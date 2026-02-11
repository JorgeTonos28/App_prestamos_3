<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->boolean('legal_fee_enabled')->default(true)->after('notes');
            $table->decimal('legal_fee_amount', 14, 2)->default(0)->after('legal_fee_enabled');
            $table->boolean('legal_fee_financed')->default(false)->after('legal_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['legal_fee_enabled', 'legal_fee_amount', 'legal_fee_financed']);
        });
    }
};
