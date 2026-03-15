<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Setting;
use App\Support\LoanCycle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LegalStatusService
{
    public function moveToLegalIfNeeded(Loan $loan, ?Carbon $asOf = null): bool
    {
        if (!in_array($loan->status, ['active', 'under_adjustment'], true) || $loan->legal_status || !$loan->legal_auto_enabled) {
            return false;
        }

        $asOf ??= now();
        $asOf = $asOf->copy()->startOfDay();

        $loan->loadMissing('ledgerEntries');

        $arrears = app(ArrearsCalculator::class)->calculate($loan, $asOf);

        $threshold = $loan->legal_days_overdue_threshold;
        if ($threshold === null) {
            $threshold = (int) (Setting::where('key', 'legal_days_overdue_threshold')->value('value') ?? 30);
        }
        $threshold = max(0, (int) $threshold);

        $moraDays = (int) ($arrears['late_fee_days'] ?? 0);
        if ($moraDays < $threshold) {
            return false;
        }

        $legalDate = $this->calculateLegalDateFromLateFeeRules($loan, $asOf, $threshold);
        if (!$legalDate || $legalDate->gt($asOf)) {
            return false;
        }

        $entryFee = $loan->legal_entry_fee_amount;
        if ($entryFee === null) {
            $entryFee = (float) (Setting::where('key', 'legal_entry_fee_default')->value('value') ?? 4000);
        }

        if ($this->hasLegalEntryFee($loan)) {
            $loan->legal_status = true;
            $loan->legal_entered_at = $legalDate->toDateString();
            $loan->save();

            return true;
        }

        DB::transaction(function () use ($loan, $legalDate, $entryFee) {
            $loan->legal_status = true;
            $loan->legal_entered_at = $legalDate->toDateString();
            $loan->save();

            if ($entryFee > 0) {
                $newBalance = $loan->balance_total + $entryFee;

                $loan->ledgerEntries()->create([
                    'type' => 'legal_fee',
                    'occurred_at' => $legalDate,
                    'amount' => $entryFee,
                    'principal_delta' => 0,
                    'interest_delta' => 0,
                    'fees_delta' => $entryFee,
                    'balance_after' => $newBalance,
                    'meta' => [
                        'reason' => 'legal_entry',
                        'auto_created' => true,
                    ],
                ]);

                $loan->fees_accrued += $entryFee;
                $loan->balance_total = $newBalance;
                $loan->save();
            }
        });

        return true;
    }

    public function ensureLegalEntryFeeExists(Loan $loan, ?Carbon $asOf = null): bool
    {
        if (!$loan->legal_status) {
            return false;
        }

        if ($this->hasLegalEntryFee($loan)) {
            return false;
        }

        $asOf ??= $loan->legal_entered_at
            ? Carbon::parse($loan->legal_entered_at)->startOfDay()
            : now();

        $entryFee = $loan->legal_entry_fee_amount;
        if ($entryFee === null) {
            $entryFee = (float) (Setting::where('key', 'legal_entry_fee_default')->value('value') ?? 4000);
        }

        if ($entryFee <= 0) {
            return false;
        }

        DB::transaction(function () use ($loan, $asOf, $entryFee) {
            $newBalance = $loan->balance_total + $entryFee;

            $loan->ledgerEntries()->create([
                'type' => 'legal_fee',
                'occurred_at' => $asOf,
                'amount' => $entryFee,
                'principal_delta' => 0,
                'interest_delta' => 0,
                'fees_delta' => $entryFee,
                'balance_after' => $newBalance,
                'meta' => [
                    'reason' => 'legal_entry',
                    'auto_created' => true,
                    'recreated' => true,
                ],
            ]);

            $loan->fees_accrued += $entryFee;
            $loan->balance_total = $newBalance;
            $loan->save();
        });

        return true;
    }


    public function recalculateLegalEntry(Loan $loan, ?Carbon $asOf = null): void
    {
        $asOf ??= now();
        $loan = $loan->fresh();

        $legalEntryIds = $loan->ledgerEntries()
            ->where('type', 'legal_fee')
            ->get()
            ->filter(fn ($entry) => (string) data_get($entry->meta, 'reason') === 'legal_entry')
            ->pluck('id');

        if ($legalEntryIds->isNotEmpty()) {
            $loan->ledgerEntries()->whereIn('id', $legalEntryIds)->delete();
        }

        if ($loan->legal_status || $loan->legal_entered_at) {
            $loan->legal_status = false;
            $loan->legal_entered_at = null;
            $loan->save();
        }

        $this->moveToLegalIfNeeded($loan->fresh(), $asOf->copy()->startOfDay());
        $this->ensureLegalEntryFeeExists($loan->fresh(), $asOf->copy()->startOfDay());
    }

    private function calculateLegalDateFromLateFeeRules(Loan $loan, Carbon $asOf, int $threshold): ?Carbon
    {
        $triggerType = $loan->late_fee_trigger_type ?? (Setting::where('key', 'global_late_fee_trigger_type')->value('value') ?? 'installments');
        $triggerValueRaw = $loan->late_fee_trigger_value;
        if ($triggerValueRaw === null) {
            $triggerValueRaw = Setting::where('key', 'global_late_fee_trigger_value')->value('value') ?? 3;
        }
        $triggerValue = max(0, (int) $triggerValueRaw);

        $dayType = $loan->late_fee_day_type ?? (Setting::where('key', 'global_late_fee_day_type')->value('value') ?? 'business');
        if (!in_array($dayType, ['business', 'calendar'], true)) {
            $dayType = 'business';
        }

        $gracePeriod = max(0, (int) ($loan->late_fee_grace_period ?? (int) (Setting::where('key', 'global_late_fee_grace_period')->value('value') ?? 3)));

        $lateFeeStart = $triggerType === 'installments'
            ? $this->lateFeeStartDateFromInstallments($loan, $asOf, $triggerValue, $gracePeriod, $dayType)
            : $this->lateFeeStartDateFromDays($loan, $asOf, $triggerValue, $dayType);

        if (!$lateFeeStart) {
            return null;
        }

        return $dayType === 'business'
            ? $lateFeeStart->copy()->addWeekdays($threshold)
            : $lateFeeStart->copy()->addDays($threshold);
    }

    private function lateFeeStartDateFromInstallments(Loan $loan, Carbon $asOf, int $triggerValue, int $gracePeriod, string $dayType): ?Carbon
    {
        $dueDates = $this->generateDueDates($loan, $asOf);
        if (empty($dueDates) || $triggerValue <= 0) {
            return null;
        }

        $totalPaid = round((float) $loan->ledgerEntries()
            ->where('type', 'payment')
            ->whereDate('occurred_at', '<=', $asOf)
            ->sum('amount'), 2);

        $coveredInstallments = (int) floor($totalPaid / $loan->installment_amount);

        $triggerIndex = $coveredInstallments + ($triggerValue - 1);
        if (!isset($dueDates[$triggerIndex])) {
            return null;
        }

        $triggerDate = $dueDates[$triggerIndex]->copy()->startOfDay();

        return $dayType === 'business'
            ? $triggerDate->copy()->addWeekdays($gracePeriod)
            : $triggerDate->copy()->addDays($gracePeriod);
    }

    private function lateFeeStartDateFromDays(Loan $loan, Carbon $asOf, int $triggerValue, string $dayType): ?Carbon
    {
        $arrears = app(ArrearsCalculator::class)->calculate($loan, $asOf);
        if (!isset($arrears['first_unpaid_date'])) {
            return null;
        }

        $firstUnpaidDate = Carbon::parse($arrears['first_unpaid_date'])->startOfDay();

        return $dayType === 'business'
            ? $firstUnpaidDate->copy()->addWeekdays($triggerValue)
            : $firstUnpaidDate->copy()->addDays($triggerValue);
    }

    private function generateDueDates(Loan $loan, Carbon $targetDate): array
    {
        $dueDates = [];
        $useFixedCutoff = ($loan->late_fee_cutoff_mode ?? 'dynamic_payment') === 'fixed_cutoff';
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

    private function hasLegalEntryFee(Loan $loan): bool
    {
        return $loan->ledgerEntries()
            ->where('type', 'legal_fee')
            ->get()
            ->contains(function ($entry) {
                return (string) data_get($entry->meta, 'reason') === 'legal_entry';
            });
    }
}
