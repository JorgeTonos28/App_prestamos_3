<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Payment;
use App\Models\LoanLedgerEntry;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Iterate through all existing payments and link them to their ledger entry
        // This is a best-effort heuristic since we didn't link them explicitly before.
        // We match by: loan_id, amount, occurred_at (date)

        $payments = Payment::all();

        foreach ($payments as $payment) {
            // Find a ledger entry that matches this payment AND hasn't been linked yet
            $ledgerEntry = LoanLedgerEntry::where('loan_id', $payment->loan_id)
                ->where('type', 'payment')
                ->where('amount', $payment->amount)
                ->whereDate('occurred_at', $payment->paid_at->toDateString())
                ->where(function($query) {
                    // JSON path query to ensure payment_id doesn't exist
                    $query->whereNull('meta')
                          ->orWhereRaw("JSON_EXTRACT(meta, '$.payment_id') IS NULL");
                })
                ->first();

            if ($ledgerEntry) {
                $meta = $ledgerEntry->meta ?? [];
                $meta['payment_id'] = $payment->id;
                $ledgerEntry->meta = $meta;
                $ledgerEntry->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to revert metadata enrichment
    }
};
