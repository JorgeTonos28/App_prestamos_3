<?php

namespace App\Helpers;

use Carbon\Carbon;

class FinancialHelper
{
    /**
     * Calculate difference in days using 30/360 convention (US Method).
     * Only applies if convention is enabled, otherwise returns standard diff.
     */
    public static function diffInDays(Carbon $start, Carbon $end, int $convention = 30): int
    {
        if ($convention !== 30) {
            return $start->diffInDays($end);
        }

        // 30/360 Logic
        $d1 = $start->day;
        $m1 = $start->month;
        $y1 = $start->year;

        $d2 = $end->day;
        $m2 = $end->month;
        $y2 = $end->year;

        if ($d1 == 31) $d1 = 30;
        if ($d2 == 31 && $d1 == 30) $d2 = 30; // US Method 30/360

        return 360 * ($y2 - $y1) + 30 * ($m2 - $m1) + ($d2 - $d1);
    }
}
