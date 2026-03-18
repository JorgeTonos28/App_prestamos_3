<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Setting;
use Carbon\Carbon;
use App\Support\LoanCycle;
use Illuminate\Support\Facades\Schema;

class ArrearsCalculator
{
    /**
     * Calculate arrears status for a loan.
     */
    public function calculate(Loan $loan, ?Carbon $asOf = null): array
    {
        $startDate = $loan->start_date;
        $installmentAmount = $loan->installment_amount;

        if (!$installmentAmount || $installmentAmount <= 0) {
            return [
                'count' => 0,
                'amount' => 0,
                'days' => 0,
                'late_fee_days' => 0,
                'late_fees_due' => 0,
                'total_due' => 0,
                'details' => [],
            ];
        }

        $now = ($asOf ? $asOf->copy() : Carbon::now())->startOfDay();

        $dueDates = [];
        $currentDate = (($loan->late_fee_cutoff_mode ?? 'dynamic_payment') === 'fixed_cutoff')
            ? LoanCycle::anchorDate($loan)
            : $startDate->copy()->startOfDay();

        LoanCycle::advanceByModality($currentDate, $loan);

        while ($currentDate->lt($now)) {
            $dueDates[] = $currentDate->copy();
            LoanCycle::advanceByModality($currentDate, $loan);
        }

        if (empty($dueDates)) {
            return [
                'count' => 0,
                'amount' => 0,
                'days' => 0,
                'late_fee_days' => 0,
                'late_fees_due' => 0,
                'total_due' => 0,
                'details' => [],
            ];
        }

        $totalExpected = count($dueDates) * $installmentAmount;

        if ($loan->relationLoaded('ledgerEntries')) {
            $payments = $loan->ledgerEntries
                ->filter(fn ($entry) => $entry->type === 'payment' && Carbon::parse($entry->occurred_at)->startOfDay()->lte($now));
        } else {
            $payments = $loan->ledgerEntries()
                ->where('type', 'payment')
                ->whereDate('occurred_at', '<=', $now)
                ->get(['amount', 'principal_delta', 'interest_delta', 'fees_delta']);
        }

        $grossPaidToDate = round((float) $payments->sum('amount'), 2);
        $totalPaid = $grossPaidToDate;

        $arrearsAmount = max(0, round($totalExpected - $totalPaid, 2));
        $arrearsCount = $arrearsAmount / $installmentAmount;

        $coveredInstallments = (int) floor($totalPaid / $installmentAmount);
        $firstUnpaidIndex = $coveredInstallments;

        $daysOverdue = 0;
        $lateFeeAmount = 0.0;
        $lateFeeDaysChargeable = 0;
        $firstUnpaidDateString = null;
        if (isset($dueDates[$firstUnpaidIndex])) {
            $firstUnpaidDate = $dueDates[$firstUnpaidIndex];
            $firstUnpaidDateString = $firstUnpaidDate->toDateString();
            $daysOverdue = $firstUnpaidDate->diffInDays($now);

            if ($loan->enable_late_fees && $arrearsAmount > 0) {
                $lateFeeState = app(LateFeeService::class)->calculatePendingLateFees($loan, $now);
                $lateFeeDaysChargeable = (int) ($lateFeeState['total_days'] ?? 0);

                $dailyLateFee = $loan->late_fee_daily_amount ?? $this->getGlobalLateFeeDailyAmount();
                $lateFeeAmount = round($lateFeeDaysChargeable * $dailyLateFee, 2);
            }
        }

        return [
            'count' => round($arrearsCount, 1),
            'amount' => $arrearsAmount,
            'days' => $daysOverdue,
            'late_fee_days' => $lateFeeDaysChargeable,
            'late_fees_due' => $lateFeeAmount,
            'total_due' => $arrearsAmount + $lateFeeAmount,
            'expected_to_date' => $totalExpected,
            'paid_to_date' => $totalPaid,
            'paid_gross_to_date' => $grossPaidToDate,
            'first_unpaid_date' => $firstUnpaidDateString,
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

    private function getGlobalLateFeeGracePeriod(): int
    {
        if (!Schema::hasTable('settings')) {
            return 3;
        }

        $value = Setting::where('key', 'global_late_fee_grace_period')->value('value');

        if ($value === null) {
            return 3;
        }

        return max(0, (int) $value);
    }
}