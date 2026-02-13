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
        if ($loan->status !== 'active' || $loan->legal_status || !$loan->legal_auto_enabled) {
            return false;
        }

        $asOf ??= now();

        $loan->loadMissing('ledgerEntries');

        $arrears = app(ArrearsCalculator::class)->calculate($loan, $asOf->copy()->startOfDay());

        $threshold = $loan->legal_days_overdue_threshold;
        if ($threshold === null) {
            $threshold = (int) (Setting::where('key', 'legal_days_overdue_threshold')->value('value') ?? 30);
        }

        $firstUnpaidDate = isset($arrears['first_unpaid_date'])
            ? Carbon::parse($arrears['first_unpaid_date'])->startOfDay()
            : $asOf->copy()->startOfDay();

        $gracePeriod = $loan->late_fee_grace_period;
        if ($gracePeriod === null) {
            $gracePeriod = (int) (Setting::where('key', 'global_late_fee_grace_period')->value('value') ?? 3);
        }

        $moraDays = (int) ($arrears['late_fee_days'] ?? 0);
        if ($moraDays <= 0) {
            $businessDaysLate = $firstUnpaidDate->diffInWeekdays($asOf->copy()->startOfDay());
            $moraDays = max(0, $businessDaysLate - max(0, (int) $gracePeriod));
        }

        if ($moraDays < max(0, (int) $threshold)) {
            return false;
        }

        $legalDate = $firstUnpaidDate->copy()->addWeekdays(max(0, (int) $gracePeriod) + max(0, (int) $threshold));
        if ($legalDate->gt($asOf)) {
            $legalDate = $asOf->copy()->startOfDay();
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
