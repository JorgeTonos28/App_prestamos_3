<?php

namespace App\Services;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudService
{
    public function __construct(private readonly WhatsAppAgentSettings $settings) {}

    public function sendText(WhatsAppConversation $conversation, string $body): WhatsAppMessage
    {
        $message = $this->createPendingMessage($conversation, 'text', $body);

        return $this->send($message, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($conversation->phone),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => mb_substr(trim($body), 0, 4096),
            ],
        ]);
    }

    public function sendTemplate(
        WhatsAppConversation $conversation,
        string $templateName,
        string $language,
        array $bodyParameters = []
    ): WhatsAppMessage {
        if (! preg_match('/^[a-z0-9_]+$/', $templateName)) {
            throw new RuntimeException('The configured WhatsApp template name is invalid.');
        }

        $components = [];
        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => collect($bodyParameters)
                    ->map(fn ($value): array => ['type' => 'text', 'text' => mb_substr((string) $value, 0, 1024)])
                    ->values()
                    ->all(),
            ];
        }

        $message = $this->createPendingMessage($conversation, 'template', "template:{$templateName}");

        return $this->send($message, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($conversation->phone),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                ...($components === [] ? [] : ['components' => $components]),
            ],
        ]);
    }

    public function sendDecision(WhatsAppConversation $conversation, string $decision, string $message): WhatsAppMessage
    {
        if ($conversation->isInsideCustomerServiceWindow()) {
            return $this->sendText($conversation, $message);
        }

        $templateKey = $decision === 'approved'
            ? 'whatsapp_approval_template'
            : 'whatsapp_rejection_template';
        $template = trim((string) $this->settings->get($templateKey, ''));

        if ($template === '') {
            throw new RuntimeException('A WhatsApp decision template is required outside the 24-hour service window.');
        }

        return $this->sendTemplate(
            $conversation,
            $template,
            (string) $this->settings->get('whatsapp_template_language', 'es_DO')
        );
    }

    public function updateDeliveryStatus(string $providerMessageId, string $status, ?int $timestamp = null): void
    {
        $message = WhatsAppMessage::query()
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if (! $message) {
            return;
        }

        $knownStatuses = ['sent', 'delivered', 'read', 'failed'];
        if (! in_array($status, $knownStatuses, true)) {
            return;
        }

        $at = $timestamp ? now()->setTimestamp($timestamp) : now();
        $updates = ['status' => $status];

        if ($status === 'sent') {
            $updates['sent_at'] = $at;
        } elseif ($status === 'delivered') {
            $updates['delivered_at'] = $at;
        } elseif ($status === 'read') {
            $updates['read_at'] = $at;
        }

        $message->update($updates);
    }

    public function connectionDetails(): array
    {
        $phoneNumberId = $this->phoneNumberId();
        $response = $this->client()
            ->get($this->graphUrl($phoneNumberId), [
                'fields' => 'display_phone_number,verified_name,quality_rating',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Meta rejected the WhatsApp connection check.');
        }

        return $response->json();
    }

    private function createPendingMessage(WhatsAppConversation $conversation, string $type, string $body): WhatsAppMessage
    {
        return $conversation->messages()->create([
            'loan_application_id' => $conversation->loan_application_id,
            'direction' => 'outbound',
            'type' => $type,
            'status' => 'pending',
            'body' => $body,
        ]);
    }

    private function send(WhatsAppMessage $message, array $payload): WhatsAppMessage
    {
        try {
            $response = $this->client()
                ->post($this->graphUrl($this->phoneNumberId().'/messages'), $payload);

            if ($response->failed()) {
                throw new RuntimeException('Meta rejected the outbound WhatsApp message.');
            }

            $providerMessageId = data_get($response->json(), 'messages.0.id');
            if (! is_string($providerMessageId) || $providerMessageId === '') {
                throw new RuntimeException('Meta did not return a WhatsApp message identifier.');
            }

            $message->update([
                'provider_message_id' => $providerMessageId,
                'status' => 'sent',
                'sent_at' => now(),
                'metadata' => [
                    'contact_wa_id' => data_get($response->json(), 'contacts.0.wa_id'),
                ],
            ]);

            $message->conversation()->update([
                'last_message_at' => now(),
            ]);

            return $message->refresh();
        } catch (\Throwable $exception) {
            $message->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function client(): PendingRequest
    {
        $token = $this->settings->secret('whatsapp_access_token');
        if (! filled($token)) {
            throw new RuntimeException('WhatsApp access token is not configured.');
        }

        return Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->connectTimeout(5)
            ->retry(2, 250, throw: false);
    }

    private function phoneNumberId(): string
    {
        $phoneNumberId = trim((string) $this->settings->get('whatsapp_phone_number_id', ''));
        if (! preg_match('/^\d+$/', $phoneNumberId)) {
            throw new RuntimeException('WhatsApp phone number ID is not configured or invalid.');
        }

        return $phoneNumberId;
    }

    private function graphUrl(string $path): string
    {
        $version = trim((string) $this->settings->get('whatsapp_graph_version', 'v23.0'));
        if (! preg_match('/^v\d{1,2}\.\d$/', $version)) {
            throw new RuntimeException('The configured Meta Graph API version is invalid.');
        }

        return "https://graph.facebook.com/{$version}/{$path}";
    }

    private function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($normalized) < 8 || strlen($normalized) > 15) {
            throw new RuntimeException('The WhatsApp recipient phone is invalid.');
        }

        return $normalized;
    }
}
