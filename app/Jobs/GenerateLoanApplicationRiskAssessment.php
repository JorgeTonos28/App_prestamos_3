<?php

namespace App\Jobs;

use App\Models\LoanApplication;
use App\Models\LoanApplicationEvent;
use App\Services\RiskAssessmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateLoanApplicationRiskAssessment implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 240;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $applicationId, public readonly bool $force = false)
    {
        $this->onQueue('risk-analysis');
    }

    public function uniqueId(): string
    {
        return (string) $this->applicationId;
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(RiskAssessmentService $service): void
    {
        $service->generate(LoanApplication::findOrFail($this->applicationId), $this->force);
    }

    public function failed(Throwable $exception): void
    {
        $application = LoanApplication::find($this->applicationId);
        if (! $application) {
            return;
        }

        $application->update(['status' => LoanApplication::STATUS_ERROR]);
        LoanApplicationEvent::create([
            'loan_application_id' => $application->id,
            'actor_type' => 'system',
            'event' => 'risk_assessment_failed',
            'from_status' => LoanApplication::STATUS_ANALYZING,
            'to_status' => LoanApplication::STATUS_ERROR,
            'metadata' => ['error_type' => class_basename($exception)],
        ]);
    }
}
