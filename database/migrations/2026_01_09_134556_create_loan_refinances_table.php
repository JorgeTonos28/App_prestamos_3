<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_refinances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('new_loan_id')->constrained('loans')->onDelete('cascade');
            $table->foreignId('old_loan_id')->constrained('loans')->onDelete('cascade');
            $table->decimal('payoff_amount', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_refinances');
    }
};
