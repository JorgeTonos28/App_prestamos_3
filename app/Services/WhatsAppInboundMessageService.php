<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanApplicationEvent;
use App\Models\WhatsAppMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class WhatsAppInboundMessageService
{
    public function __construct(private readonly WhatsAppAgentSettings $settings) {}

    public function ingest(array $message, array $value): ?WhatsAppMessage
    {
        $providerMessageId = trim((string) ($message['id'] ?? ''));
        $phone = $this->normalizePhone((string) ($message['from'] ?? ''));

        if ($providerMessageId === '' || $phone === '') {
            return null;
        }

        if (WhatsAppMessage::query()->where('provider_message_id', $providerMessageId)->exists()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($message, $value, $providerMessageId, $phone): WhatsAppMessage {
                $application = LoanApplication::query()
                    ->open()
                    ->where('whatsapp_phone', $phone)
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                $profileName = $this->profileName($value, $phone);

                if (! $application) {
                    $application = LoanApplication::create([
                        'whatsapp_phone' => $phone,
                        'whatsapp_profile_name' => $profileName,
                        'status' => LoanApplication::STATUS_COLLECTING_DATA,
                        'current_step' => 'consent',
                        'required_documents' => $this->settings->requiredDocuments(),
                        'expires_at' => now()->addDays(max(1, (int) $this->settings->get('whatsapp_application_expiry_days', 30))),
                    ]);

                    $application->conversation()->create([
                        'phone' => $phone,
                        'profile_name' => $profileName,
                        'status' => 'active',
                        'current_step' => 'consent',
                        'context' => [],
                    ]);

                    LoanApplicationEvent::create([
                        'loan_application_id' => $application->id,
                        'actor_type' => 'customer',
                        'event' => 'application_started',
                        'to_status' => LoanApplication::STATUS_COLLECTING_DATA,
                        'metadata' => ['channel' => 'whatsapp'],
                    ]);
                }

                $conversation = $application->conversation;
                $conversation->update([
                    'profile_name' => $profileName ?: $conversation->profile_name,
                    'last_message_at' => now(),
                    'customer_service_window_expires_at' => now()->addHours(24),
                ]);

                $application->update([
                    'whatsapp_profile_name' => $profileName ?: $application->whatsapp_profile_name,
                ]);

                $type = $this->messageType($message);
                $stored = $conversation->messages()->create([
                    'loan_application_id' => $application->id,
                    'provider_message_id' => $providerMessageId,
                    'direction' => 'inbound',
                    'type' => $type,
                    'status' => 'received',
                    'body' => $this->messageBody($message),
                    'provider_media_id' => data_get($message, "{$type}.id"),
                    'metadata' => [
                        'mime_type' => data_get($message, "{$type}.mime_type"),
                        'filename' => data_get($message, 'document.filename'),
                        'caption' => data_get($message, "{$type}.caption"),
                    ],
                    'provider_timestamp' => isset($message['timestamp'])
                        ? now()->setTimestamp((int) $message['timestamp'])
                        : now(),
                ]);

                LoanApplicationEvent::create([
                    'loan_application_id' => $application->id,
                    'actor_type' => 'customer',
                    'event' => 'message_received',
                    'metadata' => ['message_id' => $stored->id, 'type' => $type],
                ]);

                return $stored;
            }, 3);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                return null;
            }

            throw $exception;
        }
    }

    private function messageType(array $message): string
    {
        $type = (string) ($message['type'] ?? 'unknown');

        return in_array($type, ['text', 'image', 'document', 'button', 'interactive'], true)
            ? $type
            : 'unsupported';
    }

    private function messageBody(array $message): ?string
    {
        return match ($this->messageType($message)) {
            'text' => data_get($message, 'text.body'),
            'button' => data_get($message, 'button.text'),
            'interactive' => data_get($message, 'interactive.button_reply.title')
                ?? data_get($message, 'interactive.list_reply.title'),
            'image' => data_get($message, 'image.caption'),
            'document' => data_get($message, 'document.caption')
                ?? data_get($message, 'document.filename'),
            default => null,
        };
    }

    private function profileName(array $value, string $phone): ?string
    {
        foreach (($value['contacts'] ?? []) as $contact) {
            if ($this->normalizePhone((string) ($contact['wa_id'] ?? '')) === $phone) {
                return filled(data_get($contact, 'profile.name'))
                    ? mb_substr((string) data_get($contact, 'profile.name'), 0, 255)
                    : null;
            }
        }

        return null;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
