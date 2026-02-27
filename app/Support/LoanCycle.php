<?php

namespace App\Support;

use App\Models\Loan;
use Carbon\Carbon;

class LoanCycle
{
    public static function anchorDate(Loan $loan): Carbon
    {
        $anchor = $loan->cutoff_anchor_date ?: $loan->start_date;

        return Carbon::parse($anchor)->startOfDay();
    }

    public static function usesFixedCutoffDates(Loan $loan): bool
    {
        return in_array($loan->modality, ['biweekly', 'monthly'], true)
            && (($loan->cutoff_cycle_mode ?? 'calendar') === 'fixed_dates');
    }

    public static function advanceByModality(Carbon $date, Loan $loan): void
    {
        match ($loan->modality) {
            'daily' => $date->addDay(),
            'weekly' => $date->addDays((int) ($loan->days_in_period_weekly ?: 7)),
            'biweekly' => self::advanceBiweekly($date, $loan),
            'monthly' => self::advanceMonthly($date, $loan),
            default => $date->addMonth(),
        };
    }

    public static function nextCutoffDate(Loan $loan, Carbon $fromDate): Carbon
    {
        $cursor = self::anchorDate($loan);

        while ($cursor->lte($fromDate->copy()->startOfDay())) {
            self::advanceByModality($cursor, $loan);
        }

        return $cursor;
    }

    private static function advanceBiweekly(Carbon $date, Loan $loan): void
    {
        if (!self::usesFixedCutoffDates($loan)) {
            $date->addDays((int) ($loan->days_in_period_biweekly ?: 15));
            return;
        }

        $endDay = min(30, (int) $date->copy()->endOfMonth()->day);

        if ($date->day < 15) {
            $date->day(15);
            return;
        }

        if ($date->day < $endDay) {
            $date->day($endDay);
            return;
        }

        $date->addMonthNoOverflow()->day(15);
    }

    private static function advanceMonthly(Carbon $date, Loan $loan): void
    {
        if (self::usesFixedCutoffDates($loan)) {
            $anchorDay = min(30, (int) self::anchorDate($loan)->day);
            $date->addMonthNoOverflow();
            $day = min($anchorDay, (int) $date->copy()->endOfMonth()->day);
            $date->day($day);
            return;
        }

        if (($loan->month_day_count_mode ?? 'exact') === 'thirty') {
            $date->addDays(30);
            return;
        }

        $date->addMonth();
    }
}
