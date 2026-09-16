<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_notifications', function (Blueprint $table) {
            $table->boolean('ack_requested')->default(false)->after('provider_response');
        });
    }

    public function down(): void
    {
        Schema::table('sms_notifications', function (Blueprint $table) {
            $table->dropColumn('ack_requested');
        });
    }
};
