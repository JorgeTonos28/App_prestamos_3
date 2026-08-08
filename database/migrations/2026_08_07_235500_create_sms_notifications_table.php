<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20);
            $table->text('message');
            $table->string('provider')->default('labsmobile');
            $table->string('provider_subid')->nullable()->index();
            $table->string('api_code', 20)->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->date('notification_date')->index();
            $table->unsignedSmallInteger('message_sequence')->default(1);
            $table->timestamps();

            $table->unique(
                ['client_id', 'notification_date', 'provider', 'message_sequence'],
                'sms_daily_client_provider_sequence_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_notifications');
    }
};
