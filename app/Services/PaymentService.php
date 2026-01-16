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

            // Retroactive Payment Handling
            // If the payment date is before the last accrual date, we must reverse subsequent interest,
            // apply the payment, and then let the system re-accrue (if needed, usually by next view/cron).
            // Actually, we should re-accrue immediately to 'now' (or back to where it was) to restore state.
            // But 'accrueUpTo' is safe to call.

            $lastAccrual = $loan->last_accrual_date ? Carbon::parse($loan->last_accrual_date)->startOfDay() : null;
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

            if ($futurePayments->count() > 0) {
                // We are in "Replay Mode"
                // First, rewind everything after $paidAt (including the future payments and any accruals)
                // Actually, if we just delete ALL ledger entries > $paidAt, we can rebuild.

                // Find all Ledger entries after $paidAt
                $futureEntries = LoanLedgerEntry::where('loan_id', $loan->id)
                    ->where('occurred_at', '>', $paidAt) // Strict greater
                    ->orderBy('occurred_at', 'desc') // Reverse order to rollback safely if needed
                    ->get();

                foreach ($futureEntries as $entry) {
                    // Reverse the effect on the Loan Balance Cache
                    // Logic: Entry reduced balance by (principal + fees + interest)?
                    // No, Entry recorded what happened.
                    // We need to REVERSE the delta.
                    // Principal Delta: -100 (Payment). Reverse: +100.
                    // Interest Delta: -50 (Payment). Reverse: +50.
                    // Interest Accrual: +20 (Accrual). Reverse: -20.

                    $loan->principal_outstanding -= $entry->principal_delta; // (-) - (-) = +
                    $loan->interest_accrued -= $entry->interest_delta;
                    $loan->fees_accrued -= $entry->fees_delta;

                    // balance_total logic:
                    // If payment: balance reduced. Reverse: balance increases.
                    // If accrual: balance increased. Reverse: balance decreases.
                    // Balance After was X. Balance Before was X - Delta.
                    // So we are walking back to "Balance Before".
                    // But simpler: Just invert the deltas.
                    // Delta for Payment is negative. So subtracting it adds it back.
                    // Delta for Accrual is positive. So subtracting it removes it.
                    // Total Balance Delta = PrincipalDelta + InterestDelta + FeesDelta (usually).
                    // Wait. Interest Accrual: PrincipalDelta=0, IntDelta=+20. Total=+20.
                    // Payment: PrincDelta=-100, IntDelta=-20. Total=-120.

                    // So subtracting the deltas from the CURRENT loan state should restore it?
                    // Yes, assuming the current loan state MATCHES the end of the chain.
                    // Warning: If we have "Pending Interest" displayed in UI but not saved, we rely on DB state.
                    // DB state should be consistent.

                    $totalDelta = $entry->principal_delta + $entry->interest_delta + $entry->fees_delta;
                    $loan->balance_total -= $totalDelta;

                    $entry->delete();
                }

                // Delete the actual Payment records for the future payments (we have them in memory to replay)
                // Note: The ledger loop above deleted their ledger entries.
                Payment::where('loan_id', $loan->id)->whereDate('paid_at', '>', $paidAt)->delete();

                // Reset last_accrual_date to $paidAt (effectively rewind clock)
                // Actually, we should reset it to the latest event BEFORE $paidAt.
                // Or easier: Just set it to $paidAt? No, that skips accrual UP TO $paidAt.
                // We want to accrue UP TO $paidAt.
                // So finding the last event before $paidAt is safer?
                // Or simpler: Set last_accrual_date to Loan Start Date if no prior events?
                // Ledger is now clean after $paidAt.
                // Find max occurred_at in ledger <= $paidAt.
                $lastEvent = LoanLedgerEntry::where('loan_id', $loan->id)
                    ->orderBy('occurred_at', 'desc')
                    ->first();

                $loan->last_accrual_date = $lastEvent ? $lastEvent->occurred_at : $loan->start_date;
                $loan->save();
            } else {
                // Standard Retroactive Check (No future payments, but maybe future ACCRUALS due to viewing?)
                // This handles the case where user viewed dashboard (generating daily interest) but didn't pay.
                // We want to wipe those "future" accruals too if they exist.

                $lastAccrual = $loan->last_accrual_date ? Carbon::parse($loan->last_accrual_date)->startOfDay() : null;
                $paymentDate = $paidAt->copy()->startOfDay();

                if ($lastAccrual && $paymentDate->lt($lastAccrual)) {
                    $futureAccruals = LoanLedgerEntry::where('loan_id', $loan->id)
                        ->where('type', 'interest_accrual')
                        ->whereDate('occurred_at', '>', $paymentDate)
                        ->get();

                    foreach ($futureAccruals as $entry) {
                        $loan->interest_accrued -= $entry->interest_delta;
                        $loan->balance_total -= $entry->amount;
                        $entry->delete();
                    }
                    // Reset accrual date to payment date so we accrue correctly from here
                    $loan->last_accrual_date = $paymentDate;
                    $loan->save();
                }
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

            // IMPORTANT: If we are in retroactive mode, we must re-accrue up to "now" (or next logic step).
            // But InterestEngine::accrueUpTo is idempotent for a specific date.
            // If the user views the loan later, LoanController::show calls accrueUpTo(now()).
            // However, if we just inserted a payment in the past, and we want to ensure the "Balance"
            // reflects the "corrected" future state (e.g. if we paid 2 months ago, interest since then is less),
            // we should probably trigger a re-accrual to NOW immediately so the returned view is correct.

            // Use fresh instance or reload to ensure we don't have stale state before re-accruing?
            // Actually, we just saved it. But let's be safe.
            // $loan->refresh(); // Might lose relation loaded? No, local refresh.

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

            // REPLAY Future Payments (if any)
            if (isset($futurePayments) && $futurePayments->count() > 0) {
                foreach ($futurePayments as $fp) {
                    // Recursive call? Or just iterate logic?
                    // Recursion is dangerous if logic changes.
                    // But here we can just call registerPayment again.
                    // Note: registerPayment wraps in transaction. Nested transactions are fine in Laravel.

                    $this->registerPayment(
                        $loan->fresh(), // Reload loan state
                        Carbon::parse($fp->paid_at),
                        $fp->amount,
                        $fp->method,
                        $fp->reference,
                        $fp->notes . ' (Reprocesado)'
                    );
                }
            }

            // Remove automatic catch-up to NOW.
            // This prevents generating daily interest entries during batch processing (e.g. loan creation history).
            // The caller (Controller) must invoke accrueUpTo(now()) if they want to display "up to date" info immediately.

            return $newPayment;
        });
    }
}
