<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Add interest_base column after interest_mode
            // We use 'principal' as default to support the existing 'Simple' logic by default
            if (!Schema::hasColumn('loans', 'interest_base')) {
                $table->enum('interest_base', ['principal', 'total_balance'])
                      ->default('principal')
                      ->after('interest_mode');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            if (Schema::hasColumn('loans', 'interest_base')) {
                $table->dropColumn('interest_base');
            }
        });
    }
};
