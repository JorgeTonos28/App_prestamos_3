<?php

namespace App\Support;

use App\Helpers\FinancialHelper;
use App\Models\Loan;
use Carbon\Carbon;

class LoanDelinquencySnapshot
{
    public static function build(Loan $loan, Carbon $asOf): array
    {
        $asOf = $asOf->copy()->startOfDay();
        $dueDates = self::generateDueDates($loan, $asOf);

        if (empty($dueDates)) {
            return self::emptySnapshot();
        }

        $entries = self::entriesUpTo($loan, $asOf);
        $hasDisbursementEntry = $entries->contains(fn ($entry) => $entry->type === 'disbursement');

        $state = [
            'principal_outstanding' => $hasDisbursementEntry ? 0.0 : round((float) $loan->principal_initial, 2),
            'interest_accrued' => 0.0,
            'fees_accrued' => 0.0,
            'balance_total' => $hasDisbursementEntry ? 0.0 : round((float) $loan->principal_initial, 2),
        ];

        $dueItems = [];
        $entryIndex = 0;
        $lastAccrualDate = Carbon::parse($loan->start_date)->startOfDay();

        foreach ($dueDates as $dueDate) {
            self::applyEntriesBeforeDate($entries, $entryIndex, $dueDate, $state, $dueItems, $lastAccrualDate);

            $requiredAmount = self::projectRequiredAmount($loan, $state, $lastAccrualDate, $dueDate, $dueItems);
            $dueItems[] = [
                'due_date' => $dueDate->toDateString(),
                'required_amount' => $requiredAmount,
                'paid_amount' => 0.0,
                'unpaid_amount' => $requiredAmount,
            ];

            if ($requiredAmount > 0) {
                $state['interest_accrued'] = round($state['interest_accrued'] + $requiredAmount, 2);
                $state['balance_total'] = round($state['balance_total'] + $requiredAmount, 2);
            }

            $lastAccrualDate = $dueDate->copy();

            self::applyEntriesOnDate($entries, $entryIndex, $dueDate, $state, $dueItems, $lastAccrualDate);
        }

        self::applyEntriesThroughDate($entries, $entryIndex, $asOf, $state, $dueItems, $lastAccrualDate);

        return self::summarizeDueItems($dueItems, $asOf);
    }

    private static function emptySnapshot(): array
    {
        return [
            'count' => 0.0,
            'amount' => 0.0,
            'expected_to_date' => 0.0,
            'paid_to_date' => 0.0,
            'paid_gross_to_date' => 0.0,
            'first_unpaid_date' => null,
            'details' => [],
        ];
    }

    private static function summarizeDueItems(array $dueItems, Carbon $asOf): array
    {
        $expectedTotal = round(array_sum(array_column($dueItems, 'required_amount')), 2);
        $paidTotal = round(array_sum(array_column($dueItems, 'paid_amount')), 2);
        $unpaidTotal = round(array_sum(array_column($dueItems, 'unpaid_amount')), 2);

        $count = 0.0;
        $firstUnpaidDate = null;

        foreach ($dueItems as $item) {
            $required = (float) ($item['required_amount'] ?? 0);
            $unpaid = (float) ($item['unpaid_amount'] ?? 0);

            if ($required > 0.0001 && $unpaid > 0.01) {
                $count += $unpaid / $required;
                $firstUnpaidDate ??= $item['due_date'];
            }
        }

        return [
            'count' => round($count, 1),
            'amount' => $unpaidTotal,
            'expected_to_date' => $expectedTotal,
            'paid_to_date' => $paidTotal,
            'paid_gross_to_date' => $paidTotal,
            'first_unpaid_date' => $firstUnpaidDate,
            'details' => array_map(function (array $item) {
                return [
                    'due_date' => $item['due_date'],
                    'required_amount' => round((float) $item['required_amount'], 2),
                    'paid_amount' => round((float) $item['paid_amount'], 2),
                    'unpaid_amount' => round((float) $item['unpaid_amount'], 2),
                ];
            }, $dueItems),
        ];
    }

    private static function generateDueDates(Loan $loan, Carbon $asOf): array
    {
        $dueDates = [];
        $currentDate = (($loan->late_fee_cutoff_mode ?? 'dynamic_payment') === 'fixed_cutoff')
            ? LoanCycle::anchorDate($loan)
            : Carbon::parse($loan->start_date)->startOfDay();

        LoanCycle::advanceByModality($currentDate, $loan);

        while ($currentDate->lt($asOf)) {
            $dueDates[] = $currentDate->copy()->startOfDay();
            LoanCycle::advanceByModality($currentDate, $loan);
        }

        return $dueDates;
    }

    private static function entriesUpTo(Loan $loan, Carbon $asOf)
    {
        if ($loan->relationLoaded('ledgerEntries')) {
            return $loan->ledgerEntries
                ->filter(fn ($entry) => Carbon::parse($entry->occurred_at)->startOfDay()->lte($asOf))
                ->sortBy([
                    ['occurred_at', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();
        }

        return $loan->ledgerEntries()
            ->whereDate('occurred_at', '<=', $asOf)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get([
                'id',
                'type',
                'occurred_at',
                'amount',
                'principal_delta',
                'interest_delta',
                'fees_delta',
                'meta',
            ]);
    }

    private static function applyEntriesBeforeDate($entries, int &$entryIndex, Carbon $date, array &$state, array &$dueItems, Carbon &$lastAccrualDate): void
    {
        while ($entryIndex < $entries->count()) {
            $entryDate = Carbon::parse($entries[$entryIndex]->occurred_at)->startOfDay();
            if (!$entryDate->lt($date)) {
                break;
            }

            self::applyEntry($entries[$entryIndex], $state, $dueItems, $lastAccrualDate);
            $entryIndex++;
        }
    }

    private static function applyEntriesOnDate($entries, int &$entryIndex, Carbon $date, array &$state, array &$dueItems, Carbon &$lastAccrualDate): void
    {
        while ($entryIndex < $entries->count()) {
            $entryDate = Carbon::parse($entries[$entryIndex]->occurred_at)->startOfDay();
            if (!$entryDate->equalTo($date)) {
                break;
            }

            self::applyEntry($entries[$entryIndex], $state, $dueItems, $lastAccrualDate);
            $entryIndex++;
        }
    }

    private static function applyEntriesThroughDate($entries, int &$entryIndex, Carbon $date, array &$state, array &$dueItems, Carbon &$lastAccrualDate): void
    {
        while ($entryIndex < $entries->count()) {
            $entryDate = Carbon::parse($entries[$entryIndex]->occurred_at)->startOfDay();
            if ($entryDate->gt($date)) {
                break;
            }

            self::applyEntry($entries[$entryIndex], $state, $dueItems, $lastAccrualDate);
            $entryIndex++;
        }
    }

    private static function applyEntry($entry, array &$state, array &$dueItems, Carbon &$lastAccrualDate): void
    {
        $entryDate = Carbon::parse($entry->occurred_at)->startOfDay();
        $type = (string) $entry->type;
        $meta = $entry->meta ?? [];

        if ($type === 'interest_accrual' && (string) data_get($meta, 'accrual_context') === 'cutoff') {
            return;
        }

        $state['principal_outstanding'] = round($state['principal_outstanding'] + (float) $entry->principal_delta, 2);
        $state['interest_accrued'] = round($state['interest_accrued'] + (float) $entry->interest_delta, 2);
        $state['fees_accrued'] = round($state['fees_accrued'] + (float) $entry->fees_delta, 2);
        $state['balance_total'] = round(
            $state['principal_outstanding'] + $state['interest_accrued'] + $state['fees_accrued'],
            2
        );

        if ($type === 'interest_accrual') {
            $lastAccrualDate = $entryDate->copy();
        }

        if ($type === 'payment') {
            $coverage = abs((float) $entry->interest_delta);

            if ($coverage <= 0.0001) {
                $coverage = max(0, round((float) $entry->amount - abs((float) $entry->fees_delta), 2));
            }

            if ($coverage > 0) {
                self::allocateCoverage($dueItems, $coverage, $entryDate);
            }
        }
    }

    private static function allocateCoverage(array &$dueItems, float $coverage, Carbon $paymentDate): void
    {
        foreach ($dueItems as &$item) {
            if ($coverage <= 0.0001) {
                break;
            }

            if (Carbon::parse($item['due_date'])->startOfDay()->gt($paymentDate)) {
                break;
            }

            $unpaid = (float) ($item['unpaid_amount'] ?? 0);
            if ($unpaid <= 0.01) {
                continue;
            }

            $applied = min($coverage, $unpaid);
            $item['paid_amount'] = round((float) $item['paid_amount'] + $applied, 2);
            $item['unpaid_amount'] = round($unpaid - $applied, 2);
            $coverage = round($coverage - $applied, 2);
        }
        unset($item);
    }

    private static function projectRequiredAmount(
        Loan $loan,
        array $state,
        Carbon $fromDate,
        Carbon $dueDate,
        array $dueItems
    ): float {
        $daysToAccrue = self::resolveDaysToAccrue($loan, $fromDate, $dueDate);
        if ($daysToAccrue <= 0) {
            return 0.0;
        }

        $base = self::resolveInterestBase($loan, $state, $dueItems);
        if ($base <= 0) {
            return 0.0;
        }

        return round($base * self::dailyRate($loan) * $daysToAccrue, 2);
    }

    private static function resolveInterestBase(Loan $loan, array $state, array $dueItems): float
    {
        if ($loan->interest_mode === 'simple') {
            return (float) $loan->principal_initial;
        }

        if (self::hasUnpaidDueItems($dueItems)) {
            return (float) $state['balance_total'];
        }

        return $loan->interest_base === 'total_balance'
            ? (float) $state['balance_total']
            : (float) $state['principal_outstanding'];
    }

    private static function hasUnpaidDueItems(array $dueItems): bool
    {
        foreach ($dueItems as $item) {
            if ((float) ($item['unpaid_amount'] ?? 0) > 0.01) {
                return true;
            }
        }

        return false;
    }

    private static function dailyRate(Loan $loan): float
    {
        $convention = $loan->days_in_month_convention ?: 30;

        return ((float) $loan->monthly_rate / 100) / $convention;
    }

    private static function resolveDaysToAccrue(Loan $loan, Carbon $fromDate, Carbon $toDate): int
    {
        if (($loan->late_fee_cutoff_mode ?? 'dynamic_payment') === 'fixed_cutoff'
            && ($loan->cutoff_cycle_mode ?? 'calendar') === 'fixed_dates'
        ) {
            if ($loan->modality === 'biweekly' && ($loan->month_day_count_mode ?? 'exact') === 'thirty') {
                return 15;
            }

            if ($loan->modality === 'monthly' && ($loan->month_day_count_mode ?? 'exact') === 'thirty') {
                return 30;
            }
        }

        $convention = $loan->days_in_month_convention ?: 30;

        return FinancialHelper::diffInDays($fromDate, $toDate, $convention);
    }
}
