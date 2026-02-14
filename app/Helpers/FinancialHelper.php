<?php

namespace App\Helpers;

use Carbon\Carbon;

class FinancialHelper
{
    /**
     * Calculate day difference using real calendar days.
     *
     * NOTE: Convention parameter is kept for backward compatibility with callers,
     * but business rules now require real-day counting to avoid 30/360 confusion.
     */
    public static function diffInDays(Carbon $start, Carbon $end, int $convention = 30): int
    {
        return $start->diffInDays($end);
    }
}
