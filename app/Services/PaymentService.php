<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use App\Models\Payment;
use Carbon\Carbon;
use App\Support\LoanCycle;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    private const REPLAYABLE_LEDGER_TYPES = [
        'payment',
        'interest_accrual',
        'fee_accrual',
    ];

    protected $interestEngine;
    protected $lateFeeService;
    protected $legalStatusService;

    public function __construct(InterestEngine $interestEngine, LateFeeService $lateFeeService, LegalStatusService $legalStatusService)
    {
        $this->interestEngine = $interestEngine;
        $this->lateFeeService = $lateFeeService;
        $this->legalStatusService = $legalStatusService;
    }

    public function registerPayment(Loan $loan, Carbon $paidAt, float $amount, string $method, ?string $reference = null, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($loan, $paidAt, $amount, $method, $reference, $notes) {
            $paymentDate = $paidAt->copy()->startOfDay();
            $futurePayments = Payment::where('loan_id', $loan->id)
                ->whereDate('paid_at', '>', $paymentDate)
                ->orderBy('paid_at')
                ->orderBy('id')
                ->get();

            $hasFutureActivity = LoanLedgerEntry::where('loan_id', $loan->id)
                ->whereDate('occurred_at', '>', $paymentDate)
                ->whereIn('type', self::REPLAYABLE_LEDGER_TYPES)
                ->exists();

            if ($futurePayments->isNotEmpty() || $hasFutureActivity) {
                LoanLedgerEntry::where('loan_id', $loan->id)
                    ->whereDate('occurred_at', '>', $paymentDate)
                    ->whereIn('type', self::REPLAYABLE_LEDGER_TYPES)
                    ->delete();

                if ($futurePayments->isNotEmpty()) {
                    Payment::whereIn('id', $futurePayments->pluck('id'))->delete();
                }

                $this->purgeFutureAutoLegalEntries($loan->fresh(), $paymentDate);

                if ($loan->legal_status && $loan->legal_entered_at && Carbon::parse($loan->legal_entered_at)->startOfDay()->gt($paymentDate)) {
                    $loan->legal_status = false;
                    $loan->legal_entered_at = null;
                }

                $loan->last_accrual_date = $this->resolveAccrualBaselineDate($loan, $paymentDate);
                $loan->save();
                $this->recalculateLedgerBalances($loan->fresh());
            }

            $newPayment = Payment::create([
                'loan_id' => $loan->id,
                'client_id' => $loan->client_id,
                'paid_at' => $paidAt,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'applied_interest' => 0,
                'applied_principal' => 0,
                'applied_fees' => 0,
                'notes' => $notes ?? '',
            ]);

            $loan = $loan->fresh();

            $this->postAccrualsThroughDueDates($loan, $paymentDate, $newPayment->id);

            $loan = $loan->fresh();

            if (($loan->payment_accrual_mode ?? 'realtime') === 'realtime') {
                // Devengo en la fecha exacta del pago.
                $this->lateFeeService->checkAndAccrueLateFees($loan->fresh(), $paymentDate, $newPayment->id);
                $this->interestEngine->accrueUpTo($loan->fresh(), $paymentDate, $newPayment->id);
            }

            $loan = $loan->fresh();
            $remainingAmount = $amount;

            $interestOutstandingBeforePayment = round((float) ($loan->interest_accrued ?? 0), 2);
            $feeBuckets = $this->resolveFeeBucketsForPayment($loan, $paymentDate);

            $interestToPay = min($remainingAmount, $interestOutstandingBeforePayment);
            $remainingAmount -= $interestToPay;

            $lateFeesToPay = min($remainingAmount, (float) ($feeBuckets['late_fee'] ?? 0));
            $remainingAmount -= $lateFeesToPay;

            $legalEntryFeesToPay = min($remainingAmount, (float) ($feeBuckets['legal_entry_fee'] ?? 0));
            $remainingAmount -= $legalEntryFeesToPay;

            $otherLegalFeesToPay = min($remainingAmount, (float) ($feeBuckets['legal_other_fee'] ?? 0));
            $remainingAmount -= $otherLegalFeesToPay;

            $feesToPay = round($lateFeesToPay + $legalEntryFeesToPay + $otherLegalFeesToPay, 2);

            $principalToPay = min($remainingAmount, (float) $loan->principal_outstanding);

            $totalApplied = round($feesToPay + $interestToPay + $principalToPay, 2);

            LoanLedgerEntry::create([
                'loan_id' => $loan->id,
                'payment_id' => $newPayment->id,
                'type' => 'payment',
                'occurred_at' => $paidAt,
                'amount' => $totalApplied,
                'principal_delta' => -$principalToPay,
                'interest_delta' => -$interestToPay,
                'fees_delta' => -$feesToPay,
                'balance_after' => 0,
                'meta' => [
                    'method' => $method,
                    'reference' => $reference,
                    'notes' => $notes,
                    'payment_id' => $newPayment->id,
                    'payment_breakdown' => [
                        'interest' => [
                            'paid' => round($interestToPay, 2),
                            'remaining' => round(max(0, $interestOutstandingBeforePayment - $interestToPay), 2),
                        ],
                        'late_fee' => [
                            'paid' => round($lateFeesToPay, 2),
                            'remaining' => round(max(0, (float) ($feeBuckets['late_fee'] ?? 0) - $lateFeesToPay), 2),
                        ],
                        'legal_entry_fee' => [
                            'paid' => round($legalEntryFeesToPay, 2),
                            'remaining' => round(max(0, (float) ($feeBuckets['legal_entry_fee'] ?? 0) - $legalEntryFeesToPay), 2),
                        ],
                        'legal_other_fee' => [
                            'paid' => round($otherLegalFeesToPay, 2),
                            'remaining' => round(max(0, (float) ($feeBuckets['legal_other_fee'] ?? 0) - $otherLegalFeesToPay), 2),
                        ],
                    ],
                ],
            ]);

            $newPayment->update([
                'applied_interest' => $interestToPay,
                'applied_principal' => $principalToPay,
                'applied_fees' => $feesToPay,
            ]);

            $this->recalculateLedgerBalances($loan->fresh());

            if ($futurePayments->isNotEmpty()) {
                foreach ($futurePayments as $fp) {
                    $notesToReplay = $fp->notes;
                    if (!str_contains($notesToReplay, '(Reprocesado)')) {
                        $notesToReplay .= ' (Reprocesado)';
                    }

                    $this->registerPayment(
                        $loan->fresh(),
                        Carbon::parse($fp->paid_at),
                        (float) $fp->amount,
                        $fp->method,
                        $fp->reference,
                        $notesToReplay
                    );
                }
            }

            $asOfToday = now()->startOfDay();
            $this->legalStatusService->recalculateLegalEntry($loan->fresh(), $asOfToday);
            $this->postAccrualsThroughDueDates($loan->fresh(), $asOfToday);
            $this->recalculateLedgerBalances($loan->fresh());

            return $newPayment->fresh();
        });
    }

    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $loan = $payment->loan;

            $linkedEntry = LoanLedgerEntry::where('payment_id', $payment->id)->first();
            $paidAt = $linkedEntry
                ? Carbon::parse($linkedEntry->occurred_at)->startOfDay()
                : $payment->paid_at->copy()->startOfDay();

            $futurePayments = Payment::where('loan_id', $loan->id)
                ->where('paid_at', '>=', $paidAt)
                ->where('id', '!=', $payment->id)
                ->orderBy('paid_at')
                ->orderBy('id')
                ->get();

            $paymentIdsToPurge = $futurePayments->pluck('id')->push($payment->id)->values();

            LoanLedgerEntry::where('loan_id', $loan->id)
                ->where(function ($query) use ($paidAt, $paymentIdsToPurge) {
                    $query->where('occurred_at', '>', $paidAt)
                        ->orWhereIn('payment_id', $paymentIdsToPurge)
                        ->orWhereIn('triggered_by_payment_id', $paymentIdsToPurge);
                })
                ->whereIn('type', self::REPLAYABLE_LEDGER_TYPES)
                ->delete();

            $payment->delete();

            if ($futurePayments->isNotEmpty()) {
                Payment::whereIn('id', $futurePayments->pluck('id'))->delete();
            }

            $this->purgeFutureAutoLegalEntries($loan->fresh(), $paidAt);

            if ($loan->legal_status && $loan->legal_entered_at && Carbon::parse($loan->legal_entered_at)->startOfDay()->gte($paidAt)) {
                $loan->legal_status = false;
                $loan->legal_entered_at = null;
            }

            $loan->last_accrual_date = $this->resolveAccrualBaselineDate($loan, $paidAt);
            $loan->save();

            $this->recalculateLedgerBalances($loan->fresh());

            foreach ($futurePayments as $fp) {
                $this->registerPayment(
                    $loan->fresh(),
                    Carbon::parse($fp->paid_at),
                    (float) $fp->amount,
                    $fp->method,
                    $fp->reference,
                    $fp->notes
                );
            }

            $asOfToday = now()->startOfDay();
            $this->legalStatusService->recalculateLegalEntry($loan->fresh(), $asOfToday);
            $this->postAccrualsThroughDueDates($loan->fresh(), $asOfToday);
            $this->recalculateLedgerBalances($loan->fresh());
        });
    }

    public function recalculateLedgerBalances(Loan $loan): void
    {
        $loan = $loan->fresh();

        $entries = LoanLedgerEntry::where('loan_id', $loan->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $hasDisbursementEntry = $entries->contains(fn (LoanLedgerEntry $entry) => $entry->type === 'disbursement');
        $openingPrincipal = $hasDisbursementEntry ? 0.0 : (float) $loan->principal_initial;

        $runningBalance = $openingPrincipal;
        $principalDeltaSum = 0.0;
        $interestAccrued = 0.0;
        $feesAccrued = 0.0;

        foreach ($entries as $entry) {
            $principalDeltaSum += (float) $entry->principal_delta;
            $interestAccrued += (float) $entry->interest_delta;
            $feesAccrued += (float) $entry->fees_delta;
            $runningBalance = round($openingPrincipal + $principalDeltaSum + $interestAccrued + $feesAccrued, 2);

            if (round((float) $entry->balance_after, 2) !== $runningBalance) {
                $entry->balance_after = $runningBalance;
                $entry->save();
            }
        }

        $loan->principal_outstanding = round($openingPrincipal + $principalDeltaSum, 2);
        $loan->interest_accrued = round($interestAccrued, 2);
        $loan->fees_accrued = round($feesAccrued, 2);
        $loan->balance_total = round($runningBalance, 2);

        if ($loan->balance_total <= 0.01) {
            $loan->status = 'closed';
            $loan->balance_total = 0.0;
            $loan->principal_outstanding = max(0.0, $loan->principal_outstanding);
            $loan->interest_accrued = max(0.0, $loan->interest_accrued);
            $loan->fees_accrued = max(0.0, $loan->fees_accrued);
        } elseif ($loan->status === 'closed') {
            $loan->status = 'active';
        }

        $loan->save();
    }

    public function postAccrualsThroughDueDates(Loan $loan, Carbon $asOfDate, ?int $triggeredByPaymentId = null): void
    {
        $loan = $loan->fresh();

        if ($loan->status !== 'active' || $loan->consolidated_into_loan_id !== null) {
            return;
        }

        $asOfDate = $asOfDate->copy()->startOfDay();
        $lastAccrualDate = $loan->last_accrual_date
            ? Carbon::parse($loan->last_accrual_date)->startOfDay()
            : Carbon::parse($loan->start_date)->startOfDay();

        $dueDateCursor = LoanCycle::anchorDate($loan);
        LoanCycle::advanceByModality($dueDateCursor, $loan);

        while ($dueDateCursor->lte($asOfDate)) {
            if ($dueDateCursor->gt($lastAccrualDate)) {
                $this->lateFeeService->checkAndAccrueLateFees($loan->fresh(), $dueDateCursor, $triggeredByPaymentId);
                $this->interestEngine->accrueUpTo($loan->fresh(), $dueDateCursor, $triggeredByPaymentId);
            }

            LoanCycle::advanceByModality($dueDateCursor, $loan);
        }
    }

    private function resolveAccrualBaselineDate(Loan $loan, Carbon $cutoffDate): Carbon
    {
        $lastEventOnOrBeforeCutoff = LoanLedgerEntry::where('loan_id', $loan->id)
            ->whereDate('occurred_at', '<=', $cutoffDate->copy()->startOfDay())
            ->whereIn('type', ['interest_accrual', 'payment', 'disbursement'])
            ->orderBy('occurred_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastEventOnOrBeforeCutoff
            ? Carbon::parse($lastEventOnOrBeforeCutoff->occurred_at)->startOfDay()
            : Carbon::parse($loan->start_date)->startOfDay();
    }



    private function purgeFutureAutoLegalEntries(Loan $loan, Carbon $cutoffDate): void
    {
        $legalEntryIds = $loan->ledgerEntries()
            ->where('type', 'legal_fee')
            ->whereDate('occurred_at', '>=', $cutoffDate)
            ->get()
            ->filter(fn ($entry) => (string) data_get($entry->meta, 'reason') === 'legal_entry')
            ->pluck('id');

        if ($legalEntryIds->isNotEmpty()) {
            $loan->ledgerEntries()->whereIn('id', $legalEntryIds)->delete();
        }
    }




    private function resolveFeeBucketsForPayment(Loan $loan, Carbon $asOfDate): array
    {
        $entries = LoanLedgerEntry::where('loan_id', $loan->id)
            ->whereDate('occurred_at', '<=', $asOfDate)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $lateAccrued = 0.0;
        $legalEntryAccrued = 0.0;
        $legalOtherAccrued = 0.0;
        $latePaid = 0.0;
        $legalEntryPaid = 0.0;
        $legalOtherPaid = 0.0;

        foreach ($entries as $entry) {
            if ($entry->type === 'fee_accrual') {
                $lateAccrued += (float) $entry->amount;
                continue;
            }

            if ($entry->type === 'legal_fee') {
                $reason = (string) data_get($entry->meta, 'reason', '');
                if ($reason === 'legal_entry') {
                    $legalEntryAccrued += (float) $entry->amount;
                } else {
                    $legalOtherAccrued += (float) $entry->amount;
                }
                continue;
            }

            if ($entry->type === 'payment') {
                $breakdown = data_get($entry->meta, 'payment_breakdown', []);
                $latePaid += (float) data_get($breakdown, 'late_fee.paid', 0);
                $legalEntryPaid += (float) data_get($breakdown, 'legal_entry_fee.paid', 0);
                $legalOtherPaid += (float) data_get($breakdown, 'legal_other_fee.paid', 0);
            }
        }

        $lateOutstanding = max(0, round($lateAccrued - $latePaid, 2));
        $legalEntryOutstanding = max(0, round($legalEntryAccrued - $legalEntryPaid, 2));
        $legalOtherOutstanding = max(0, round($legalOtherAccrued - $legalOtherPaid, 2));

        return [
            'late_fee' => $lateOutstanding,
            'legal_entry_fee' => $legalEntryOutstanding,
            'legal_other_fee' => $legalOtherOutstanding,
        ];
    }

}

