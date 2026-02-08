<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class LateFeeService
{
    public function accrueForPayment(Loan $loan, Carbon $paidAt): array
    {
        if ($loan->status !== 'active' || !$loan->enable_late_fees) {
            return ['days' => 0, 'amount' => 0.0];
        }

        if (!$loan->installment_amount || $loan->installment_amount <= 0) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $dailyLateFee = $loan->late_fee_daily_amount ?? $this->getGlobalLateFeeDailyAmount();
        if ($dailyLateFee <= 0) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $paidAt = $paidAt->copy()->startOfDay();

        $dueDates = $this->generateDueDates($loan, $paidAt);
        if (empty($dueDates)) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $totalPaid = (float) $loan->ledgerEntries()
            ->where('type', 'payment')
            ->where('occurred_at', '<', $paidAt)
            ->sum('amount');

        $coveredInstallments = (int) floor($totalPaid / $loan->installment_amount);
        if (!isset($dueDates[$coveredInstallments])) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $firstUnpaidDate = $dueDates[$coveredInstallments];
        $gracePeriod = $loan->late_fee_grace_period ?? $this->getGlobalLateFeeGracePeriod();
        $businessDaysLate = $firstUnpaidDate->diffInWeekdays($paidAt);
        $totalChargeableDays = max(0, $businessDaysLate - $gracePeriod);

        if ($totalChargeableDays === 0) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $alreadyAccruedDays = (int) $loan->ledgerEntries()
            ->where('type', 'fee_accrual')
            ->where('occurred_at', '<=', $paidAt)
            ->get()
            ->sum(function ($entry) {
                $meta = $entry->meta ?? [];
                return (int) ($meta['late_fee_days'] ?? 0);
            });

        $newDays = max(0, $totalChargeableDays - $alreadyAccruedDays);
        if ($newDays === 0) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $totalFees = $newDays * $dailyLateFee;
        $totalFees = round($totalFees, 2);
        $newBalance = (float) $loan->balance_total + $totalFees;

        $loan->ledgerEntries()->create([
            'type' => 'fee_accrual',
            'occurred_at' => $paidAt,
            'amount' => $totalFees,
            'principal_delta' => 0,
            'interest_delta' => 0,
            'fees_delta' => $totalFees,
            'balance_after' => $newBalance,
            'meta' => [
                'late_fee_days' => $newDays,
                'daily_amount' => $dailyLateFee,
                'as_of' => $paidAt->toDateString(),
            ],
        ]);

        $loan->fees_accrued = (float) $loan->fees_accrued + $totalFees;
        $loan->balance_total = $newBalance;
        $loan->save();

        return ['days' => $newDays, 'amount' => $totalFees];
    }

    private function generateDueDates(Loan $loan, Carbon $targetDate): array
    {
        $dueDates = [];
        $currentDate = $loan->start_date->copy();

        $this->advanceDate($currentDate, $loan->modality);

        while ($currentDate->lt($targetDate)) {
            $dueDates[] = $currentDate->copy();
            $this->advanceDate($currentDate, $loan->modality);
        }

        return $dueDates;
    }

    private function advanceDate(Carbon $date, string $modality): void
    {
        match ($modality) {
            'daily' => $date->addDay(),
            'weekly' => $date->addWeek(),
            'biweekly' => $date->addWeeks(2),
            'monthly' => $date->addMonth(),
        };
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
