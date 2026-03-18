<?php

namespace App\Support;

use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LoanPaymentCoverage
{
    public static function amountAppliedToScheduledDebtUpTo(Loan $loan, Carbon $asOfDate): float
    {
        $entries = self::paymentEntriesUpTo($loan, $asOfDate);

        return round((float) $entries->sum(fn ($entry) => self::entryAppliedToScheduledDebt($entry)), 2);
    }

    public static function latestPaymentDate(Loan $loan, Carbon $asOfDate): ?Carbon
    {
        $asOfDate = $asOfDate->copy()->startOfDay();

        if ($loan->relationLoaded('ledgerEntries')) {
            $entry = $loan->ledgerEntries
                ->filter(fn ($entry) => $entry->type === 'payment' && Carbon::parse($entry->occurred_at)->startOfDay()->lte($asOfDate))
                ->sortBy([
                    ['occurred_at', 'desc'],
                    ['id', 'desc'],
                ])
                ->first();

            return $entry ? Carbon::parse($entry->occurred_at)->startOfDay() : null;
        }

        $occurredAt = LoanLedgerEntry::query()
            ->where('loan_id', $loan->id)
            ->where('type', 'payment')
            ->whereDate('occurred_at', '<=', $asOfDate)
            ->orderBy('occurred_at', 'desc')
            ->orderBy('id', 'desc')
            ->value('occurred_at');

        return $occurredAt ? Carbon::parse($occurredAt)->startOfDay() : null;
    }

    public static function entryAppliedToScheduledDebt($entry): float
    {
        $principal = abs((float) data_get($entry, 'principal_delta', 0));
        $interest = abs((float) data_get($entry, 'interest_delta', 0));
        $applied = round($principal + $interest, 2);

        if ($applied > 0) {
            return $applied;
        }

        $amount = (float) data_get($entry, 'amount', 0);
        $fees = abs((float) data_get($entry, 'fees_delta', 0));

        return round(max(0, $amount - $fees), 2);
    }

    private static function paymentEntriesUpTo(Loan $loan, Carbon $asOfDate): Collection
    {
        $asOfDate = $asOfDate->copy()->startOfDay();

        if ($loan->relationLoaded('ledgerEntries')) {
            return $loan->ledgerEntries
                ->filter(fn ($entry) => $entry->type === 'payment' && Carbon::parse($entry->occurred_at)->startOfDay()->lte($asOfDate))
                ->values();
        }

        return $loan->ledgerEntries()
            ->where('type', 'payment')
            ->whereDate('occurred_at', '<=', $asOfDate)
            ->get(['id', 'occurred_at', 'amount', 'principal_delta', 'interest_delta', 'fees_delta']);

    }
}

