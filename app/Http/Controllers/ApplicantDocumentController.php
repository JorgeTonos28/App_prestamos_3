<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateLoanApplicationRiskAssessment;
use App\Models\ApplicantDocument;
use App\Models\LoanApplication;
use App\Models\LoanApplicationEvent;
use App\Services\WhatsAppCloudService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ApplicantDocumentController extends Controller
{
    public function download(ApplicantDocument $applicantDocument)
    {
        abort_if(in_array($applicantDocument->status, ['quarantined'], true), 404);
        abort_unless(Storage::disk($applicantDocument->disk)->exists($applicantDocument->storage_path), 404);

        $extension = match ($applicantDocument->mime_type) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin',
        };
        $filename = str($applicantDocument->label)->slug()->limit(80).'.'.$extension;

        return Storage::disk($applicantDocument->disk)->download(
            $applicantDocument->storage_path,
            $filename,
            ['Content-Type' => $applicantDocument->mime_type]
        );
    }

    public function review(
        Request $request,
        ApplicantDocument $applicantDocument,
        WhatsAppCloudService $whatsApp
    ) {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['valid', 'invalid'])],
            'notes' => [Rule::requiredIf($request->input('status') === 'invalid'), 'nullable', 'string', 'max:2000'],
        ]);

        abort_if($applicantDocument->malware_scan_status === 'infected', 422, 'Un archivo detectado como malicioso no puede validarse.');

        $results = $applicantDocument->validation_results ?? [];
        $results['manual_review'] = [
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now()->toIso8601String(),
        ];
        $applicantDocument->update([
            'status' => $validated['status'],
            'validation_results' => $results,
            'rejection_reason' => $validated['status'] === 'invalid' ? $validated['notes'] : null,
            'validated_at' => now(),
        ]);

        $application = $applicantDocument->application->fresh(['documents', 'conversation']);
        LoanApplicationEvent::create([
            'loan_application_id' => $application->id,
            'user_id' => $request->user()->id,
            'actor_type' => 'admin',
            'event' => 'document_manually_'.$validated['status'],
            'metadata' => ['document_id' => $applicantDocument->id],
        ]);

        if ($validated['status'] === 'invalid') {
            $application->update([
                'status' => LoanApplication::STATUS_COLLECTING_DOCUMENTS,
                'current_step' => 'documents',
            ]);
            $application->conversation?->update(['status' => 'active', 'current_step' => 'documents', 'closed_at' => null]);

            try {
                if ($application->conversation?->isInsideCustomerServiceWindow()) {
                    $whatsApp->sendText(
                        $application->conversation,
                        "El documento {$applicantDocument->label} requiere corrección. Por favor envíalo nuevamente."
                    );
                }
            } catch (\Throwable) {
                // The review remains saved and the failed outbound message is audited.
            }

            return redirect()->back()->with('success', 'Documento rechazado; la solicitud volvió a recolección.');
        }

        $required = collect($application->required_documents ?? [])->pluck('key');
        $valid = $application->documents->where('status', 'valid')->pluck('document_type');
        if ($required->diff($valid)->isEmpty()) {
            $application->update([
                'status' => LoanApplication::STATUS_READY_FOR_ANALYSIS,
                'current_step' => 'analysis',
                'submitted_at' => $application->submitted_at ?? now(),
            ]);
            $application->conversation?->update(['current_step' => 'analysis']);
            GenerateLoanApplicationRiskAssessment::dispatch($application->id);
        }

        return redirect()->back()->with('success', 'Documento validado correctamente.');
    }
}
