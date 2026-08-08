<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_notifications', function (Blueprint $table) {
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 30)->default('overdue')->index();
            $table->unsignedSmallInteger('segment_count')->default(1);
            $table->decimal('credits_used', 10, 4)->default(0);
            $table->decimal('estimated_cost', 12, 4)->default(0);
            $table->string('cost_currency', 3)->default('DOP');
            $table->json('provider_response')->nullable();
            $table->json('delivery_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sms_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_by_user_id');
            $table->dropColumn([
                'source',
                'segment_count',
                'credits_used',
                'estimated_cost',
                'cost_currency',
                'provider_response',
                'delivery_details',
            ]);
        });
    }
};
