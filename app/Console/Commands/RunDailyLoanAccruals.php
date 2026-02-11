<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LegalStatusService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunDailyLoanAccruals extends Command
{
    protected $signature = 'loans:daily-accrual';

    protected $description = 'Runs daily legal-status consistency checks for active loans.';

    public function handle(LegalStatusService $legalStatusService): int
    {
        $asOf = Carbon::now()->startOfDay();

        $this->info("Running daily loan consistency checks as of {$asOf->toDateString()}...");

        $processed = 0;

        Loan::query()
            ->where('status', 'active')
            ->whereNull('consolidated_into_loan_id')
            ->chunkById(200, function ($loans) use ($asOf, $legalStatusService, &$processed) {
                foreach ($loans as $loan) {
                    try {
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
