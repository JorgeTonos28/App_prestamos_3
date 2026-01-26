<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\LoanLedgerEntry;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loan_ledger_entries', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('loan_id')->constrained()->onDelete('set null');
        });

        // Backfill data from meta
        $entries = LoanLedgerEntry::whereNotNull('meta')->where('type', 'payment')->get();
        foreach ($entries as $entry) {
            if (isset($entry->meta['payment_id'])) {
                $entry->payment_id = $entry->meta['payment_id'];
                $entry->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_id');
        });
    }
};
