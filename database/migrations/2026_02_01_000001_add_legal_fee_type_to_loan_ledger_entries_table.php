<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');

            DB::statement(
                "CREATE TABLE loan_ledger_entries_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    loan_id INTEGER NOT NULL,
                    payment_id INTEGER NULL,
                    type TEXT NOT NULL CHECK (type IN ('disbursement', 'interest_accrual', 'payment', 'fee_accrual', 'legal_fee', 'adjustment', 'refinance_payoff', 'write_off', 'cancellation')),
                    occurred_at DATETIME NOT NULL,
                    amount NUMERIC NOT NULL,
                    principal_delta NUMERIC NOT NULL,
                    interest_delta NUMERIC NOT NULL,
                    fees_delta NUMERIC NOT NULL,
                    balance_after NUMERIC NULL,
                    meta TEXT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
                    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
                )"
            );

            DB::statement(
                "INSERT INTO loan_ledger_entries_new (id, loan_id, payment_id, type, occurred_at, amount, principal_delta, interest_delta, fees_delta, balance_after, meta, created_at, updated_at)
                 SELECT id, loan_id, payment_id, type, occurred_at, amount, principal_delta, interest_delta, fees_delta, balance_after, meta, created_at, updated_at
                 FROM loan_ledger_entries"
            );

            DB::statement('DROP TABLE loan_ledger_entries');
            DB::statement('ALTER TABLE loan_ledger_entries_new RENAME TO loan_ledger_entries');
            DB::statement('PRAGMA foreign_keys=on');

            return;
        }

        DB::statement("ALTER TABLE loan_ledger_entries MODIFY COLUMN type ENUM('disbursement', 'interest_accrual', 'payment', 'fee_accrual', 'legal_fee', 'adjustment', 'refinance_payoff', 'write_off', 'cancellation') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');

            DB::statement(
                "CREATE TABLE loan_ledger_entries_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    loan_id INTEGER NOT NULL,
                    payment_id INTEGER NULL,
                    type TEXT NOT NULL CHECK (type IN ('disbursement', 'interest_accrual', 'payment', 'fee_accrual', 'adjustment', 'refinance_payoff', 'write_off', 'cancellation')),
                    occurred_at DATETIME NOT NULL,
                    amount NUMERIC NOT NULL,
                    principal_delta NUMERIC NOT NULL,
                    interest_delta NUMERIC NOT NULL,
                    fees_delta NUMERIC NOT NULL,
                    balance_after NUMERIC NULL,
                    meta TEXT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
                    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
                )"
            );

            DB::statement(
                "INSERT INTO loan_ledger_entries_new (id, loan_id, payment_id, type, occurred_at, amount, principal_delta, interest_delta, fees_delta, balance_after, meta, created_at, updated_at)
                 SELECT id, loan_id, payment_id, type, occurred_at, amount, principal_delta, interest_delta, fees_delta, balance_after, meta, created_at, updated_at
                 FROM loan_ledger_entries"
            );

            DB::statement('DROP TABLE loan_ledger_entries');
            DB::statement('ALTER TABLE loan_ledger_entries_new RENAME TO loan_ledger_entries');
            DB::statement('PRAGMA foreign_keys=on');

            return;
        }

        DB::statement("ALTER TABLE loan_ledger_entries MODIFY COLUMN type ENUM('disbursement', 'interest_accrual', 'payment', 'fee_accrual', 'adjustment', 'refinance_payoff', 'write_off', 'cancellation') NOT NULL");
    }
};
