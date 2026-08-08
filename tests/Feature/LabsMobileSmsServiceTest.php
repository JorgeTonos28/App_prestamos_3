<?php

namespace Tests\Feature;

use App\Services\LabsMobileSmsService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LabsMobileSmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
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

    public function test_it_submits_a_simulated_sms_using_labs_mobile_json_contract(): void
    {
        Http::fake([
            'https://api.labsmobile.com/json/send' => Http::response([
                'code' => 0,
                'subid' => 'simulated-123',
            ]),
        ]);

        $result = app(LabsMobileSmsService::class)->send('809-555-1234', 'Mensaje de prueba');

        $this->assertSame('simulated-123', $result['subid']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.labsmobile.com/json/send'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('test@example.com:test-token'))
                && $request->data() === [
                    'message' => 'Mensaje de prueba',
                    'recipient' => [['msisdn' => '18095551234']],
                    'test' => '1',
                ];
        });
    }

    public function test_it_appends_the_private_webhook_token_to_ackurl(): void
    {
        config([
            'services.labsmobile.ack_url' => 'https://prestamos.example.com/webhooks/labsmobile/delivery',
            'services.labsmobile.webhook_token' => 'secret value',
        ]);

        Http::fake([
            'https://api.labsmobile.com/json/send' => Http::response(['code' => 0, 'subid' => 'ack-123']),
        ]);

        app(LabsMobileSmsService::class)->send('829-555-1234', 'Prueba ACK');

        Http::assertSent(fn (Request $request): bool =>
            $request->data()['ackurl'] === 'https://prestamos.example.com/webhooks/labsmobile/delivery?token=secret%20value'
        );
    }

    public function test_it_reads_the_current_dominican_credit_rate(): void
    {
        Http::fake([
            'https://api.labsmobile.com/json/prices' => Http::response([
                'DO' => [
                    'isocode' => 'DO',
                    'prefix' => '1',
                    'name' => 'Dominican Republic',
                    'credits' => 0.797,
                ],
            ]),
        ]);

        $rate = app(LabsMobileSmsService::class)->countryPrice('DO');

        $this->assertSame(0.797, $rate);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://api.labsmobile.com/json/prices'
            && $request->data()['countries'] === ['DO']
        );
    }

    public function test_test_command_only_runs_in_simulated_mode(): void
    {
        config(['services.labsmobile.test_mode' => false]);

        $this->artisan('labsmobile:send-test', ['phone' => '809-555-1234'])
            ->expectsOutput('Refusing to send: LABSMOBILE_TEST_MODE must be true for this command.')
            ->assertExitCode(Command::FAILURE);

        Http::assertNothingSent();
    }

    public function test_it_rejects_invalid_dominican_phone_numbers_before_contacting_labs_mobile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Dominican phone number');

        app(LabsMobileSmsService::class)->send('555-1234', 'Mensaje de prueba');

        Http::assertNothingSent();
    }
}
