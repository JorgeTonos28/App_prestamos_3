<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Setting;
use App\Support\LoanCycle;
use App\Support\LoanPaymentCoverage;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LateFeeService
{
    public function accrueForPayment(Loan $loan, Carbon $paidAt, ?int $triggeredByPaymentId = null): array
    {
        return $this->checkAndAccrueLateFees($loan, $paidAt, $triggeredByPaymentId);
    }

    public function checkAndAccrueLateFees(Loan $loan, Carbon $date, ?int $triggeredByPaymentId = null, bool $isCutoffCalculation = false): array
    {
        $pending = $this->calculatePendingLateFees($loan, $date);

        if (($pending['days'] ?? 0) === 0 || (float) ($pending['amount'] ?? 0) <= 0) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $date = $date->copy()->startOfDay();
        $newDays = (int) $pending['days'];
        $totalFees = (float) $pending['amount'];
        $newBalance = (float) $loan->balance_total + $totalFees;

        $loan->ledgerEntries()->create([
            'triggered_by_payment_id' => $triggeredByPaymentId,
            'type' => 'fee_accrual',
            'occurred_at' => $date,
            'amount' => $totalFees,
            'principal_delta' => 0,
            'interest_delta' => 0,
            'fees_delta' => $totalFees,
            'balance_after' => $newBalance,
            'meta' => [
                'late_fee_days' => $newDays,
                'late_fee_total_days' => (int) ($pending['total_days'] ?? $newDays),
                'daily_amount' => (float) ($pending['daily_amount'] ?? ($loan->late_fee_daily_amount ?? $this->getGlobalLateFeeDailyAmount())),
                'as_of' => $date->toDateString(),
                'late_fee_date' => $date->toDateString(),
                'late_fee_start_date' => $pending['late_fee_start_date'] ?? null,
                'first_unpaid_due_date' => $pending['first_unpaid_due_date'] ?? null,
                'late_fee_cutoff_mode' => $loan->late_fee_cutoff_mode ?? 'dynamic_payment',
                'accrual_context' => $isCutoffCalculation ? 'cutoff' : 'payment',
            ],
        ]);

        $loan->fees_accrued = (float) $loan->fees_accrued + $totalFees;
        $loan->balance_total = $newBalance;
        $loan->save();

        return ['days' => $newDays, 'amount' => $totalFees];
    }

    public function calculatePendingLateFees(Loan $loan, Carbon $date): array
    {
        if (!$this->canAccrueLateFees($loan)) {
            return $this->zeroPendingLateFees();
        }

        $date = $date->copy()->startOfDay();

        $dailyLateFee = $loan->late_fee_daily_amount ?? $this->getGlobalLateFeeDailyAmount();
        if ($dailyLateFee <= 0) {
            return $this->zeroPendingLateFees();
        }

        $useFixedCutoff = ($loan->late_fee_cutoff_mode ?? 'dynamic_payment') === 'fixed_cutoff';

        return $useFixedCutoff
            ? $this->calculateFixedCutoffPendingLateFees($loan, $date, $dailyLateFee)
            : $this->calculateDynamicPendingLateFees($loan, $date, $dailyLateFee);
    }

    private function calculateDynamicPendingLateFees(Loan $loan, Carbon $date, float $dailyLateFee): array
    {
        $dueDates = $this->generateDueDates($loan, $date, false);
        if (empty($dueDates)) {
            return $this->zeroPendingLateFees();
        }

        $coveredAmount = $this->paidTowardsInstallmentsUpTo($loan, $date);
        $coveredInstallments = (int) floor($coveredAmount / max(0.01, (float) $loan->installment_amount));

        if (!isset($dueDates[$coveredInstallments])) {
            return $this->zeroPendingLateFees();
        }

        $firstUnpaidDate = $dueDates[$coveredInstallments]->copy()->startOfDay();
        $triggerType = $loan->late_fee_trigger_type ?? $this->getGlobalLateFeeTriggerType();
        $triggerValueRaw = $loan->late_fee_trigger_value;
        if ($triggerValueRaw === null) {
            $triggerValueRaw = $loan->late_fee_grace_period ?? $this->getGlobalLateFeeTriggerValue();
        }
        $triggerValue = max(0, (int) $triggerValueRaw);
        $dayType = $loan->late_fee_day_type ?? $this->getGlobalLateFeeDayType();
        $gracePeriod = max(0, (int) ($loan->late_fee_grace_period ?? $this->getGlobalLateFeeGracePeriod()));

        $lateDays = 0;
        $lateFeeStartDate = null;

        if ($triggerType === 'installments') {
            $requiredInstallments = max(1, $triggerValue);
            $overdueInstallments = max(0, count($dueDates) - $coveredInstallments);
            if ($overdueInstallments < $requiredInstallments) {
                return $this->zeroPendingLateFees(['first_unpaid_due_date' => $firstUnpaidDate->toDateString()]);
            }

            $triggerIndex = min(count($dueDates) - 1, $coveredInstallments + max(0, $requiredInstallments - 1));
            $triggerDate = $dueDates[$triggerIndex]->copy()->startOfDay();
            $lateFeeStartDate = $this->shiftByDayType($triggerDate, $gracePeriod, $dayType);
            $lateDays = max(0, $this->diffByDayType($triggerDate, $date, $dayType) - $gracePeriod);
        } else {
            $lateFeeStartDate = $this->shiftByDayType($firstUnpaidDate, $triggerValue, $dayType);
            $lateDays = max(0, $this->diffByDayType($firstUnpaidDate, $date, $dayType) - $triggerValue);
        }

        $alreadyAccruedDays = $this->accruedLateFeeDaysUpTo($loan, $date);
        $newDays = max(0, $lateDays - $alreadyAccruedDays);

        return [
            'days' => $newDays,
            'amount' => round($newDays * $dailyLateFee, 2),
            'daily_amount' => $dailyLateFee,
            'total_days' => $lateDays,
            'late_fee_start_date' => $lateFeeStartDate?->toDateString(),
            'first_unpaid_due_date' => $firstUnpaidDate->toDateString(),
        ];
    }

    private function calculateFixedCutoffPendingLateFees(Loan $loan, Carbon $date, float $dailyLateFee): array
    {
        $dayType = $loan->late_fee_day_type ?? $this->getGlobalLateFeeDayType();
        $gracePeriod = max(0, (int) ($loan->late_fee_grace_period ?? $this->getGlobalLateFeeGracePeriod()));
        $triggerValue = max(1, (int) ($loan->late_fee_trigger_value ?? $this->getGlobalLateFeeTriggerValue()));
        $lastPaymentDate = LoanPaymentCoverage::latestPaymentDate($loan, $date);
        $dueDatesSinceLastPayment = $this->generateDueDatesAfter($loan, $date, $lastPaymentDate);

        if (count($dueDatesSinceLastPayment) < $triggerValue) {
            return $this->zeroPendingLateFees();
        }

        $triggerDate = $dueDatesSinceLastPayment[$triggerValue - 1]->copy()->startOfDay();
        $lateFeeStartDate = $this->shiftByDayType($triggerDate, $gracePeriod, $dayType);
        $lateDays = max(0, $this->diffByDayType($triggerDate, $date, $dayType) - $gracePeriod);
        $alreadyAccruedDays = $this->accruedLateFeeDaysSince($loan, $lastPaymentDate, $date);
        $newDays = max(0, $lateDays - $alreadyAccruedDays);

        return [
            'days' => $newDays,
            'amount' => round($newDays * $dailyLateFee, 2),
            'daily_amount' => $dailyLateFee,
            'total_days' => $lateDays,
            'late_fee_start_date' => $lateFeeStartDate->toDateString(),
            'first_unpaid_due_date' => $triggerDate->toDateString(),
        ];
    }

    private function zeroPendingLateFees(array $overrides = []): array
    {
        return array_merge([
            'days' => 0,
            'amount' => 0.0,
            'daily_amount' => 0.0,
            'total_days' => 0,
            'late_fee_start_date' => null,
            'first_unpaid_due_date' => null,
        ], $overrides);
    }

    private function paidTowardsInstallmentsUpTo(Loan $loan, Carbon $date): float
    {
        return round((float) $loan->ledgerEntries()
            ->where('type', 'payment')
            ->whereDate('occurred_at', '<=', $date)
            ->sum('amount'), 2);
    }
    private function accruedLateFeeDaysUpTo(Loan $loan, Carbon $date): int
    {
        return $this->sumLateFeeDays(
            $loan->ledgerEntries()
                ->where('type', 'fee_accrual')
                ->whereDate('occurred_at', '<=', $date)
                ->get()
        );
    }

    private function accruedLateFeeDaysSince(Loan $loan, ?Carbon $fromDate, Carbon $toDate): int
    {
        $query = $loan->ledgerEntries()
            ->where('type', 'fee_accrual')
            ->whereDate('occurred_at', '<=', $toDate);

        if ($fromDate) {
            $query->whereDate('occurred_at', '>', $fromDate->copy()->startOfDay());
        }

        return $this->sumLateFeeDays($query->get());
    }

    private function sumLateFeeDays(Collection $entries): int
    {
        return (int) $entries->sum(function ($entry) {
            $meta = $entry->meta ?? [];

            if (isset($meta['late_fee_days']) && (int) $meta['late_fee_days'] > 0) {
                return (int) $meta['late_fee_days'];
            }

            if (isset($meta['late_fee_date']) || isset($meta['as_of'])) {
                return 1;
            }

            return 0;
        });
    }

    private function canAccrueLateFees(Loan $loan): bool
    {
        if (!in_array($loan->status, ['active', 'under_adjustment'], true)) {
            return false;
        }

        if ($loan->consolidated_into_loan_id !== null) {
            return false;
        }

        if (!$loan->enable_late_fees) {
            return false;
        }

        return (float) $loan->installment_amount > 0;
    }

    private function generateDueDates(Loan $loan, Carbon $targetDate, bool $useFixedCutoff): array
    {
        $dueDates = [];
        $currentDate = $useFixedCutoff
            ? LoanCycle::anchorDate($loan)
            : $loan->start_date->copy()->startOfDay();

        LoanCycle::advanceByModality($currentDate, $loan);

        while ($currentDate->lt($targetDate)) {
            $dueDates[] = $currentDate->copy();
            LoanCycle::advanceByModality($currentDate, $loan);
        }

        return $dueDates;
    }

    private function generateDueDatesAfter(Loan $loan, Carbon $targetDate, ?Carbon $afterDate): array
    {
        $dueDates = [];
        $currentDate = LoanCycle::anchorDate($loan);

        LoanCycle::advanceByModality($currentDate, $loan);

        while ($currentDate->lt($targetDate)) {
            if (!$afterDate || $currentDate->gt($afterDate->copy()->startOfDay())) {
                $dueDates[] = $currentDate->copy();
            }

            LoanCycle::advanceByModality($currentDate, $loan);
        }

        return $dueDates;
    }

    private function shiftByDayType(Carbon $date, int $days, string $dayType): Carbon
    {
        return $dayType === 'business'
            ? $date->copy()->addWeekdays($days)
            : $date->copy()->addDays($days);
    }

    private function diffByDayType(Carbon $fromDate, Carbon $toDate, string $dayType): int
    {
        return $dayType === 'business'
            ? $fromDate->diffInWeekdays($toDate)
            : $fromDate->diffInDays($toDate);
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

        return max(0, (int) ($value ?? 3));
    }

    private function getGlobalLateFeeTriggerType(): string
    {
        if (!Schema::hasTable('settings')) {
            return 'days';
        }

        $value = Setting::where('key', 'global_late_fee_trigger_type')->value('value');

        return in_array($value, ['days', 'installments'], true) ? $value : 'installments';
    }

    private function getGlobalLateFeeTriggerValue(): int
    {
        if (!Schema::hasTable('settings')) {
            return 3;
        }

        $value = Setting::where('key', 'global_late_fee_trigger_value')->value('value');

        return max(0, (int) ($value ?? 3));
    }

    private function getGlobalLateFeeDayType(): string
    {
        if (!Schema::hasTable('settings')) {
            return 'business';
        }

        $value = Setting::where('key', 'global_late_fee_day_type')->value('value');

        return in_array($value, ['business', 'calendar'], true) ? $value : 'business';
    }
}
