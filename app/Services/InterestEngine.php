<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use Carbon\Carbon;
use App\Helpers\FinancialHelper;

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
    public function accrueUpTo(Loan $loan, Carbon $targetDate): void
    {
        if ($loan->status !== 'active') {
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

        // Base calculation
        // Simple: always use original principal.
        // Compound: use outstanding principal by default, optionally total balance if configured.
        if ($loan->interest_mode === 'simple') {
            $base = $loan->principal_initial;
        } else {
            $base = $loan->interest_base === 'total_balance' ? $loan->balance_total : $loan->principal_outstanding;
        }

        // Interest for this period
        $interest = $base * $dailyRate * $daysToAccrue;

        // Rounding (standard 2 decimals)
        $interest = round($interest, 2);

        if ($interest > 0) {
            // Create Ledger Entry
            LoanLedgerEntry::create([
                'loan_id' => $loan->id,
                'type' => 'interest_accrual',
                'occurred_at' => $targetDate, // Record as of the target date (midnight)
                'amount' => $interest,
                'principal_delta' => 0,
                'interest_delta' => $interest,
                'fees_delta' => 0,
                'balance_after' => $loan->balance_total + $interest,
                'meta' => [
                    'days' => $daysToAccrue,
                    'from' => $lastDate->toDateString(),
                    'to' => $targetDate->toDateString(),
                    'daily_rate' => $dailyRate,
                    'base_amount' => $base
                ]
            ]);

            // Update Loan Cache
            $loan->interest_accrued += $interest;
            $loan->balance_total += $interest;
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
        if ($loan->status !== 'active') {
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
        if ($loan->interest_mode === 'simple') {
            $base = $loan->principal_initial;
        } else {
            $base = $loan->interest_base === 'total_balance' ? $loan->balance_total : $loan->principal_outstanding;
        }

        $interest = $base * $dailyRate * $daysToAccrue;

        return round($interest, 2);
    }
}
