<?php

namespace App\Jobs;

use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsAppAgentSettings;
use App\Services\WhatsAppCloudService;
use App\Services\WhatsAppInboundMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 90;

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue('whatsapp');
    }

    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(
        WhatsAppAgentSettings $settings,
        WhatsAppInboundMessageService $inbound,
        WhatsAppCloudService $cloud
    ): void {
        $event = WhatsAppWebhookEvent::findOrFail($this->eventId);
        if ($event->status === 'processed') {
            return;
        }

        $event->update([
            'status' => 'processing',
            'attempts' => $event->attempts + 1,
            'failure_reason' => null,
        ]);

        try {
            if (! $settings->boolean('whatsapp_agent_enabled')) {
                $event->update(['status' => 'ignored', 'processed_at' => now()]);

                return;
            }

            foreach (($event->payload['entry'] ?? []) as $entry) {
                foreach (($entry['changes'] ?? []) as $change) {
                    $value = $change['value'] ?? [];
                    if (! $this->isExpectedPhoneNumber($value, $settings)) {
                        continue;
                    }

                    foreach (($value['statuses'] ?? []) as $status) {
                        $cloud->updateDeliveryStatus(
                            (string) ($status['id'] ?? ''),
                            (string) ($status['status'] ?? ''),
                            isset($status['timestamp']) ? (int) $status['timestamp'] : null,
                        );
                    }

                    foreach (($value['messages'] ?? []) as $message) {
                        $inbound->ingest($message, $value);
                    }
                }
            }

            $event->update(['status' => 'processed', 'processed_at' => now()]);
        } catch (\Throwable $exception) {
            $event->update([
                'status' => 'failed',
                'failure_reason' => mb_substr($exception->getMessage(), 0, 1000),
            ]);

            throw $exception;
        }
    }

    private function isExpectedPhoneNumber(array $value, WhatsAppAgentSettings $settings): bool
    {
        $configured = trim((string) $settings->get('whatsapp_phone_number_id', ''));
        $received = trim((string) data_get($value, 'metadata.phone_number_id', ''));

        return $configured !== '' && hash_equals($configured, $received);
    }
}
