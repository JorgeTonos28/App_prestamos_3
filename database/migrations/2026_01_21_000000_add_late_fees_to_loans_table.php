<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (!Schema::hasColumn('loans', 'enable_late_fees')) {
                $table->boolean('enable_late_fees')->default(false)->after('fees_accrued');
            }

            if (!Schema::hasColumn('loans', 'late_fee_daily_amount')) {
                $table->decimal('late_fee_daily_amount', 14, 2)->nullable()->after('enable_late_fees');
            }

            if (!Schema::hasColumn('loans', 'late_fee_grace_period')) {
                $table->integer('late_fee_grace_period')->default(3)->after('late_fee_daily_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'late_fee_grace_period')) {
                $table->dropColumn('late_fee_grace_period');
            }

            if (Schema::hasColumn('loans', 'late_fee_daily_amount')) {
                $table->dropColumn('late_fee_daily_amount');
            }

            if (Schema::hasColumn('loans', 'enable_late_fees')) {
                $table->dropColumn('enable_late_fees');
            }
        });
    }
};
