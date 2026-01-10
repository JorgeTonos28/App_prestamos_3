<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use App\Models\LoanRefinance;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RefinanceService
{
    protected $interestEngine;

    public function __construct(InterestEngine $interestEngine)
    {
        $this->interestEngine = $interestEngine;
    }

    /**
     * Consolidate multiple loans into a new one.
     *
     * @param Client $client
     * @param array $oldLoanIds IDs of loans to be refinanced
     * @param array $newLoanData Data for the new loan (amount will be calculated)
     * @return Loan
     */
    public function refinance(Client $client, array $oldLoanIds, array $newLoanData): Loan
    {
        return DB::transaction(function () use ($client, $oldLoanIds, $newLoanData) {
            $oldLoans = Loan::whereIn('id', $oldLoanIds)->where('client_id', $client->id)->where('status', 'active')->get();
            $totalPayoff = 0;
            $payoffAmounts = [];
            $refinanceDate = Carbon::parse($newLoanData['start_date'] ?? now());

            // 1. Calculate payoff for each old loan and close it
            foreach ($oldLoans as $oldLoan) {
                // Accrue interest up to refinance date
                $this->interestEngine->accrueUpTo($oldLoan, $refinanceDate);

                $payoffAmount = $oldLoan->balance_total;
                $totalPayoff += $payoffAmount;
                $payoffAmounts[$oldLoan->id] = $payoffAmount;

                // Create Ledger Entry for Refinance Payoff (closes the loan)
                LoanLedgerEntry::create([
                    'loan_id' => $oldLoan->id,
                    'type' => 'refinance_payoff',
                    'occurred_at' => $refinanceDate,
                    'amount' => $payoffAmount,
                    'principal_delta' => -$oldLoan->principal_outstanding,
                    'interest_delta' => -$oldLoan->interest_accrued,
                    'fees_delta' => -$oldLoan->fees_accrued,
                    'balance_after' => 0,
                    'meta' => ['new_loan_code' => $newLoanData['code'] ?? 'PENDING']
                ]);

                // Close old loan
                $oldLoan->principal_outstanding = 0;
                $oldLoan->interest_accrued = 0;
                $oldLoan->fees_accrued = 0;
                $oldLoan->balance_total = 0;
                $oldLoan->status = 'closed_refinanced';
                $oldLoan->save();
            }

            // 2. Create New Loan
            $principalInitial = $newLoanData['principal_initial'] ?? $totalPayoff;
            if ($principalInitial < $totalPayoff) {
                // Can't be less than what we owe
                $principalInitial = $totalPayoff;
            }

            $loan = new Loan();
            $loan->fill($newLoanData);
            $loan->client_id = $client->id;
            $loan->principal_initial = $principalInitial;
            $loan->principal_outstanding = $principalInitial;
            $loan->balance_total = $principalInitial;
            $loan->status = 'active'; // Active immediately

            $loan->save();

            // 3. Disbursement Ledger for New Loan
            LoanLedgerEntry::create([
                'loan_id' => $loan->id,
                'type' => 'disbursement',
                'occurred_at' => $refinanceDate,
                'amount' => $principalInitial,
                'principal_delta' => $principalInitial,
                'interest_delta' => 0,
                'fees_delta' => 0,
                'balance_after' => $principalInitial,
                'meta' => ['refinanced_loan_ids' => $oldLoanIds]
            ]);

            // 4. Link records
            foreach ($oldLoans as $oldLoan) {
                LoanRefinance::create([
                    'client_id' => $client->id,
                    'new_loan_id' => $loan->id,
                    'old_loan_id' => $oldLoan->id,
                    'payoff_amount' => $payoffAmounts[$oldLoan->id] ?? 0
                ]);
            }

            return $loan;
        });
    }
}
