<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add new statuses to loans table
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('draft', 'active', 'closed', 'closed_refinanced', 'defaulted', 'cancelled', 'written_off') NOT NULL DEFAULT 'draft'");
        }

        // Add cancellation details columns
        Schema::table('loans', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('status');
            $table->date('cancellation_date')->nullable()->after('cancellation_reason');
        });

        // Add new types to loan_ledger_entries table
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE loan_ledger_entries MODIFY COLUMN type ENUM('disbursement', 'interest_accrual', 'payment', 'fee_accrual', 'adjustment', 'refinance_payoff', 'write_off', 'cancellation') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'cancellation_date']);
        });

        // Revert statuses (WARNING: this might fail if there are records with new statuses)
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('draft', 'active', 'closed', 'closed_refinanced', 'defaulted') NOT NULL DEFAULT 'draft'");

        DB::statement("ALTER TABLE loan_ledger_entries MODIFY COLUMN type ENUM('disbursement', 'interest_accrual', 'payment', 'fee_accrual', 'adjustment', 'refinance_payoff') NOT NULL");
    }
};
