<?php

namespace Tests\Feature;

use App\Jobs\GenerateLoanApplicationRiskAssessment;
use App\Models\ApplicantDocument;
use App\Models\Client;
use App\Models\LoanApplication;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsAppAgentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoanApplicationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_inbox_and_private_files_require_authentication(): void
    {
        $application = $this->application();
        $document = $this->document($application);

        $this->get(route('loan-applications.index'))->assertRedirect(route('login'));
        $this->get(route('loan-applications.show', $application))->assertRedirect(route('login'));
        $this->get(route('applicant-documents.download', $document))->assertRedirect(route('login'));
    }

    public function test_admin_can_review_a_complete_application(): void
    {
        $application = $this->application();
        $application->riskAssessments()->create([
            'version' => 1,
            'status' => 'completed',
            'score' => 22,
            'level' => 'low',
            'recommendation' => 'consider_approval',
            'summary' => 'Expediente para revisión humana.',
            'report' => 'Informe detallado.',
            'factors' => [],
            'red_flags' => [],
            'mitigants' => ['Ingreso estable.'],
            'generated_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('loan-applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LoanApplications/Show')
                ->where('application.reference', 'SOL-000001')
                ->where('application.risk_assessments.0.level', 'low')
                ->has('application.applicant_data')
            );
    }

    public function test_approval_creates_client_links_documents_and_notifies_without_creating_a_loan(): void
    {
        Storage::fake('local');
        $settings = $this->configuredAgent();
        Http::fake([
            'https://graph.facebook.com/v23.0/123456789/messages' => Http::response([
                'messages' => [['id' => 'wamid.decision-approved']],
            ]),
        ]);

        $application = $this->application();
        $document = $this->document($application);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('loan-applications.decision', $application), [
            'decision' => 'approved',
            'review_notes' => 'Capacidad y documentos revisados.',
            'create_client' => true,
        ])->assertRedirect(route('loan-applications.show', $application));

        $application->refresh();
        $client = Client::firstOrFail();
        $this->assertSame(LoanApplication::STATUS_APPROVED, $application->status);
        $this->assertSame($user->id, $application->reviewed_by_user_id);
        $this->assertSame($client->id, $application->client_id);
        $this->assertSame($client->id, $document->fresh()->client_id);
        $this->assertNotNull($application->decision_notified_at);
        $this->assertDatabaseCount('loans', 0);

        Http::assertSent(fn (HttpRequest $request): bool =>
            $request['type'] === 'text'
            && str_contains($request['text']['body'], 'aprobada por el administrador')
        );
    }

    public function test_manual_document_validation_dispatches_risk_analysis_when_checklist_is_complete(): void
    {
        Queue::fake();
        $application = $this->application([
            'status' => LoanApplication::STATUS_COLLECTING_DOCUMENTS,
            'current_step' => 'documents_validation',
            'required_documents' => [[
                'key' => 'identity_document',
                'label' => 'Documento de identidad',
                'required' => true,
            ]],
        ]);
        $document = $this->document($application, ['status' => 'pending_manual_review']);

        $this->actingAs(User::factory()->create())
            ->post(route('applicant-documents.review', $document), [
                'status' => 'valid',
                'notes' => 'Coincide visualmente con el expediente.',
            ])->assertRedirect();

        $this->assertSame('valid', $document->fresh()->status);
        $this->assertSame(LoanApplication::STATUS_READY_FOR_ANALYSIS, $application->fresh()->status);
        Queue::assertPushed(GenerateLoanApplicationRiskAssessment::class, 1);
    }

    public function test_agent_secrets_are_encrypted_and_never_returned_to_settings_page(): void
    {
        $user = User::factory()->create();
        $payload = [
            'whatsapp_graph_version' => 'v23.0',
            'whatsapp_phone_number_id' => '123456789',
            'whatsapp_business_account_id' => '987654321',
            'whatsapp_access_token' => 'meta-super-secret',
            'whatsapp_app_secret' => 'app-super-secret',
            'whatsapp_verify_token' => 'verify-super-secret-123',
            'openai_api_key' => 'openai-super-secret',
            'openai_model' => 'gpt-5.6-terra',
            'whatsapp_required_documents' => ['identity_document', 'bank_statements_6_months'],
            'whatsapp_agent_enabled' => true,
        ];

        $this->actingAs($user)->post(route('settings.update'), $payload)->assertRedirect();

        $raw = Setting::where('key', 'whatsapp_access_token')->value('value');
        $this->assertNotSame('meta-super-secret', $raw);
        $this->assertSame('meta-super-secret', app(WhatsAppAgentSettings::class)->secret('whatsapp_access_token'));
        $this->assertTrue(app(WhatsAppAgentSettings::class)->configurationStatus()['enabled']);

        $response = $this->actingAs($user)->get(route('settings.edit', ['tab' => 'whatsapp']));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Edit')
            ->where('activeTab', 'whatsapp')
            ->where('whatsappAgent.secrets.whatsapp_access_token', true)
            ->missing('settings.whatsapp_access_token')
            ->missing('settings.openai_api_key')
        );
        $response->assertDontSee('meta-super-secret');
        $response->assertDontSee('openai-super-secret');
    }

    private function configuredAgent(): WhatsAppAgentSettings
    {
        $settings = app(WhatsAppAgentSettings::class);
        $settings->put('whatsapp_phone_number_id', '123456789');
        $settings->put('whatsapp_graph_version', 'v23.0');
        $settings->putSecret('whatsapp_access_token', 'meta-access-token');

        return $settings;
    }

    private function application(array $overrides = []): LoanApplication
    {
        $application = LoanApplication::create([
            'whatsapp_phone' => '18095551234',
            'whatsapp_profile_name' => 'Ana Pérez',
            'status' => LoanApplication::STATUS_PENDING_REVIEW,
            'current_step' => 'human_review',
            'consent_version' => 'v1',
            'consent_at' => now(),
            'submitted_at' => now(),
            'required_documents' => [],
            'risk_score' => 22,
            'risk_level' => 'low',
            'applicant_data' => [
                'full_name' => 'Ana María Pérez',
                'document_type' => 'cedula',
                'national_id' => '001-1234567-8',
                'email' => 'ana@example.com',
                'address' => 'Santo Domingo',
            ],
            'loan_request' => [
                'requested_amount' => 100000,
                'loan_type' => 'personal',
            ],
            ...$overrides,
        ]);
        $application->conversation()->create([
            'phone' => '18095551234',
            'status' => 'active',
            'current_step' => $application->current_step,
            'customer_service_window_expires_at' => now()->addHours(12),
        ]);

        return $application;
    }

    private function document(LoanApplication $application, array $overrides = []): ApplicantDocument
    {
        Storage::disk('local')->put('applicant-documents/test/document.pdf', '%PDF-1.4 test');

        return ApplicantDocument::create([
            'loan_application_id' => $application->id,
            'document_type' => 'identity_document',
            'label' => 'Documento de identidad',
            'original_name' => 'documento.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 13,
            'checksum_sha256' => hash('sha256', 'document-'.$application->id),
            'disk' => 'local',
            'storage_path' => 'applicant-documents/test/document.pdf',
            'status' => 'valid',
            'malware_scan_status' => 'clean',
            'validation_results' => [],
            'received_at' => now(),
            'validated_at' => now(),
            ...$overrides,
        ]);
    }
}
