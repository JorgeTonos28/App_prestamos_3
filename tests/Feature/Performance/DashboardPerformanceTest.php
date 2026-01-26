<?php

namespace Tests\Feature\Performance;

use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class DashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stats_caching_performance()
    {
        // Authenticate
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a significant number of loans to simulate load
        $client = Client::factory()->create();
        $loans = Loan::factory()->count(100)->create([
            'client_id' => $client->id,
            'status' => 'active',
            'start_date' => now()->subMonths(1),
            'principal_initial' => 5000,
        ]);

        foreach ($loans as $loan) {
            LoanLedgerEntry::factory()->create([
                'loan_id' => $loan->id,
                'type' => 'payment',
                'amount' => 100,
                'occurred_at' => now(),
                'interest_delta' => -50,
                'principal_delta' => -50,
            ]);
        }

        // Measure First Request (Cache Miss)
        DB::enableQueryLog();
        $startTime = microtime(true);
        $response = $this->get(route('dashboard'));
        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        $firstRequestQueries = count($queries);
        $firstRequestTime = $endTime - $startTime;

        $response->assertOk();

        // Measure Second Request (Should be Cache Hit)
        DB::flushQueryLog();
        $startTime = microtime(true);
        $response2 = $this->get(route('dashboard'));
        $endTime = microtime(true);
        $queries2 = DB::getQueryLog();
        $secondRequestQueries = count($queries2);
        $secondRequestTime = $endTime - $startTime;

        $response2->assertOk();

        // Assert that caching is working by checking that queries decreased
        $this->assertLessThan($firstRequestQueries, $secondRequestQueries, "Caching is not working! Queries did not decrease.");
    }
}
