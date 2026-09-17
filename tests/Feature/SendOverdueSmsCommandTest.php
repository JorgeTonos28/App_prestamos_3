<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Loan;
use App\Models\Setting;
use App\Services\ArrearsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendOverdueSmsCommandTest extends TestCase
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
            'services.labsmobile.test_mode' => true,
        ]);
    }

    public function test_it_waits_until_the_configured_start_day(): void
    {
        $loan = $this->createActiveLoan();
        $this->configureAutomation(startDay: 3);

        $this->mock(ArrearsCalculator::class, function ($mock): void {
            $mock->shouldReceive('calculate')->once()->andReturn([
                'amount' => 1000,
                'days' => 2,
            ]);
        });
        Http::fake();

        $this->artisan('loans:send-overdue-sms', [
            '--force' => true,
            '--dry-run' => true,
        ])
            ->expectsOutput('Finished SMS run. Sent/simulated: 0; skipped: 0; failed: 0.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('sms_notifications', 0);
        $this->assertNotNull($loan->fresh());
        Http::assertNothingSent();
    }

    public function test_it_sends_one_message_on_the_start_day_and_skips_duplicates_for_that_day(): void
    {
        $loan = $this->createActiveLoan();
        $this->configureAutomation(startDay: 3, intervalDays: 2);

        $this->mock(ArrearsCalculator::class, function ($mock): void {
            $mock->shouldReceive('calculate')->twice()->andReturn([
                'amount' => 1000,
                'days' => 3,
            ]);
        });

        Http::fake([
            'https://api.labsmobile.com/json/send' => Http::response([
                'code' => 0,
                'subid' => 'overdue-start-day',
            ]),
        ]);

        $this->artisan('loans:send-overdue-sms', ['--force' => true])
            ->expectsOutput('Finished SMS run. Sent/simulated: 1; skipped: 0; failed: 0.')
            ->assertExitCode(0);

        $this->artisan('loans:send-overdue-sms', ['--force' => true])
            ->expectsOutput('Finished SMS run. Sent/simulated: 0; skipped: 1; failed: 0.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('sms_notifications', 1);
        $this->assertDatabaseHas('sms_notifications', [
            'loan_id' => $loan->id,
            'source' => 'overdue',
            'status' => 'simulated',
        ]);
        Http::assertSentCount(1);
    }

    private function createActiveLoan(): Loan
    {
        $client = Client::factory()->create(['phone' => '809-555-1234']);

        return Loan::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'is_archived' => false,
        ]);
    }

    private function configureAutomation(int $startDay, int $intervalDays = 1): void
    {
        foreach ([
            'overdue_sms_enabled' => '1',
            'overdue_sms_start_day' => (string) $startDay,
            'overdue_sms_interval_days' => (string) $intervalDays,
            'overdue_sms_body' => 'Recordatorio para {client_first_name}',
        ] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
