<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LegalStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateLegalLoans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loans:update-legal-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Moves overdue loans into legal status and applies legal entry fees.';

    public function handle(LegalStatusService $legalStatusService): int
    {
        $this->info('Evaluating legal status for active loans.');

        $eligibleLoans = Loan::where('status', 'active')
            ->where('legal_status', false)
            ->with('ledgerEntries')
            ->get();

        $updated = 0;

        foreach ($eligibleLoans as $loan) {
            try {
                if ($legalStatusService->moveToLegalIfNeeded($loan, now())) {
                    $updated++;
                    $this->info("Loan {$loan->code} moved to legal.");
                }
            } catch (\Throwable $e) {
                Log::error('Failed to move loan to legal', [
                    'loan_id' => $loan->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Completed. {$updated} loans moved to legal.");

        return Command::SUCCESS;
    }
}
