<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->enum('late_fee_cutoff_mode', ['dynamic_payment', 'fixed_cutoff'])
                ->default('dynamic_payment')
                ->after('late_fee_grace_period');
            $table->enum('payment_accrual_mode', ['realtime', 'cutoff_only'])
                ->default('realtime')
                ->after('late_fee_cutoff_mode');
            $table->date('cutoff_anchor_date')->nullable()->after('payment_accrual_mode');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['late_fee_cutoff_mode', 'payment_accrual_mode', 'cutoff_anchor_date']);
        });
    }
};
