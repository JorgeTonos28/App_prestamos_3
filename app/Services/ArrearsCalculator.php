<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Setting;
use App\Support\LoanDelinquencySnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ArrearsCalculator
{
    /**
     * Calculate arrears status for a loan.
     */
    public function calculate(Loan $loan, ?Carbon $asOf = null): array
    {
        $now = ($asOf ? $asOf->copy() : Carbon::now())->startOfDay();

        $snapshot = LoanDelinquencySnapshot::build($loan, $now);
        $arrearsAmount = (float) ($snapshot['amount'] ?? 0);
        $firstUnpaidDateString = $snapshot['first_unpaid_date'] ?? null;

        $daysOverdue = $firstUnpaidDateString
            ? Carbon::parse($firstUnpaidDateString)->startOfDay()->diffInDays($now)
            : 0;

        $lateFeeAmount = 0.0;
        $lateFeeDaysChargeable = 0;
        if ($loan->enable_late_fees && $arrearsAmount > 0) {
            $lateFeeState = app(LateFeeService::class)->calculatePendingLateFees($loan, $now);
            $lateFeeDaysChargeable = (int) ($lateFeeState['total_days'] ?? 0);

            $dailyLateFee = $loan->late_fee_daily_amount ?? $this->getGlobalLateFeeDailyAmount();
            $lateFeeAmount = round($lateFeeDaysChargeable * $dailyLateFee, 2);
        }

        return [
            'count' => (float) ($snapshot['count'] ?? 0),
            'amount' => $arrearsAmount,
            'days' => $daysOverdue,
            'late_fee_days' => $lateFeeDaysChargeable,
            'late_fees_due' => $lateFeeAmount,
            'total_due' => $arrearsAmount + $lateFeeAmount,
            'expected_to_date' => (float) ($snapshot['expected_to_date'] ?? 0),
            'paid_to_date' => (float) ($snapshot['paid_to_date'] ?? 0),
            'paid_gross_to_date' => (float) ($snapshot['paid_gross_to_date'] ?? 0),
            'first_unpaid_date' => $firstUnpaidDateString,
            'details' => $snapshot['details'] ?? [],
        ];
    }

    private function getGlobalLateFeeDailyAmount(): float
    {
        if (!Schema::hasTable('settings')) {
            return 0.0;
        }

        $value = Setting::where('key', 'global_late_fee_daily_amount')->value('value');

        return $value !== null ? (float) $value : 0.0;
    }
}
