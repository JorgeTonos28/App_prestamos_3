<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->boolean('legal_auto_enabled')->default(true)->after('legal_entered_at');
            $table->integer('legal_days_overdue_threshold')->nullable()->after('legal_auto_enabled');
            $table->decimal('legal_entry_fee_amount', 14, 2)->nullable()->after('legal_days_overdue_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['legal_auto_enabled', 'legal_days_overdue_threshold', 'legal_entry_fee_amount']);
        });
    }
};
