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

        $arrears = app(ArrearsCalculator::class)->calculate($loan);

        $threshold = $loan->legal_days_overdue_threshold;
        if ($threshold === null) {
            $threshold = (int) (Setting::where('key', 'legal_days_overdue_threshold')->value('value') ?? 30);
        }

        if (($arrears['days'] ?? 0) < max(0, (int) $threshold)) {
            return false;
        }

        $entryFee = $loan->legal_entry_fee_amount;
        if ($entryFee === null) {
            $entryFee = (float) (Setting::where('key', 'legal_entry_fee_default')->value('value') ?? 4000);
        }

        DB::transaction(function () use ($loan, $asOf, $entryFee) {
            $loan->legal_status = true;
            $loan->legal_entered_at = $asOf->toDateString();
            $loan->save();

            if ($entryFee > 0) {
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

        $hasLegalEntryFee = $loan->ledgerEntries()
            ->where('type', 'legal_fee')
            ->where('meta->reason', 'legal_entry')
            ->exists();

        if ($hasLegalEntryFee) {
            return false;
        }

        $asOf ??= now();

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
}
