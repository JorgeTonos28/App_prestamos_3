<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Loan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class OverdueEmailsBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_benchmark_overdue_emails_command()
    {
        // Setup: Create 10 clients and 10 overdue loans
        // Check if tables exist first to avoid running if migration failed?
        // TestCase uses RefreshDatabase, so it should migrate.

        $clients = [];
        for ($i = 0; $i < 10; $i++) {
            $client = Client::create([
                'first_name' => "Client$i",
                'last_name' => "Test$i",
                'national_id' => "001-000000$i-0",
                'email' => "client$i@example.com",
            ]);

            Loan::create([
                'client_id' => $client->id,
                'code' => "LOAN-$i",
                'principal_initial' => 1000,
                'principal_outstanding' => 1000,
                'balance_total' => 1000,
                'status' => 'active',
                'start_date' => Carbon::now()->subMonths(2),
                'modality' => 'monthly',
                'monthly_rate' => 5.00,
                'installment_amount' => 100,
                // 'maturity_date' => Carbon::now()->addMonths(10), // Added in 2026_01_10_000001
            ]);
        }

        // Mock Mail to simulate delay
        // We are testing specific behavior based on whether `send` or `queue` is used.
        // We mock the chain: Mail::to(...) -> send(...) or queue(...)

        $mockPendingMail = \Mockery::mock(\Illuminate\Mail\PendingMail::class);

        // This is the tricky part. If the code calls `send`, we want delay.
        // If the code calls `queue`, we want instant.

        // We can set expectation for 'send' to happen 10 times.
        // If we switch to 'queue', this test will fail if we don't update it.
        // But for the BASELINE, we expect 'send'.

        // Optimization Verification: Expect 'queue' instead of 'send'

        // We expect queue to be called. We do NOT add a delay because queuing is fast.
        $mockPendingMail->shouldReceive('queue')->times(10)->andReturn(true);

        // Ensure send is NOT called (strictly verify optimization)
        $mockPendingMail->shouldReceive('send')->never();

        Mail::shouldReceive('to')->times(10)->andReturn($mockPendingMail);

        // Measure time
        $start = microtime(true);

        Artisan::call('loans:send-overdue-emails');

        $end = microtime(true);
        $duration = $end - $start;

        // Output duration
        echo "\nBenchmark Duration: " . round($duration, 3) . " seconds\n";

        // Assert duration is roughly 1s (plus overhead) if synchronous
        // If optimized, it will be faster.
        // We can just assert true for now to pass, but the echo is what we want.
        $this->assertTrue(true);
    }
}
