<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    protected $interestEngine;

    public function __construct(InterestEngine $interestEngine)
    {
        $this->interestEngine = $interestEngine;
    }

    public function registerPayment(Loan $loan, Carbon $paidAt, float $amount, string $method, ?string $reference = null, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($loan, $paidAt, $amount, $method, $reference, $notes) {
            // 1. Accrue interest up to payment date
            $this->interestEngine->accrueUpTo($loan, $paidAt);

            // 2. Allocation logic
            // Priority: Fees -> Interest -> Principal

            $remainingAmount = $amount;

            // Fees
            $feesToPay = min($remainingAmount, (float) ($loan->fees_accrued ?? 0));
            $remainingAmount -= $feesToPay;

            // Interest
            $interestToPay = min($remainingAmount, (float) ($loan->interest_accrued ?? 0));
            $remainingAmount -= $interestToPay;

            // Principal
            // Cap principal payment at principal_outstanding to avoid negative principal.
            $principalToPay = min($remainingAmount, (float) $loan->principal_outstanding);

            // Check for overpayment (excess)
            $excess = $remainingAmount - $principalToPay;
            // In Phase 1, we just ignore excess or could log it.
            // For now, we only apply what is owed.

            // 3. Create Ledger Entry
            // Deltas are negative for payments (reducing debt)
            $principalDelta = -$principalToPay;
            $interestDelta = -$interestToPay;
            $feesDelta = -$feesToPay;

            // Total amount applied effectively
            $totalApplied = $feesToPay + $interestToPay + $principalToPay;
            $newBalance = $loan->balance_total - $totalApplied;

            LoanLedgerEntry::create([
                'loan_id' => $loan->id,
                'type' => 'payment',
                'occurred_at' => $paidAt,
                'amount' => $totalApplied, // Only record effective payment
                'principal_delta' => $principalDelta,
                'interest_delta' => $interestDelta,
                'fees_delta' => $feesDelta,
                'balance_after' => $newBalance,
                'meta' => [
                    'method' => $method,
                    'reference' => $reference,
                    'notes' => $notes
                ]
            ]);

            // 4. Update Loan Cache
            $loan->fees_accrued -= $feesToPay;
            $loan->interest_accrued -= $interestToPay;
            $loan->principal_outstanding -= $principalToPay;
            $loan->balance_total = $newBalance;

            if ($loan->balance_total <= 0.01) { // Tolerance for float
                $loan->status = 'closed';
                $loan->balance_total = 0; // Clean up
            }

            $loan->save();

            // 5. Create Payment Record
            return Payment::create([
                'loan_id' => $loan->id,
                'client_id' => $loan->client_id,
                'paid_at' => $paidAt,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'applied_interest' => $interestToPay,
                'applied_principal' => $principalToPay,
                'applied_fees' => $feesToPay,
                'notes' => $notes ?? ''
            ]);
        });
    }
}
