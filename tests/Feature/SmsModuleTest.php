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

        config([
            'services.labsmobile.enabled' => true,
            'services.labsmobile.username' => 'test@example.com',
            'services.labsmobile.token' => 'test-token',
            'services.labsmobile.endpoint' => 'https://api.labsmobile.com/json/send',
            'services.labsmobile.balance_endpoint' => 'https://api.labsmobile.com/json/balance',
            'services.labsmobile.test_mode' => true,
            'services.labsmobile.ack_url' => null,
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
        $this->assertSame('manual-123', $notification->provider_subid);

        Http::assertSent(function (HttpRequest $request): bool {
            return $request->url() === 'https://api.labsmobile.com/json/send'
                && $request->data()['test'] === '1'
                && $request->data()['label'] === 'app-presto:manual';
        });
    }

    public function test_real_messages_snapshot_segments_credits_and_estimated_cost(): void
    {
        config(['services.labsmobile.test_mode' => false]);
        Setting::create(['key' => 'sms_cost_per_credit', 'value' => '0.0250']);
        Setting::create(['key' => 'sms_cost_currency', 'value' => 'USD']);

        Http::fake([
            'https://api.labsmobile.com/json/send' => Http::response([
                'code' => 0,
                'subid' => 'real-123',
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
        $this->assertSame('2.0000', $notification->credits_used);
        $this->assertSame('0.0500', $notification->estimated_cost);
        $this->assertSame('USD', $notification->cost_currency);

        Http::assertSent(fn (HttpRequest $request): bool => $request->data()['long'] === '1');
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

    public function test_sms_history_can_be_filtered_by_any_text_value(): void
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
            'notification_date' => now()->toDateString(),
            'message_sequence' => 1,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('settings.edit', ['tab' => 'sms', 'sms_search' => 'especial']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Edit')
                ->where('activeTab', 'sms')
                ->has('smsHistory.data', 1)
                ->where('smsHistory.data.0.message', 'Cobro especial de agosto')
                ->where('smsSummary.total', 1));
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
        $this->assertNotNull($notification->delivered_at);
        $this->assertSame('DELIVRD', $notification->delivery_details['desc']);
    }

    public function test_balance_can_be_refreshed_without_exposing_credentials(): void
    {
        Http::fake([
            'https://api.labsmobile.com/json/balance' => Http::response([
                'code' => 0,
                'credits' => '18.75',
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('settings.sms.balance'))
            ->assertSessionHas('success');

        $this->assertSame(18.75, Cache::get('labsmobile.balance')['credits']);
    }
}
