<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\LoanApplication;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsAppAgentSettings;
use App\Services\WhatsAppCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppAgentSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(WhatsAppAgentSettings::class);
        $this->settings->put('whatsapp_agent_enabled', '1');
        $this->settings->put('whatsapp_phone_number_id', '123456789');
        $this->settings->put('whatsapp_graph_version', 'v23.0');
        $this->settings->putSecret('whatsapp_verify_token', 'verify-secret');
        $this->settings->putSecret('whatsapp_app_secret', 'meta-app-secret');
        $this->settings->putSecret('whatsapp_access_token', 'meta-access-token');
    }

    public function test_meta_can_verify_the_webhook_with_the_configured_token(): void
    {
        $this->get(route('webhooks.whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'verify-secret',
            'hub_challenge' => 'challenge-value',
        ]))
            ->assertOk()
            ->assertSeeText('challenge-value');

        $this->get(route('webhooks.whatsapp.verify', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong-token',
            'hub_challenge' => 'challenge-value',
        ]))->assertForbidden();
    }

    public function test_webhook_rejects_an_invalid_meta_signature(): void
    {
        $payload = json_encode($this->messagePayload(), JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ], $payload)->assertUnauthorized();

        $this->assertDatabaseCount('whatsapp_webhook_events', 0);
    }

    public function test_valid_webhook_is_deduplicated_and_queued(): void
    {
        Queue::fake();
        $payload = json_encode($this->messagePayload(), JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'meta-app-secret');

        foreach (range(1, 2) as $_) {
            $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ], $payload)
                ->assertOk()
                ->assertSeeText('EVENT_RECEIVED');
        }

        $this->assertDatabaseCount('whatsapp_webhook_events', 1);
        Queue::assertPushed(ProcessWhatsAppWebhook::class, 1);
    }

    public function test_webhook_job_creates_one_application_conversation_and_encrypted_message(): void
    {
        Http::fake([
            'https://graph.facebook.com/v23.0/123456789/messages' => Http::response([
                'messages' => [['id' => 'wamid.welcome-1']],
            ]),
        ]);

        $event = WhatsAppWebhookEvent::create([
            'payload_hash' => hash('sha256', 'event-1'),
            'provider_event_id' => 'wamid.inbound-1',
            'event_type' => 'message',
            'payload' => $this->messagePayload(),
            'status' => 'pending',
            'received_at' => now(),
        ]);

        app()->call([new ProcessWhatsAppWebhook($event->id), 'handle']);

        $this->assertDatabaseCount('loan_applications', 1);
        $this->assertDatabaseCount('whatsapp_conversations', 1);
        $this->assertDatabaseCount('whatsapp_messages', 2);
        $this->assertSame('Hola', WhatsAppMessage::query()->where('direction', 'inbound')->firstOrFail()->body);
        $this->assertSame('processed', $event->refresh()->status);

        $secondEvent = WhatsAppWebhookEvent::create([
            'payload_hash' => hash('sha256', 'event-2'),
            'provider_event_id' => 'wamid.inbound-1',
            'event_type' => 'message',
            'payload' => $this->messagePayload(),
            'status' => 'pending',
            'received_at' => now(),
        ]);

        app()->call([new ProcessWhatsAppWebhook($secondEvent->id), 'handle']);

        $this->assertDatabaseCount('loan_applications', 1);
        $this->assertDatabaseCount('whatsapp_messages', 2);
    }

    public function test_outbound_messages_use_meta_cloud_api_and_are_audited(): void
    {
        Http::fake([
            'https://graph.facebook.com/v23.0/123456789/messages' => Http::response([
                'contacts' => [['wa_id' => '18095551234']],
                'messages' => [['id' => 'wamid.outbound-1']],
            ]),
        ]);

        $application = LoanApplication::create([
            'whatsapp_phone' => '18095551234',
            'status' => LoanApplication::STATUS_COLLECTING_DATA,
            'current_step' => 'consent',
        ]);
        $conversation = $application->conversation()->create([
            'phone' => '18095551234',
            'status' => 'active',
            'current_step' => 'consent',
            'customer_service_window_expires_at' => now()->addHour(),
        ]);

        $message = app(WhatsAppCloudService::class)->sendText($conversation, 'Mensaje seguro');

        $this->assertSame('sent', $message->status);
        $this->assertSame('wamid.outbound-1', $message->provider_message_id);
        $this->assertSame('Mensaje seguro', $message->body);

        Http::assertSent(fn (HttpRequest $request): bool =>
            $request->url() === 'https://graph.facebook.com/v23.0/123456789/messages'
            && $request->hasHeader('Authorization', 'Bearer meta-access-token')
            && $request['to'] === '18095551234'
            && $request['text']['body'] === 'Mensaje seguro'
        );
    }

    private function messagePayload(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '18095550000',
                            'phone_number_id' => '123456789',
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'Cliente Prueba'],
                            'wa_id' => '18095551234',
                        ]],
                        'messages' => [[
                            'from' => '18095551234',
                            'id' => 'wamid.inbound-1',
                            'timestamp' => (string) now()->timestamp,
                            'text' => ['body' => 'Hola'],
                            'type' => 'text',
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
