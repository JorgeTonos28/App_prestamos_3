<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use App\Models\Setting;
use App\Services\ArrearsCalculator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

    public function handle(ArrearsCalculator $calculator): int
    {
        $threshold = (int) (Setting::where('key', 'legal_days_overdue_threshold')->value('value') ?? 30);
        $legalFee = (float) (Setting::where('key', 'legal_entry_fee_default')->value('value') ?? 4000);

        $this->info("Evaluating legal status (threshold: {$threshold} days).");

        $eligibleLoans = Loan::where('status', 'active')
            ->where('legal_status', false)
            ->with('ledgerEntries')
            ->get();

        $updated = 0;

        foreach ($eligibleLoans as $loan) {
            $arrears = $calculator->calculate($loan);

            if (($arrears['days'] ?? 0) < $threshold) {
                continue;
            }

            try {
                DB::transaction(function () use ($loan, $legalFee) {
                    $loan->legal_status = true;
                    $loan->legal_entered_at = Carbon::now()->toDateString();
                    $loan->save();

                    if ($legalFee > 0) {
                        $newBalance = $loan->balance_total + $legalFee;

                        LoanLedgerEntry::create([
                            'loan_id' => $loan->id,
                            'type' => 'legal_fee',
                            'occurred_at' => Carbon::now(),
                            'amount' => $legalFee,
                            'principal_delta' => 0,
                            'interest_delta' => 0,
                            'fees_delta' => $legalFee,
                            'balance_after' => $newBalance,
                            'meta' => [
                                'reason' => 'legal_entry',
                                'auto_created' => true,
                            ],
                        ]);

                        $loan->fees_accrued += $legalFee;
                        $loan->balance_total = $newBalance;
                        $loan->save();
                    }
                });

                $updated++;
                $this->info("Loan {$loan->code} moved to legal.");
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
