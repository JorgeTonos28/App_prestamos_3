<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateLoanApplicationRiskAssessment;
use App\Models\Client;
use App\Models\LoanApplication;
use App\Models\LoanApplicationEvent;
use App\Services\WhatsAppAgentSettings;
use App\Services\WhatsAppCloudService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LoanApplicationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in([
                'all',
                LoanApplication::STATUS_COLLECTING_DATA,
                LoanApplication::STATUS_COLLECTING_DOCUMENTS,
                LoanApplication::STATUS_READY_FOR_ANALYSIS,
                LoanApplication::STATUS_ANALYZING,
                LoanApplication::STATUS_PENDING_REVIEW,
                LoanApplication::STATUS_APPROVED,
                LoanApplication::STATUS_REJECTED,
                LoanApplication::STATUS_ERROR,
            ])],
            'risk_level' => ['nullable', Rule::in(['all', 'low', 'medium', 'high'])],
        ]);

        $status = $filters['status'] ?? LoanApplication::STATUS_PENDING_REVIEW;
        $riskLevel = $filters['risk_level'] ?? 'all';
        $query = LoanApplication::query()
            ->with(['latestRiskAssessment:id,loan_application_id,score,level,recommendation,status,generated_at', 'client:id,client_code,first_name,last_name'])
            ->withCount('documents')
            ->latest('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($riskLevel !== 'all') {
            $query->where('risk_level', $riskLevel);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($query) use ($search): void {
                $query->where('uuid', 'like', "%{$search}%")
                    ->orWhere('whatsapp_phone', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search): void {
                        $clientQuery->where('client_code', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $applications = $query->paginate(15)->withQueryString();
        $applications->through(fn (LoanApplication $application): array => [
            'id' => $application->id,
            'uuid' => $application->uuid,
            'reference' => 'SOL-'.str_pad((string) $application->id, 6, '0', STR_PAD_LEFT),
            'applicant_name' => $application->applicant_data['full_name']
                ?? $application->whatsapp_profile_name
                ?? 'Sin identificar',
            'whatsapp_phone' => $application->whatsapp_phone,
            'status' => $application->status,
            'current_step' => $application->current_step,
            'requested_amount' => $application->loan_request['requested_amount'] ?? null,
            'risk_score' => $application->risk_score,
            'risk_level' => $application->risk_level,
            'documents_count' => $application->documents_count,
            'client' => $application->client,
            'submitted_at' => $application->submitted_at,
            'created_at' => $application->created_at,
        ]);

        return Inertia::render('LoanApplications/Index', [
            'applications' => $applications,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $status,
                'risk_level' => $riskLevel,
            ],
            'summary' => [
                'pending_review' => LoanApplication::where('status', LoanApplication::STATUS_PENDING_REVIEW)->count(),
                'in_progress' => LoanApplication::whereIn('status', [
                    LoanApplication::STATUS_COLLECTING_DATA,
                    LoanApplication::STATUS_COLLECTING_DOCUMENTS,
                    LoanApplication::STATUS_READY_FOR_ANALYSIS,
                    LoanApplication::STATUS_ANALYZING,
                ])->count(),
                'approved' => LoanApplication::where('status', LoanApplication::STATUS_APPROVED)->count(),
                'high_risk' => LoanApplication::where('risk_level', 'high')->count(),
            ],
        ]);
    }

    public function show(LoanApplication $loanApplication, WhatsAppAgentSettings $settings)
    {
        $loanApplication->load([
            'client:id,client_code,first_name,last_name',
            'reviewedBy:id,name',
            'documents' => fn ($query) => $query->latest('id'),
            'riskAssessments' => fn ($query) => $query->latest('version'),
            'events' => fn ($query) => $query->with('user:id,name')->latest('id')->limit(100),
            'messages' => fn ($query) => $query->latest('id')->limit(100),
            'conversation',
        ]);

        return Inertia::render('LoanApplications/Show', [
            'application' => [
                'id' => $loanApplication->id,
                'uuid' => $loanApplication->uuid,
                'reference' => 'SOL-'.str_pad((string) $loanApplication->id, 6, '0', STR_PAD_LEFT),
                'status' => $loanApplication->status,
                'current_step' => $loanApplication->current_step,
                'whatsapp_phone' => $loanApplication->whatsapp_phone,
                'whatsapp_profile_name' => $loanApplication->whatsapp_profile_name,
                'applicant_data' => $loanApplication->applicant_data ?? [],
                'loan_request' => $loanApplication->loan_request ?? [],
                'required_documents' => $loanApplication->required_documents ?? [],
                'risk_score' => $loanApplication->risk_score,
                'risk_level' => $loanApplication->risk_level,
                'review_notes' => $loanApplication->review_notes,
                'consent_at' => $loanApplication->consent_at,
                'submitted_at' => $loanApplication->submitted_at,
                'reviewed_at' => $loanApplication->reviewed_at,
                'decision_notified_at' => $loanApplication->decision_notified_at,
                'created_at' => $loanApplication->created_at,
                'client' => $loanApplication->client,
                'reviewed_by' => $loanApplication->reviewedBy,
                'documents' => $loanApplication->documents->map(fn ($document): array => [
                    'id' => $document->id,
                    'uuid' => $document->uuid,
                    'document_type' => $document->document_type,
                    'label' => $document->label,
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->size_bytes,
                    'status' => $document->status,
                    'malware_scan_status' => $document->malware_scan_status,
                    'validation_results' => $document->validation_results,
                    'rejection_reason' => $document->rejection_reason,
                    'received_at' => $document->received_at,
                    'validated_at' => $document->validated_at,
                    'download_url' => route('applicant-documents.download', $document),
                ]),
                'risk_assessments' => $loanApplication->riskAssessments->map(fn ($assessment): array => [
                    'id' => $assessment->id,
                    'version' => $assessment->version,
                    'status' => $assessment->status,
                    'model' => $assessment->model,
                    'score' => $assessment->score,
                    'level' => $assessment->level,
                    'recommendation' => $assessment->recommendation,
                    'summary' => $assessment->summary,
                    'report' => $assessment->report,
                    'factors' => $assessment->factors ?? [],
                    'red_flags' => $assessment->red_flags ?? [],
                    'mitigants' => $assessment->mitigants ?? [],
                    'deterministic_breakdown' => $assessment->deterministic_breakdown,
                    'generated_at' => $assessment->generated_at,
                ]),
                'events' => $loanApplication->events->map(fn ($event): array => [
                    'id' => $event->id,
                    'event' => $event->event,
                    'actor_type' => $event->actor_type,
                    'user_name' => $event->user?->name,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'metadata' => $event->metadata,
                    'created_at' => $event->created_at,
                ]),
                'messages' => $loanApplication->messages->sortBy('id')->values()->map(fn ($message): array => [
                    'id' => $message->id,
                    'direction' => $message->direction,
                    'type' => $message->type,
                    'status' => $message->status,
                    'body' => $message->body,
                    'created_at' => $message->created_at,
                ]),
            ],
            'agentStatus' => $settings->configurationStatus(),
        ]);
    }

    public function decide(
        Request $request,
        LoanApplication $loanApplication,
        WhatsAppAgentSettings $settings,
        WhatsAppCloudService $whatsApp
    ) {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => [Rule::requiredIf($request->input('decision') === 'rejected'), 'nullable', 'string', 'max:3000'],
            'create_client' => ['nullable', 'boolean'],
        ]);

        $decision = $validated['decision'];
        $client = DB::transaction(function () use ($loanApplication, $request, $validated, $decision): ?Client {
            $application = LoanApplication::query()->lockForUpdate()->findOrFail($loanApplication->id);
            if ($application->status !== LoanApplication::STATUS_PENDING_REVIEW) {
                abort(422, 'Esta solicitud ya no está pendiente de decisión.');
            }

            $client = null;
            if ($decision === 'approved' && $request->boolean('create_client')) {
                $client = $this->createOrAttachClient($application);
                $application->documents()->update(['client_id' => $client->id]);
                $application->conversation?->update(['client_id' => $client->id]);
            }

            $application->update([
                'client_id' => $client?->id ?? $application->client_id,
                'status' => $decision,
                'reviewed_by_user_id' => $request->user()->id,
                'review_notes' => $validated['review_notes'] ?? null,
                'reviewed_at' => now(),
                'current_step' => 'completed',
            ]);
            $application->conversation?->update(['status' => 'closed', 'current_step' => 'completed', 'closed_at' => now()]);

            LoanApplicationEvent::create([
                'loan_application_id' => $application->id,
                'user_id' => $request->user()->id,
                'actor_type' => 'admin',
                'event' => 'application_'.$decision,
                'from_status' => LoanApplication::STATUS_PENDING_REVIEW,
                'to_status' => $decision,
                'metadata' => ['client_created_or_attached' => $client !== null],
            ]);

            return $client;
        });

        $notificationError = null;
        try {
            $conversation = $loanApplication->fresh()->conversation;
            if (! $conversation) {
                throw new \RuntimeException('La conversación de WhatsApp no está disponible.');
            }

            $message = $decision === 'approved'
                ? 'Tu solicitud de préstamo fue aprobada por el administrador. Nos comunicaremos contigo para formalizar los próximos pasos.'
                : 'Luego de la revisión, tu solicitud de préstamo no fue aprobada en esta ocasión. Puedes contactar al negocio si deseas más información.';
            $whatsApp->sendDecision($conversation, $decision, $message);
            $loanApplication->update(['decision_notified_at' => now()]);
            LoanApplicationEvent::create([
                'loan_application_id' => $loanApplication->id,
                'actor_type' => 'system',
                'event' => 'decision_notification_sent',
                'metadata' => ['decision' => $decision],
            ]);
        } catch (\Throwable $exception) {
            $notificationError = $exception->getMessage();
            LoanApplicationEvent::create([
                'loan_application_id' => $loanApplication->id,
                'actor_type' => 'system',
                'event' => 'decision_notification_failed',
                'metadata' => ['decision' => $decision, 'reason' => mb_substr($exception->getMessage(), 0, 300)],
            ]);
        }

        $message = $decision === 'approved'
            ? 'Solicitud aprobada'.($client ? ' y cliente creado o vinculado.' : '.')
            : 'Solicitud rechazada.';

        return redirect()->route('loan-applications.show', $loanApplication)
            ->with($notificationError ? 'error' : 'success', $notificationError
                ? $message.' La decisión se guardó, pero WhatsApp no pudo notificarse: '.$notificationError
                : $message.' El cliente fue notificado por WhatsApp.');
    }

    public function reanalyze(LoanApplication $loanApplication)
    {
        abort_unless(in_array($loanApplication->status, [
            LoanApplication::STATUS_PENDING_REVIEW,
            LoanApplication::STATUS_ERROR,
        ], true), 422, 'Esta solicitud no puede reanalizarse en su estado actual.');

        GenerateLoanApplicationRiskAssessment::dispatch($loanApplication->id, true);

        return redirect()->back()->with('success', 'El nuevo análisis de riesgo fue enviado a la cola.');
    }

    private function createOrAttachClient(LoanApplication $application): Client
    {
        $data = $application->applicant_data ?? [];
        $nationalId = trim((string) ($data['national_id'] ?? ''));
        if ($nationalId === '') {
            abort(422, 'No se puede crear el cliente sin documento de identidad.');
        }

        if (Client::withTrashed()->where('national_id', $nationalId)->whereNotNull('deleted_at')->exists()) {
            abort(422, 'Existe un cliente eliminado con esta identificación. Debe restaurarse o revisarse manualmente.');
        }

        $nameParts = preg_split('/\s+/', trim((string) ($data['full_name'] ?? ''))) ?: [];
        if (count($nameParts) < 2) {
            abort(422, 'El nombre completo no permite crear el cliente.');
        }

        $lastName = array_pop($nameParts);
        $firstName = implode(' ', $nameParts);

        return Client::firstOrCreate([
            'national_id' => $nationalId,
        ], [
            'document_type' => in_array($data['document_type'] ?? null, ['cedula', 'passport'], true)
                ? $data['document_type']
                : 'cedula',
            'first_name' => Str::title(Str::lower($firstName)),
            'last_name' => Str::title(Str::lower($lastName)),
            'phone' => $application->whatsapp_phone,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => 'active',
            'notes' => 'Creado desde la solicitud WhatsApp SOL-'.str_pad((string) $application->id, 6, '0', STR_PAD_LEFT).'.',
        ]);
    }
}
