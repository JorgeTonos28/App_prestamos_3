<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class LateFeeService
{
    public function accrueUpTo(Loan $loan, Carbon $targetDate): void
    {
        if ($loan->status !== 'active' || !$loan->enable_late_fees) {
            return;
        }

        if (!$loan->installment_amount || $loan->installment_amount <= 0) {
            return;
        }

        $dailyLateFee = $loan->late_fee_daily_amount ?? $this->getGlobalLateFeeDailyAmount();
        if ($dailyLateFee <= 0) {
            return;
        }

        $targetDate = $targetDate->copy()->startOfDay();

        $dueDates = $this->generateDueDates($loan, $targetDate);
        if (empty($dueDates)) {
            return;
        }

        $totalPaid = (float) $loan->ledgerEntries()
            ->where('type', 'payment')
            ->sum('amount');

        $coveredInstallments = (int) floor($totalPaid / $loan->installment_amount);
        if (!isset($dueDates[$coveredInstallments])) {
            return;
        }

        $firstUnpaidDate = $dueDates[$coveredInstallments];
        $gracePeriod = $loan->late_fee_grace_period ?? $this->getGlobalLateFeeGracePeriod();
        $firstFeeDate = $firstUnpaidDate->copy()->addWeekdays($gracePeriod + 1);

        if ($firstFeeDate->gt($targetDate)) {
            return;
        }

        $lastFeeDate = $loan->ledgerEntries()
            ->where('type', 'fee_accrual')
            ->latest('occurred_at')
            ->value('occurred_at');

        $startDate = $firstFeeDate->copy();
        if ($lastFeeDate) {
            $startDate = Carbon::parse($lastFeeDate)->startOfDay()->addWeekday();
            if ($startDate->lt($firstFeeDate)) {
                $startDate = $firstFeeDate->copy();
            }
        }

        if ($startDate->gt($targetDate)) {
            return;
        }

        $totalFees = 0.0;
        $currentBalance = (float) $loan->balance_total;

        for ($date = $startDate->copy(); $date->lte($targetDate); $date->addDay()) {
            if (!$date->isWeekday()) {
                continue;
            }

            $totalFees += $dailyLateFee;
            $currentBalance += $dailyLateFee;

            $loan->ledgerEntries()->create([
                'type' => 'fee_accrual',
                'occurred_at' => $date->copy(),
                'amount' => $dailyLateFee,
                'principal_delta' => 0,
                'interest_delta' => 0,
                'fees_delta' => $dailyLateFee,
                'balance_after' => $currentBalance,
                'meta' => [
                    'late_fee_date' => $date->toDateString(),
                    'daily_amount' => $dailyLateFee,
                ],
            ]);
        }

        if ($totalFees > 0) {
            $loan->fees_accrued = (float) $loan->fees_accrued + $totalFees;
            $loan->balance_total = $currentBalance;
            $loan->save();
        }
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
