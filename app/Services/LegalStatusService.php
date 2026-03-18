<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Setting;
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

        $threshold = $loan->legal_days_overdue_threshold;
        if ($threshold === null) {
            $threshold = (int) (Setting::where('key', 'legal_days_overdue_threshold')->value('value') ?? 30);
        }
        $threshold = max(0, (int) $threshold);

        $lateFeeState = app(LateFeeService::class)->calculatePendingLateFees($loan, $asOf);
        $moraDays = (int) ($lateFeeState['total_days'] ?? 0);
        if ($moraDays < $threshold) {
            return false;
        }

        $legalDate = $this->calculateLegalDateFromLateFeeRules($loan, $asOf, $threshold, $lateFeeState);
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

    private function calculateLegalDateFromLateFeeRules(Loan $loan, Carbon $asOf, int $threshold, array $lateFeeState): ?Carbon
    {
        $startDate = data_get($lateFeeState, 'late_fee_start_date');
        if (!$startDate) {
            return null;
        }

        $dayType = $loan->late_fee_day_type ?? (Setting::where('key', 'global_late_fee_day_type')->value('value') ?? 'business');
        if (!in_array($dayType, ['business', 'calendar'], true)) {
            $dayType = 'business';
        }

        $lateFeeStart = Carbon::parse($startDate)->startOfDay();

        return $dayType === 'business'
            ? $lateFeeStart->copy()->addWeekdays($threshold)
            : $lateFeeStart->copy()->addDays($threshold);
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