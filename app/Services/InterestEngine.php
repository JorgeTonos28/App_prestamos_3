<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use Carbon\Carbon;
use App\Helpers\FinancialHelper;
use App\Support\LoanCycle;

class InterestEngine
{
    /**
     * Calculate daily rate.
     * Rule: daily_rate = (monthly_rate / 100) / days_in_month_convention
     */
    public function dailyRate(Loan $loan): float
    {
        $convention = $loan->days_in_month_convention ?: 30;
        return ($loan->monthly_rate / 100) / $convention;
    }

    /**
     * Accrue interest up to a target date.
     * Only accrues if loan is active and targetDate > last_accrual_date
     * This method is idempotent for a given date.
     */
    public function accrueUpTo(Loan $loan, Carbon $targetDate, ?int $triggeredByPaymentId = null, bool $isCutoffCalculation = false): void
    {
        if ($loan->status !== 'active' || $loan->consolidated_into_loan_id !== null) {
            return;
        }

        // Normalize targetDate to start of day to avoid time components causing issues
        $targetDate = $targetDate->copy()->startOfDay();

        // Get last date (start of day)
        $lastDate = $loan->last_accrual_date
            ? Carbon::parse($loan->last_accrual_date)->startOfDay()
            : Carbon::parse($loan->start_date)->startOfDay();

        // If target is same or before last date, do nothing.
        if ($targetDate->lte($lastDate)) {
            return;
        }

        // Use 30/360 helper if convention is 30, otherwise standard diff
        $convention = $loan->days_in_month_convention ?: 30;
        $daysToAccrue = FinancialHelper::diffInDays($lastDate, $targetDate, $convention);

        if ($daysToAccrue <= 0) {
            return;
        }

        $dailyRate = $this->dailyRate($loan);

        $snapshotAtTargetDate = $this->snapshotAtDate($loan, $targetDate);

        // Base calculation at historical cutoff (not current loan cache).
        if ($loan->interest_mode === 'simple') {
            $base = (float) $loan->principal_initial;
        } elseif ($this->isInArrearsAtDate($loan, $targetDate)) {
            $base = (float) $snapshotAtTargetDate['balance_total'];
        } else {
            $base = $loan->interest_base === 'total_balance'
                ? (float) $snapshotAtTargetDate['balance_total']
                : (float) $snapshotAtTargetDate['principal_outstanding'];
        }

        // Interest for this period
        $interest = $base * $dailyRate * $daysToAccrue;

        // Rounding (standard 2 decimals)
        $interest = round($interest, 2);

        if ($interest > 0) {
            // Create Ledger Entry
            LoanLedgerEntry::create([
                'loan_id' => $loan->id,
                'triggered_by_payment_id' => $triggeredByPaymentId,
                'type' => 'interest_accrual',
                'occurred_at' => $targetDate, // Record as of the target date (midnight)
                'amount' => $interest,
                'principal_delta' => 0,
                'interest_delta' => $interest,
                'fees_delta' => 0,
                'balance_after' => (float) $snapshotAtTargetDate['balance_total'] + $interest,
                'meta' => [
                    'days' => $daysToAccrue,
                    'from' => $lastDate->toDateString(),
                    'to' => $targetDate->toDateString(),
                    'daily_rate' => $dailyRate,
                    'base_amount' => $base,
                    'accrual_context' => $isCutoffCalculation ? 'cutoff' : 'payment'
                ]
            ]);

            // Update Loan cache from historical snapshot to avoid contamination from future entries.
            $loan->interest_accrued = round((float) $snapshotAtTargetDate['interest_accrued'] + $interest, 2);
            $loan->balance_total = round((float) $snapshotAtTargetDate['balance_total'] + $interest, 2);
        }

        // Always update the last accrual date, even if interest was 0
        $loan->last_accrual_date = $targetDate;
        $loan->save();
    }

    /**
     * Calculate accrued interest up to a target date without modifying the database.
     * Returns the calculated interest amount.
     */
    public function calculatePendingInterest(Loan $loan, Carbon $targetDate): float
    {
        if ($loan->status !== 'active' || $loan->consolidated_into_loan_id !== null) {
            return 0.0;
        }

        $targetDate = $targetDate->copy()->startOfDay();

        $lastDate = $loan->last_accrual_date
            ? Carbon::parse($loan->last_accrual_date)->startOfDay()
            : Carbon::parse($loan->start_date)->startOfDay();

        if ($targetDate->lte($lastDate)) {
            return 0.0;
        }

        $convention = $loan->days_in_month_convention ?: 30;
        $daysToAccrue = FinancialHelper::diffInDays($lastDate, $targetDate, $convention);

        if ($daysToAccrue <= 0) {
            return 0.0;
        }

        $dailyRate = $this->dailyRate($loan);
        $snapshotAtTargetDate = $this->snapshotAtDate($loan, $targetDate);

        if ($loan->interest_mode === 'simple') {
            $base = (float) $loan->principal_initial;
        } elseif ($this->isInArrearsAtDate($loan, $targetDate)) {
            $base = (float) $snapshotAtTargetDate['balance_total'];
        } else {
            $base = $loan->interest_base === 'total_balance'
                ? (float) $snapshotAtTargetDate['balance_total']
                : (float) $snapshotAtTargetDate['principal_outstanding'];
        }

        $interest = $base * $dailyRate * $daysToAccrue;

        return round($interest, 2);
    }

    private function snapshotAtDate(Loan $loan, Carbon $asOfDate): array
    {
        $entries = $loan->ledgerEntries()
            ->whereDate('occurred_at', '<=', $asOfDate->copy()->startOfDay())
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['type', 'principal_delta', 'interest_delta', 'fees_delta']);

        $hasDisbursementEntry = $entries->contains(fn ($entry) => $entry->type === 'disbursement');
        $openingPrincipal = $hasDisbursementEntry ? 0.0 : (float) $loan->principal_initial;

        $principalDeltaSum = (float) $entries->sum('principal_delta');
        $interestDeltaSum = (float) $entries->sum('interest_delta');
        $feesDeltaSum = (float) $entries->sum('fees_delta');

        $principalOutstanding = round($openingPrincipal + $principalDeltaSum, 2);
        $interestAccrued = round($interestDeltaSum, 2);
        $feesAccrued = round($feesDeltaSum, 2);

        return [
            'principal_outstanding' => $principalOutstanding,
            'interest_accrued' => $interestAccrued,
            'fees_accrued' => $feesAccrued,
            'balance_total' => round($principalOutstanding + $interestAccrued + $feesAccrued, 2),
        ];
    }

    private function isInArrearsAtDate(Loan $loan, Carbon $asOfDate): bool
    {
        $installmentAmount = (float) ($loan->installment_amount ?? 0);
        if ($installmentAmount <= 0) {
            return false;
        }

        $dueCount = 0;
        $cursor = LoanCycle::anchorDate($loan);
        LoanCycle::advanceByModality($cursor, $loan);

        while ($cursor->lte($asOfDate)) {
            $dueCount++;
            LoanCycle::advanceByModality($cursor, $loan);
        }

        if ($dueCount === 0) {
            return false;
        }

        $expected = $dueCount * $installmentAmount;
        $paid = (float) $loan->ledgerEntries()
            ->where('type', 'payment')
            ->whereDate('occurred_at', '<=', $asOfDate)
            ->sum('amount');

        return $paid + 0.0001 < $expected;
    }
}
