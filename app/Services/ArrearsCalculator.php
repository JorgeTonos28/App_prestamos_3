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

        // If loan has no strict installment amount, we can't calculate arrears by installments
        if (!$installmentAmount || $installmentAmount <= 0) {
            return [
                'count' => 0,
                'amount' => 0,
                'days' => 0,
                'late_fee_days' => 0,
                'late_fees_due' => 0,
                'total_due' => 0,
                'details' => []
            ];
        }

        $modality = $loan->modality;
        $now = ($asOf ? $asOf->copy() : Carbon::now())->startOfDay();

        // 1. Generate Due Dates from start until now (strict past dates only)
        // If a payment is due TODAY, it is not yet in arrears until tomorrow.
        $dueDates = [];
        $currentDate = (($loan->late_fee_cutoff_mode ?? 'dynamic_payment') === 'fixed_cutoff')
            ? LoanCycle::anchorDate($loan)
            : $startDate->copy()->startOfDay();

        // Move to first due date
        LoanCycle::advanceByModality($currentDate, $loan);

        // Comparison uses startOfDay, so if currentDate is today (00:00) and now is today (00:00), lt is false.
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
                'details' => []
            ];
        }

        // 2. Calculate Total Expected vs Total Paid
        $totalExpected = count($dueDates) * $installmentAmount;

        // Use total_paid from ledger (payments only)
        // Optimization: Loan model could cache this, but for now we query relationships or use ledger
        // Assuming loan->payments relation is loaded or we query it.
        // Or better, use ledger entries of type payment.
        // For efficiency, let's assume Loan model has `principal_initial` and `balance_total`.
        // Total Paid = (Principal Initial + Interest Accrued + Fees) - Balance Total ??
        // No, that's complex because interest accrues daily.

        // Simpler: Sum of all payment transactions.
        if ($loan->relationLoaded('ledgerEntries')) {
            $payments = $loan->ledgerEntries
                ->filter(fn ($entry) => $entry->type === 'payment' && Carbon::parse($entry->occurred_at)->startOfDay()->lte($now));
        } else {
            $payments = $loan->ledgerEntries()
                ->where('type', 'payment')
                ->whereDate('occurred_at', '<=', $now)
                ->get(['amount']);
        }

        $grossPaidToDate = round((float) $payments->sum('amount'), 2);
        $totalPaid = $grossPaidToDate;

        // 3. Arrears
        $arrearsAmount = max(0, $totalExpected - $totalPaid);

        // Count = Amount / Installment. Round down because partial payment doesn't clear a full installment usually,
        // but for "count" usually we want number of FULLY missed or partially missed?
        // User asked: "Si tiene 3 meses sin pagar... debe 3 meses".
        // If arrears is 3.5 installments, it's 3.5 months overdue.
        // Let's return float for precision, but show Integer in UI if clean.
        $arrearsCount = $arrearsAmount / $installmentAmount;

        // Days overdue? From the first unpaid due date.
        // If totalPaid covers first X due dates, find the X+1 date.
        $coveredInstallments = floor($totalPaid / $installmentAmount);
        $firstUnpaidIndex = (int) $coveredInstallments;

        $daysOverdue = 0;
        $businessDaysLate = 0;
        $lateFeeAmount = 0.0;
        $lateFeeDaysChargeable = 0;
        $firstUnpaidDateString = null;
        if (isset($dueDates[$firstUnpaidIndex])) {
            $firstUnpaidDate = $dueDates[$firstUnpaidIndex];
            $firstUnpaidDateString = $firstUnpaidDate->toDateString();
            $daysOverdue = $firstUnpaidDate->diffInDays($now);

            if ($loan->enable_late_fees && $arrearsAmount > 0) {
                $triggerType = $loan->late_fee_trigger_type ?? 'days';
                $triggerValue = max(0, (int) ($loan->late_fee_trigger_value ?? $loan->late_fee_grace_period ?? $this->getGlobalLateFeeGracePeriod()));
                $dayType = $loan->late_fee_day_type ?? 'business';
                $gracePeriod = max(0, (int) ($loan->late_fee_grace_period ?? $this->getGlobalLateFeeGracePeriod()));

                if ($triggerType === 'installments') {
                    $overdueInstallments = max(0, count($dueDates) - $firstUnpaidIndex);
                    if ($overdueInstallments > $triggerValue) {
                        $triggerIndex = min(count($dueDates) - 1, $firstUnpaidIndex + max(0, $triggerValue - 1));
                        $triggerDate = $dueDates[$triggerIndex]->copy()->startOfDay();
                        $rawLateDays = $dayType === 'business'
                            ? $triggerDate->diffInWeekdays($now)
                            : $triggerDate->diffInDays($now);
                        $lateFeeDaysChargeable = max(0, $rawLateDays - $gracePeriod);
                    }
                } else {
                    $rawLateDays = $dayType === 'business'
                        ? $firstUnpaidDate->diffInWeekdays($now)
                        : $firstUnpaidDate->diffInDays($now);
                    $lateFeeDaysChargeable = max(0, $rawLateDays - $triggerValue);
                }

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

