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

            $paymentDate = $paidAt->copy()->startOfDay();

            // Handle Payment Replay if inserting in the past (before other payments)
            // Strategy:
            // 1. Find all payments strictly AFTER the new payment date.
            // 2. Rollback their effects (Delete payments, delete associated ledger entries, delete accruals).
            // 3. Reset Loan state to what it was at $paidAt (or rebuild forward).
            // 4. Insert new payment.
            // 5. Re-apply old payments in order.

            $futurePayments = Payment::where('loan_id', $loan->id)
                ->whereDate('paid_at', '>', $paidAt)
                ->orderBy('paid_at')
                ->get();

            // Determine if we need to rollback ledger entries (Future Payments OR Future Interest Accruals)
            // Even if no future payments, there might be interest accruals from "Show" views or other logic
            // that are dated AFTER this new retroactive payment. We must wipe them to re-calculate correctly.
            $hasFutureActivity = LoanLedgerEntry::where('loan_id', $loan->id)
                ->where('occurred_at', '>', $paidAt)
                ->exists();

            if ($futurePayments->count() > 0 || $hasFutureActivity) {
                // We are in "Replay/Correction Mode"

                // Find all Ledger entries after $paidAt
                $futureEntries = LoanLedgerEntry::where('loan_id', $loan->id)
                    ->where('occurred_at', '>', $paidAt) // Strict greater
                    ->orderBy('occurred_at', 'desc') // Reverse order to rollback safely if needed
                    ->get();

                foreach ($futureEntries as $entry) {
                    $this->reverseLedgerEntryEffect($loan, $entry);
                    $entry->delete();
                }

                // Delete the actual Payment records for the future payments (we have them in memory to replay)
                if ($futurePayments->count() > 0) {
                     Payment::where('loan_id', $loan->id)->whereDate('paid_at', '>', $paidAt)->delete();
                }

                // Reset last_accrual_date to the latest event BEFORE $paidAt.
                $lastEvent = LoanLedgerEntry::where('loan_id', $loan->id)
                    ->orderBy('occurred_at', 'desc')
                    ->first();

                // If no event (e.g. at start), use start_date.
                $loan->last_accrual_date = $lastEvent ? $lastEvent->occurred_at : $loan->start_date;
                $loan->save();
            }

            // 1. Accrue interest up to NEW payment date
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

            $ledgerEntry = LoanLedgerEntry::create([
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

            // Set last accrual date to this payment date to prevent double-accrual for this day
            // Actually, accrueUpTo handles this idempotency, but explicitly setting it is safe.
            $loan->last_accrual_date = $paidAt;

            $loan->save();

            // 5. Create Payment Record
            $newPayment = Payment::create([
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

            // Update ledger entry meta with payment ID
            $meta = $ledgerEntry->meta ?? [];
            $meta['payment_id'] = $newPayment->id;
            $ledgerEntry->meta = $meta;
            $ledgerEntry->save();

            // REPLAY Future Payments (if any)
            if (isset($futurePayments) && $futurePayments->count() > 0) {
                foreach ($futurePayments as $fp) {

                    // We must ensure the notes don't get appended repeatedly in a loop
                    $notesToReplay = $fp->notes;
                    if (!str_contains($notesToReplay, '(Reprocesado)')) {
                         $notesToReplay .= ' (Reprocesado)';
                    }

                    $this->registerPayment(
                        $loan->fresh(), // Reload loan state
                        Carbon::parse($fp->paid_at),
                        $fp->amount,
                        $fp->method,
                        $fp->reference,
                        $notesToReplay
                    );
                }
            }

            return $newPayment;
        });
    }

    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $loan = $payment->loan;
            $paidAt = $payment->paid_at->copy()->startOfDay();

            // 1. Find all payments strictly AFTER or ON THE SAME DAY (but different ID)
            // We must replay siblings on the same day to restore them after rollback.
            $futurePayments = Payment::where('loan_id', $loan->id)
                ->where('paid_at', '>=', $paidAt)
                ->where('id', '!=', $payment->id)
                ->orderBy('paid_at')
                ->get();

            // 2. Identify all ledger entries >= $paidAt
            $entriesToRollback = LoanLedgerEntry::where('loan_id', $loan->id)
                ->where('occurred_at', '>=', $paidAt)
                ->orderBy('occurred_at', 'desc')
                ->get();

            // Rollback Ledger Effects
            foreach ($entriesToRollback as $entry) {
                // EXCLUDE DISBURSEMENT
                // If a payment is made on the same day as disbursement, do NOT delete the disbursement.
                if ($entry->type === 'disbursement') {
                    continue;
                }

                $this->reverseLedgerEntryEffect($loan, $entry);
                $entry->delete();
            }

            // 3. Delete payments explicitly to ensure the target is gone even if date logic is fuzzy
            // Delete the target payment first
            $payment->delete();

            // Delete future payments (we will replay them)
            if ($futurePayments->count() > 0) {
                // Use ID-based deletion to be safe
                Payment::whereIn('id', $futurePayments->pluck('id'))->delete();
            }

            // Reset Loan State to just before this date
            $lastEvent = LoanLedgerEntry::where('loan_id', $loan->id)
                    ->orderBy('occurred_at', 'desc')
                    ->first();

            $loan->last_accrual_date = $lastEvent ? $lastEvent->occurred_at : $loan->start_date;

            // If loan was closed, reopen it temporarily (replay will close it if needed)
            if ($loan->status === 'closed') {
                $loan->status = 'active';
            }

            $loan->save();

            // REPLAY
            foreach ($futurePayments as $fp) {
                 $this->registerPayment(
                    $loan->fresh(),
                    Carbon::parse($fp->paid_at),
                    $fp->amount,
                    $fp->method,
                    $fp->reference,
                    $fp->notes
                );
            }
        });
    }

    private function reverseLedgerEntryEffect(Loan $loan, LoanLedgerEntry $entry): void
    {
        // Reverse the effect on the Loan Balance Cache
        $loan->principal_outstanding -= $entry->principal_delta;
        $loan->interest_accrued -= $entry->interest_delta;
        $loan->fees_accrued -= $entry->fees_delta;

        // balance_total logic:
        $totalDelta = $entry->principal_delta + $entry->interest_delta + $entry->fees_delta;
        $loan->balance_total -= $totalDelta;
    }
}
