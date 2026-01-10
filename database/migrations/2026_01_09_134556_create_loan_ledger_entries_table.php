<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['disbursement', 'interest_accrual', 'payment', 'fee_accrual', 'adjustment', 'refinance_payoff']);
            $table->datetime('occurred_at');
            $table->decimal('amount', 14, 2); // Informative total amount
            $table->decimal('principal_delta', 14, 2);
            $table->decimal('interest_delta', 14, 2);
            $table->decimal('fees_delta', 14, 2);
            $table->decimal('balance_after', 14, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_ledger_entries');
    }
};
