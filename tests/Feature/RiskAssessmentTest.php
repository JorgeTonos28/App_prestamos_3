<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Services\RiskAssessmentService;
use App\Services\RiskScoringService;
use App\Services\WhatsAppAgentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RiskAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppAgentSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(WhatsAppAgentSettings::class);
        $this->settings->forgetSecret('openai_api_key');
        config(['services.openai.api_key' => null]);
    }

    public function test_deterministic_rules_flag_unsafe_capacity_and_require_human_review(): void
    {
        $application = $this->application([
            'monthly_income' => 30000,
            'monthly_expenses' => 25000,
            'monthly_debt_payments' => 15000,
            'employment_status' => 'unemployed',
            'employment_tenure_months' => 0,
        ], [
            'requested_amount' => 500000,
            'preferred_installment' => 8000,
        ]);

        $assessment = app(RiskAssessmentService::class)->generate($application);

        $this->assertSame('completed', $assessment->status);
        $this->assertSame('high', $assessment->level);
        $this->assertSame('decline_or_mitigate', $assessment->recommendation);
        $this->assertGreaterThan(65, (float) $assessment->score);
        $this->assertStringContainsString('Requiere decisión humana', $assessment->summary);
        $this->assertSame(LoanApplication::STATUS_PENDING_REVIEW, $application->fresh()->status);
        $this->assertNull($assessment->model);
        $this->assertSame('human_review', $application->fresh()->current_step);
    }

    public function test_openai_only_writes_the_narrative_and_receives_no_tools_or_direct_identifiers(): void
    {
        $this->settings->putSecret('openai_api_key', 'openai-test-key');
        $this->settings->put('openai_model', 'gpt-5.6-terra');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'executive_summary' => 'Capacidad saludable, sujeta a verificación humana.',
                            'capacity_analysis' => 'Los indicadores declarados muestran margen disponible.',
                            'document_analysis' => 'No se configuraron documentos para esta prueba.',
                            'consistency_analysis' => 'No hay discrepancias visibles en los datos suministrados.',
                            'verification_questions' => ['Confirmar ingresos en fuente primaria.'],
                            'recommendation_rationale' => 'El score fijo permite considerar aprobación sin automatizarla.',
                            'confidence' => 'medium',
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        $application = $this->application([
            'full_name' => 'Persona Privada',
            'national_id' => '001-1234567-8',
            'address' => 'Dirección que no debe enviarse',
            'monthly_income' => 80000,
            'monthly_expenses' => 25000,
            'monthly_debt_payments' => 5000,
            'employment_status' => 'employed',
            'employment_tenure_months' => 36,
        ], [
            'requested_amount' => 120000,
            'preferred_installment' => 9000,
            'loan_type' => 'personal',
            'loan_purpose' => 'Mejoras del hogar',
            'term_count' => 18,
            'payment_frequency' => 'monthly',
        ]);
        $expectedScore = app(RiskScoringService::class)->score($application)['score'];

        $assessment = app(RiskAssessmentService::class)->generate($application);

        $this->assertEquals($expectedScore, (float) $assessment->score);
        $this->assertSame('Capacidad saludable, sujeta a verificación humana.', $assessment->summary);

        Http::assertSent(function (HttpRequest $request): bool {
            $payload = $request->data();
            $serialized = json_encode($payload, JSON_THROW_ON_ERROR);

            return $request->hasHeader('Authorization', 'Bearer openai-test-key')
                && $payload['model'] === 'gpt-5.6-terra'
                && $payload['store'] === false
                && ! array_key_exists('tools', $payload)
                && data_get($payload, 'text.format.type') === 'json_schema'
                && ! str_contains($serialized, '001-1234567-8')
                && ! str_contains($serialized, 'Dirección que no debe enviarse');
        });
    }

    private function application(array $applicantOverrides, array $loanOverrides): LoanApplication
    {
        return LoanApplication::create([
            'whatsapp_phone' => '18095550000',
            'status' => LoanApplication::STATUS_READY_FOR_ANALYSIS,
            'current_step' => 'analysis',
            'consent_version' => 'v1',
            'consent_at' => now(),
            'required_documents' => [],
            'applicant_data' => [
                'birth_date' => '1990-01-01',
                'monthly_income' => 50000,
                'monthly_expenses' => 20000,
                'monthly_debt_payments' => 0,
                'employment_status' => 'employed',
                'employment_tenure_months' => 24,
                ...$applicantOverrides,
            ],
            'loan_request' => [
                'requested_amount' => 100000,
                'preferred_installment' => 8000,
                'loan_type' => 'personal',
                'loan_purpose' => 'Uso personal',
                'term_count' => 12,
                'payment_frequency' => 'monthly',
                ...$loanOverrides,
            ],
        ]);
    }
}
