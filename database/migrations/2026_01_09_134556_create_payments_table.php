<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->datetime('paid_at');
            $table->decimal('amount', 14, 2);
            $table->enum('method', ['cash', 'transfer', 'card', 'other'])->default('cash');
            $table->string('reference')->nullable();
            $table->decimal('applied_interest', 14, 2)->default(0);
            $table->decimal('applied_principal', 14, 2)->default(0);
            $table->decimal('applied_fees', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
