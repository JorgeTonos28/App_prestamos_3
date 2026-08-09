<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsAppAgentSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppAgentSettings $settings): Response
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $expected = (string) ($settings->secret('whatsapp_verify_token') ?? '');

        if ($mode !== 'subscribe' || $expected === '' || ! hash_equals($expected, $token)) {
            return response('Verification failed.', 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsAppAgentSettings $settings): Response
    {
        $rawPayload = $request->getContent();
        $appSecret = (string) ($settings->secret('whatsapp_app_secret') ?? '');
        $providedSignature = (string) $request->header('X-Hub-Signature-256', '');

        if ($appSecret === '' || $providedSignature === '') {
            return response('Signature required.', 401);
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $rawPayload, $appSecret);
        if (! hash_equals($expectedSignature, $providedSignature)) {
            return response('Invalid signature.', 401);
        }

        $payload = json_decode($rawPayload, true);
        if (! is_array($payload) || ($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response('Invalid payload.', 422);
        }

        $hash = hash('sha256', $rawPayload);
        $event = WhatsAppWebhookEvent::firstOrCreate([
            'payload_hash' => $hash,
        ], [
            'provider_event_id' => $this->providerEventId($payload),
            'event_type' => $this->eventType($payload),
            'status' => 'pending',
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if ($event->wasRecentlyCreated) {
            ProcessWhatsAppWebhook::dispatch($event->id)->afterResponse();
        }

        return response('EVENT_RECEIVED', 200)->header('Content-Type', 'text/plain');
    }

    private function providerEventId(array $payload): ?string
    {
        return data_get($payload, 'entry.0.changes.0.value.messages.0.id')
            ?? data_get($payload, 'entry.0.changes.0.value.statuses.0.id');
    }

    private function eventType(array $payload): string
    {
        if (data_get($payload, 'entry.0.changes.0.value.messages.0')) {
            return 'message';
        }

        if (data_get($payload, 'entry.0.changes.0.value.statuses.0')) {
            return 'status';
        }

        return 'unknown';
    }
}
