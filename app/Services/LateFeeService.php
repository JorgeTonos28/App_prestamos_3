<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class LateFeeService
{
    public function accrueForPayment(Loan $loan, Carbon $paidAt, ?int $triggeredByPaymentId = null): array
    {
        return $this->checkAndAccrueLateFees($loan, $paidAt, $triggeredByPaymentId);
    }

    public function checkAndAccrueLateFees(Loan $loan, Carbon $date, ?int $triggeredByPaymentId = null): array
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
                'daily_amount' => (float) ($pending['daily_amount'] ?? ($loan->late_fee_daily_amount ?? $this->getGlobalLateFeeDailyAmount())),
                'as_of' => $date->toDateString(),
                'late_fee_date' => $date->toDateString(),
                'first_unpaid_due_date' => $pending['first_unpaid_due_date'] ?? null,
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
            return ['days' => 0, 'amount' => 0.0];
        }

        $date = $date->copy()->startOfDay();

        $dailyLateFee = $loan->late_fee_daily_amount ?? $this->getGlobalLateFeeDailyAmount();
        if ($dailyLateFee <= 0) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $dueDates = $this->generateDueDates($loan, $date);
        if (empty($dueDates)) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $totalPaid = $this->paidTowardsInstallmentsUpTo($loan, $date);

        $coveredInstallments = (int) floor($totalPaid / $loan->installment_amount);
        if (!isset($dueDates[$coveredInstallments])) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $firstUnpaidDate = $dueDates[$coveredInstallments]->copy()->startOfDay();
        $gracePeriod = $loan->late_fee_grace_period ?? $this->getGlobalLateFeeGracePeriod();
        $businessDaysLate = $firstUnpaidDate->diffInWeekdays($date);
        $totalChargeableDays = max(0, $businessDaysLate - $gracePeriod);

        if ($totalChargeableDays <= 0) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $alreadyAccruedDays = $this->accruedLateFeeDaysUpTo($loan, $date);
        $newDays = max(0, $totalChargeableDays - $alreadyAccruedDays);

        if ($newDays === 0) {
            return ['days' => 0, 'amount' => 0.0];
        }

        return [
            'days' => $newDays,
            'amount' => round($newDays * $dailyLateFee, 2),
            'daily_amount' => $dailyLateFee,
            'first_unpaid_due_date' => $firstUnpaidDate->toDateString(),
        ];
    }

    private function accruedLateFeeDaysUpTo(Loan $loan, Carbon $date): int
    {
        return (int) $loan->ledgerEntries()
            ->where('type', 'fee_accrual')
            ->whereDate('occurred_at', '<=', $date)
            ->get()
            ->sum(function ($entry) {
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

    private function paidTowardsInstallmentsUpTo(Loan $loan, Carbon $date): float
    {
        $paymentEntries = $loan->ledgerEntries()
            ->where('type', 'payment')
            ->whereDate('occurred_at', '<=', $date)
            ->get(['principal_delta', 'interest_delta']);

        return round((float) $paymentEntries->sum(function ($entry) {
            return max(0, -((float) $entry->principal_delta + (float) $entry->interest_delta));
        }), 2);
    }

    private function canAccrueLateFees(Loan $loan): bool
    {
        if ($loan->status !== 'active') {
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
