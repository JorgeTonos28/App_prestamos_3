<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use App\Models\Payment;
use Carbon\Carbon;
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
                ->where(function ($query) use ($paymentDate) {
                    $query->whereDate('occurred_at', '>', $paymentDate)
                        ->orWhere(function ($sameDay) use ($paymentDate) {
                            $sameDay->whereDate('occurred_at', '=', $paymentDate)
                                ->whereIn('type', ['interest_accrual', 'fee_accrual']);
                        });
                })
                ->whereIn('type', self::REPLAYABLE_LEDGER_TYPES)
                ->exists();

            if ($futurePayments->isNotEmpty() || $hasFutureActivity) {
                LoanLedgerEntry::where('loan_id', $loan->id)
                    ->where(function ($query) use ($paymentDate) {
                        $query->whereDate('occurred_at', '>', $paymentDate)
                            ->orWhere(function ($sameDay) use ($paymentDate) {
                                $sameDay->whereDate('occurred_at', '=', $paymentDate)
                                    ->whereIn('type', ['interest_accrual', 'fee_accrual']);
                            });
                    })
                    ->whereIn('type', self::REPLAYABLE_LEDGER_TYPES)
                    ->delete();

                if ($futurePayments->isNotEmpty()) {
                    Payment::whereIn('id', $futurePayments->pluck('id'))->delete();
                }

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

            $this->lateFeeService->checkAndAccrueLateFees($loan, $paymentDate, $newPayment->id);
            $this->interestEngine->accrueUpTo($loan->fresh(), $paymentDate, $newPayment->id);

            $loan = $loan->fresh();

            $remainingAmount = $amount;

            $interestToPay = min($remainingAmount, (float) ($loan->interest_accrued ?? 0));
            $remainingAmount -= $interestToPay;

            $feesToPay = min($remainingAmount, (float) ($loan->fees_accrued ?? 0));
            $remainingAmount -= $feesToPay;

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

            $this->legalStatusService->moveToLegalIfNeeded($loan->fresh(), now());
            $this->legalStatusService->ensureLegalEntryFeeExists($loan->fresh(), now());
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

            LoanLedgerEntry::where('loan_id', $loan->id)
                ->where(function ($query) use ($paidAt, $payment, $futurePayments) {
                    $query->where('occurred_at', '>=', $paidAt)
                        ->orWhere('payment_id', $payment->id)
                        ->orWhere('triggered_by_payment_id', $payment->id);

                    if ($futurePayments->isNotEmpty()) {
                        $query->orWhereIn('payment_id', $futurePayments->pluck('id'))
                            ->orWhereIn('triggered_by_payment_id', $futurePayments->pluck('id'));
                    }
                })
                ->whereIn('type', self::REPLAYABLE_LEDGER_TYPES)
                ->delete();

            $payment->delete();

            if ($futurePayments->isNotEmpty()) {
                Payment::whereIn('id', $futurePayments->pluck('id'))->delete();
            }

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

            if ($loan->fresh()->status === 'active') {
                $today = now()->startOfDay();
                $this->lateFeeService->checkAndAccrueLateFees($loan->fresh(), $today);
                $this->interestEngine->accrueUpTo($loan->fresh(), $today);
            }

            $this->legalStatusService->moveToLegalIfNeeded($loan->fresh(), now());
            $this->legalStatusService->ensureLegalEntryFeeExists($loan->fresh(), now());
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
}
