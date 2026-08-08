<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Setting;
use App\Models\SmsNotification;
use App\Models\User;
use App\Services\SmsDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SmsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.labsmobile.enabled' => true,
            'services.labsmobile.username' => 'test@example.com',
            'services.labsmobile.token' => 'test-token',
            'services.labsmobile.endpoint' => 'https://api.labsmobile.com/json/send',
            'services.labsmobile.balance_endpoint' => 'https://api.labsmobile.com/json/balance',
            'services.labsmobile.prices_endpoint' => 'https://api.labsmobile.com/json/prices',
            'services.labsmobile.test_mode' => true,
            'services.labsmobile.ack_url' => null,
            'services.labsmobile.webhook_token' => null,
        ]);
    }

    public function test_authenticated_user_can_send_a_simulated_manual_sms(): void
    {
        Http::fake([
            'https://api.labsmobile.com/json/send' => Http::response([
                'code' => 0,
                'subid' => 'manual-123',
            ]),
        ]);

        $user = User::factory()->create();
        $client = Client::factory()->create(['phone' => '809-555-1234']);

        $response = $this->actingAs($user)->post(route('sms.send'), [
            'client_id' => $client->id,
            'message' => 'Recordatorio manual de pago.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $notification = SmsNotification::firstOrFail();
        $this->assertSame('manual', $notification->source);
        $this->assertSame('simulated', $notification->status);
        $this->assertSame($user->id, $notification->sent_by_user_id);
        $this->assertSame('0.0000', $notification->credits_used);
        $this->assertSame('DOP', $notification->cost_currency);
        $this->assertSame('manual-123', $notification->provider_subid);
        $this->assertFalse($notification->ack_requested);

        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === 'https://api.labsmobile.com/json/send'
                && $request->data()['test'] === '1'
                && $request->data()['label'] === 'app-presto:manual';
        });
    }

    public function test_real_messages_use_dominican_credit_rate_and_dop_cost(): void
    {
        config([
            'services.labsmobile.test_mode' => false,
            'services.labsmobile.ack_url' => 'https://prestamos.example.com/webhooks/labsmobile/delivery',
            'services.labsmobile.webhook_token' => 'webhook-secret',
        ]);
        Setting::create(['key' => 'sms_cost_per_credit', 'value' => '2.5000']);

        Http::fake([
            'https://api.labsmobile.com/json/prices' => Http::response([
                'DO' => ['isocode' => 'DO', 'credits' => 0.797],
            ]),
            'https://api.labsmobile.com/json/send' => Http::response([
                'code' => 0,
                'subid' => 'real-123',
            ]),
            'https://api.labsmobile.com/json/balance' => Http::response([
                'code' => 0,
                'credits' => '18.75',
            ]),
        ]);

        $client = Client::factory()->create(['phone' => '829-555-1234']);

        $this->actingAs(User::factory()->create())->post(route('sms.send'), [
            'client_id' => $client->id,
            'message' => str_repeat('A', 161),
        ])->assertRedirect();

        $notification = SmsNotification::firstOrFail();
        $this->assertSame('accepted', $notification->status);
        $this->assertSame(2, $notification->segment_count);
        $this->assertSame('1.5940', $notification->credits_used);
        $this->assertSame('3.9850', $notification->estimated_cost);
        $this->assertSame('DOP', $notification->cost_currency);
        $this->assertTrue($notification->ack_requested);
        $this->assertSame(18.75, Cache::get('labsmobile.balance')['credits']);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.labsmobile.com/json/send'
            && $request->data()['long'] === '1'
            && $request->data()['ackurl'] === 'https://prestamos.example.com/webhooks/labsmobile/delivery?token=webhook-secret'
        );
    }

    public function test_manual_and_automated_messages_receive_distinct_daily_sequences(): void
    {
        Http::fake([
            'https://api.labsmobile.com/json/send' => Http::sequence()
                ->push(['code' => 0, 'subid' => 'manual-sequence'])
                ->push(['code' => 0, 'subid' => 'overdue-sequence']),
        ]);

        $client = Client::factory()->create(['phone' => '809-555-1234']);
        $dispatcher = app(SmsDispatchService::class);

        $manual = $dispatcher->send($client, 'Mensaje manual', null, 'manual');
        $overdue = $dispatcher->send($client, 'Mensaje de cobranza', null, 'overdue');

        $this->assertSame(1, $manual->message_sequence);
        $this->assertSame(2, $overdue->message_sequence);
    }

    public function test_sms_history_filters_and_summarizes_sms_segments(): void
    {
        $clientA = Client::factory()->create([
            'first_name' => 'María',
            'last_name' => 'Gómez',
            'phone' => '809-555-1111',
        ]);
        $clientB = Client::factory()->create([
            'first_name' => 'Pedro',
            'last_name' => 'Martínez',
            'phone' => '809-555-2222',
        ]);

        SmsNotification::create([
            'client_id' => $clientA->id,
            'phone' => $clientA->phone,
            'message' => 'Cobro especial de agosto',
            'provider' => 'labsmobile',
            'source' => 'manual',
            'status' => 'simulated',
            'segment_count' => 2,
            'notification_date' => now()->toDateString(),
            'message_sequence' => 1,
        ]);
        SmsNotification::create([
            'client_id' => $clientB->id,
            'phone' => $clientB->phone,
            'message' => 'Recordatorio ordinario',
            'provider' => 'labsmobile',
            'source' => 'overdue',
            'status' => 'accepted',
            'segment_count' => 1,
            'notification_date' => now()->toDateString(),
            'message_sequence' => 1,
        ]);

        Http::fake([
            'https://api.labsmobile.com/json/balance' => Http::response(['code' => 0, 'credits' => '20']),
            'https://api.labsmobile.com/json/prices' => Http::response(['DO' => ['credits' => 0.797]]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('settings.edit', ['tab' => 'sms', 'sms_search' => 'especial']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Edit')
                ->where('activeTab', 'sms')
                ->has('smsHistory.data', 1)
                ->where('smsHistory.data.0.message', 'Cobro especial de agosto')
                ->where('smsHistory.data.0.sms_count', 2)
                ->where('smsSummary.total', 1)
                ->where('smsSummary.sms_count', 2)
                ->where('smsSummary.currency', 'DOP'));
    }

    public function test_delivery_callback_marks_a_message_as_delivered(): void
    {
        config(['services.labsmobile.webhook_token' => 'webhook-secret']);
        $client = Client::factory()->create(['phone' => '849-555-1234']);
        $notification = SmsNotification::create([
            'client_id' => $client->id,
            'phone' => $client->phone,
            'message' => 'Mensaje real',
            'provider' => 'labsmobile',
            'provider_subid' => 'delivery-123',
            'source' => 'manual',
            'status' => 'accepted',
            'notification_date' => now()->toDateString(),
            'message_sequence' => 1,
        ]);

        $this->get(route('webhooks.labsmobile.delivery', [
            'token' => 'webhook-secret',
            'subid' => 'delivery-123',
            'acklevel' => 'handset',
            'status' => 'ok',
            'desc' => 'DELIVRD',
            'timestamp' => '2026-08-08 12:00:00',
        ]))->assertNoContent();

        $notification->refresh();
        $this->assertSame('delivered', $notification->status);
        $this->assertTrue($notification->ack_requested);
        $this->assertNotNull($notification->delivered_at);
        $this->assertSame('DELIVRD', $notification->delivery_details['desc']);
        $this->assertStringContainsString('Entregado', $notification->delivery_details['diagnostic']);
    }

    public function test_delivery_callback_saves_human_readable_failure_diagnostics(): void
    {
        config(['services.labsmobile.webhook_token' => 'webhook-secret']);
        $client = Client::factory()->create(['phone' => '829-555-1234']);
        $notification = SmsNotification::create([
            'client_id' => $client->id,
            'phone' => $client->phone,
            'message' => 'Mensaje real',
            'provider' => 'labsmobile',
            'provider_subid' => 'undeliv-123',
            'source' => 'manual',
            'status' => 'accepted',
            'sent_at' => now(),
            'notification_date' => now()->toDateString(),
            'message_sequence' => 1,
        ]);

        $this->get(route('webhooks.labsmobile.delivery', [
            'token' => 'webhook-secret',
            'subid' => 'undeliv-123',
            'acklevel' => 'error',
            'status' => 'ko',
            'desc' => 'UNDELIV',
            'timestamp' => '2026-08-08 12:00:00',
        ]))->assertNoContent();

        $notification->refresh();
        $this->assertSame('failed', $notification->status);
        $this->assertTrue($notification->ack_requested);
        $this->assertStringContainsString('No entregable', $notification->error_message);
        $this->assertSame('UNDELIV', $notification->delivery_details['desc']);
    }

    public function test_opening_sms_settings_refreshes_balance_automatically(): void
    {
        Http::fake([
            'https://api.labsmobile.com/json/balance' => Http::response([
                'code' => 0,
                'credits' => '18.75',
            ]),
            'https://api.labsmobile.com/json/prices' => Http::response([
                'DO' => ['credits' => 0.797],
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('settings.edit', ['tab' => 'sms']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('smsProvider.balance.credits', 18.75)
                ->where('smsProvider.credit_rate', 0.797));
    }
}
