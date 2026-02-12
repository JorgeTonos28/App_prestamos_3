<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\InterestEngine;
use App\Services\LegalStatusService;
use App\Services\LateFeeService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunDailyLoanAccruals extends Command
{
    protected $signature = 'loans:daily-accrual';

    protected $description = 'Runs daily accrual posting and legal-status consistency checks for active loans.';

    public function handle(LegalStatusService $legalStatusService, LateFeeService $lateFeeService, InterestEngine $interestEngine): int
    {
        $asOf = Carbon::now()->startOfDay();

        $this->info("Running daily loan consistency checks as of {$asOf->toDateString()}...");

        $processed = 0;

        Loan::query()
            ->where('status', 'active')
            ->whereNull('consolidated_into_loan_id')
            ->chunkById(200, function ($loans) use ($asOf, $legalStatusService, $lateFeeService, $interestEngine, &$processed) {
                foreach ($loans as $loan) {
                    try {
                        $lateFeeService->checkAndAccrueLateFees($loan->fresh(), $asOf);
                        $interestEngine->accrueUpTo($loan->fresh(), $asOf);
                        $legalStatusService->moveToLegalIfNeeded($loan->fresh(), $asOf);
                        $legalStatusService->ensureLegalEntryFeeExists($loan->fresh(), $asOf);

                        $processed++;
                    } catch (\Throwable $e) {
                        Log::error('Daily accrual failed for loan.', [
                            'loan_id' => $loan->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Completed daily accruals. Processed {$processed} loans.");

        return Command::SUCCESS;
    }
}
