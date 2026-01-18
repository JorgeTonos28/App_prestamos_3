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
            $ledgerEntry = LoanLedgerEntry::where('loan_id', $payment->loan_id)
                ->where('type', 'payment')
                ->where('amount', $payment->amount) // Ledger amount is positive (absolute value of payment)
                // Payment paid_at is datetime, Ledger occurred_at is datetime.
                // They should match exactly or be on same day if logic was loose.
                // Our system uses startOfDay() mostly, but let's be careful.
                ->whereDate('occurred_at', $payment->paid_at->toDateString())
                ->first();

            if ($ledgerEntry) {
                $meta = $ledgerEntry->meta ?? [];
                if (!isset($meta['payment_id'])) {
                    $meta['payment_id'] = $payment->id;
                    $ledgerEntry->meta = $meta;
                    $ledgerEntry->save();
                }
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
