<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE loan_ledger_entries MODIFY COLUMN type ENUM('disbursement', 'interest_accrual', 'payment', 'fee_accrual', 'legal_fee', 'adjustment', 'refinance_payoff', 'write_off', 'cancellation') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE loan_ledger_entries MODIFY COLUMN type ENUM('disbursement', 'interest_accrual', 'payment', 'fee_accrual', 'adjustment', 'refinance_payoff', 'write_off', 'cancellation') NOT NULL");
    }
};
