<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('whatsapp_phone', 32)->index();
            $table->text('whatsapp_profile_name')->nullable();
            $table->string('source', 30)->default('whatsapp');
            $table->string('status', 40)->default('collecting_data')->index();
            $table->string('current_step', 80)->default('consent');
            $table->string('consent_version', 40)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->longText('applicant_data')->nullable();
            $table->longText('loan_request')->nullable();
            $table->json('required_documents')->nullable();
            $table->decimal('risk_score', 5, 2)->nullable()->index();
            $table->string('risk_level', 20)->nullable()->index();
            $table->longText('review_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('decision_notified_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['whatsapp_phone', 'status']);
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 32)->index();
            $table->text('profile_name')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->string('current_step', 80)->default('consent');
            $table->longText('context')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('customer_service_window_expires_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('provider_message_id', 191)->nullable()->unique();
            $table->string('direction', 10)->index();
            $table->string('type', 30)->default('text');
            $table->string('status', 30)->default('received')->index();
            $table->longText('body')->nullable();
            $table->string('provider_media_id', 191)->nullable()->index();
            $table->longText('metadata')->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamp('provider_timestamp')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('applicant_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('whatsapp_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 80)->index();
            $table->string('label', 160);
            $table->text('original_name')->nullable();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64)->index();
            $table->string('disk', 40)->default('local');
            $table->string('storage_path', 500);
            $table->string('status', 30)->default('pending_validation')->index();
            $table->string('malware_scan_status', 30)->default('not_configured');
            $table->longText('validation_results')->nullable();
            $table->longText('rejection_reason')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['loan_application_id', 'checksum_sha256'],
                'applicant_documents_application_checksum_unique'
            );
        });

        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 30)->default('pending')->index();
            $table->string('model', 100)->nullable();
            $table->string('prompt_version', 40)->default('v1');
            $table->decimal('score', 5, 2)->nullable()->index();
            $table->string('level', 20)->nullable()->index();
            $table->string('recommendation', 40)->nullable();
            $table->longText('summary')->nullable();
            $table->longText('report')->nullable();
            $table->longText('factors')->nullable();
            $table->longText('red_flags')->nullable();
            $table->longText('mitigants')->nullable();
            $table->longText('deterministic_breakdown')->nullable();
            $table->longText('input_snapshot')->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_application_id', 'version']);
        });

        Schema::create('loan_application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type', 30)->default('system');
            $table->string('event', 80)->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->char('payload_hash', 64)->unique();
            $table->string('provider_event_id', 191)->nullable()->index();
            $table->string('event_type', 40)->default('messages')->index();
            $table->string('status', 30)->default('pending')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->longText('failure_reason')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('loan_application_events');
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('applicant_documents');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('loan_applications');
    }
};
