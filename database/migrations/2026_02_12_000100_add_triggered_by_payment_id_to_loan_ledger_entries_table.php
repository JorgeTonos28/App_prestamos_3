<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_ledger_entries', function (Blueprint $table) {
            $table->foreignId('triggered_by_payment_id')
                ->nullable()
                ->after('payment_id')
                ->constrained('payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loan_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('triggered_by_payment_id');
        });
    }
};
