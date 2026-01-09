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
            // Can pay more than outstanding if prepaying? Usually max is principal_outstanding unless we allow credit balance.
            // Let's assume we cap at principal_outstanding for now, or put rest in "excess/credit" (not handled in simple model, so cap at total balance)
            // But wait, user might overpay. For now, let's just reduce principal. If it goes negative, it's weird.
            // Let's Cap principal payment at principal_outstanding.
            $principalToPay = min($remainingAmount, $loan->principal_outstanding);

            // If there is still remainder, it means overpayment.
            // In a simple app, we might reject or put it as 'credit'.
            // For this scope, let's assume strict checks on UI or just apply to principal (negative principal = credit).
            // Let's apply whatever remains to principal.
            $principalToPay = $remainingAmount; // Just apply the rest.

            // 3. Create Ledger Entry
            // Deltas are negative for payments (reducing debt)
            $principalDelta = -$principalToPay;
            $interestDelta = -$interestToPay;
            $feesDelta = -$feesToPay;

            $newBalance = $loan->balance_total - $amount;

            LoanLedgerEntry::create([
                'loan_id' => $loan->id,
                'type' => 'payment',
                'occurred_at' => $paidAt,
                'amount' => $amount,
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
