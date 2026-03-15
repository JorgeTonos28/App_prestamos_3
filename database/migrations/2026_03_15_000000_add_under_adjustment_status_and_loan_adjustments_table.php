<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('draft', 'active', 'closed', 'closed_refinanced', 'defaulted', 'cancelled', 'written_off', 'under_adjustment') NOT NULL DEFAULT 'draft'");
        }

        Schema::create('loan_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users');
            $table->text('reason');
            $table->json('snapshot')->nullable();
            $table->timestamp('opened_at');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->text('close_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_adjustments');

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('draft', 'active', 'closed', 'closed_refinanced', 'defaulted', 'cancelled', 'written_off') NOT NULL DEFAULT 'draft'");
        }
    }
};
