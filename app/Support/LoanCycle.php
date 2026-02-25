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

    public static function advanceByModality(Carbon $date, Loan $loan): void
    {
        match ($loan->modality) {
            'daily' => $date->addDay(),
            'weekly' => $date->addDays((int) ($loan->days_in_period_weekly ?: 7)),
            'biweekly' => $date->addDays((int) ($loan->days_in_period_biweekly ?: 15)),
            'monthly' => $date->addMonth(),
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
}
