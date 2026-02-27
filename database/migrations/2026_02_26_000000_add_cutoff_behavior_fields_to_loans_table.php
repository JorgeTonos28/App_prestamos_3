<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->enum('cutoff_cycle_mode', ['calendar', 'fixed_dates'])
                ->default('calendar')
                ->after('cutoff_anchor_date');
            $table->enum('month_day_count_mode', ['exact', 'thirty'])
                ->default('exact')
                ->after('cutoff_cycle_mode');
            $table->enum('late_fee_trigger_type', ['days', 'installments'])
                ->default('days')
                ->after('month_day_count_mode');
            $table->integer('late_fee_trigger_value')->nullable()
                ->after('late_fee_trigger_type');
            $table->enum('late_fee_day_type', ['business', 'calendar'])
                ->default('business')
                ->after('late_fee_trigger_value');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'cutoff_cycle_mode',
                'month_day_count_mode',
                'late_fee_trigger_type',
                'late_fee_trigger_value',
                'late_fee_day_type',
            ]);
        });
    }
};
