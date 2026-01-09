<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->enum('status', ['draft', 'active', 'closed', 'closed_refinanced', 'defaulted'])->default('draft');
            $table->date('start_date');
            $table->enum('modality', ['daily', 'weekly', 'biweekly', 'monthly']);
            $table->decimal('monthly_rate', 8, 2); // Percentage
            $table->enum('interest_mode', ['simple', 'compound'])->default('simple');
            $table->enum('interest_base', ['principal', 'total_balance'])->default('principal');
            $table->integer('days_in_month_convention')->default(30);
            $table->integer('days_in_period_weekly')->default(7);
            $table->integer('days_in_period_biweekly')->default(15);
            $table->decimal('installment_amount', 14, 2);
            $table->integer('target_term_periods')->nullable();
            $table->string('currency')->default('DOP');

            // Caches
            $table->decimal('principal_initial', 14, 2)->default(0);
            $table->decimal('principal_outstanding', 14, 2)->default(0);
            $table->decimal('interest_accrued', 14, 2)->default(0);
            $table->decimal('fees_accrued', 14, 2)->default(0);
            $table->decimal('balance_total', 14, 2)->default(0);
            $table->date('last_accrual_date')->nullable();
            $table->date('next_due_date')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
