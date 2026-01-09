<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use Carbon\Carbon;

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
     */
    public function accrueUpTo(Loan $loan, Carbon $targetDate): void
    {
        if ($loan->status !== 'active') {
            return;
        }

        $lastDate = $loan->last_accrual_date ? Carbon::parse($loan->last_accrual_date) : Carbon::parse($loan->start_date);

        // Ensure we don't accrue into the future if today < targetDate, unless specifically intended (backdating logic)
        // But usually we just accrue days that passed.
        // If targetDate is <= lastDate, nothing to do.
        if ($targetDate->lte($lastDate)) {
            return;
        }

        $daysToAccrue = $lastDate->diffInDays($targetDate);
        if ($daysToAccrue <= 0) {
            return;
        }

        $dailyRate = $this->dailyRate($loan);

        // Base calculation
        $base = $loan->interest_base === 'total_balance' ? $loan->balance_total : $loan->principal_outstanding;

        // Interest for this period
        $interest = $base * $dailyRate * $daysToAccrue;

        // Rounding (standard 2 decimals)
        $interest = round($interest, 2);

        if ($interest > 0) {
            // Create Ledger Entry
            LoanLedgerEntry::create([
                'loan_id' => $loan->id,
                'type' => 'interest_accrual',
                'occurred_at' => $targetDate, // Or now? Usually the end of the period being accrued.
                'amount' => $interest,
                'principal_delta' => 0,
                'interest_delta' => $interest,
                'fees_delta' => 0,
                'balance_after' => $loan->balance_total + $interest,
                'meta' => [
                    'days' => $daysToAccrue,
                    'from' => $lastDate->toDateString(),
                    'to' => $targetDate->toDateString(),
                    'daily_rate' => $dailyRate
                ]
            ]);

            // Update Loan Cache
            $loan->interest_accrued += $interest;
            $loan->balance_total += $interest;
        }

        $loan->last_accrual_date = $targetDate;
        $loan->save();
    }
}
