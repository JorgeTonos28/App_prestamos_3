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

class LoanIndexPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_loans_index_n_plus_one()
    {
        // Authenticate
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a client
        $client = Client::factory()->create();

        // Create 20 active loans (one full page)
        $loans = Loan::factory()->count(20)->create([
            'client_id' => $client->id,
            'status' => 'active',
            'start_date' => now()->subMonths(5),
            'installment_amount' => 1000,
            'modality' => 'monthly',
        ]);

        // Add ledger entries for each loan
        foreach ($loans as $loan) {
            // Add a disbursement (should be ignored by calculator, but relevant for N+1 if not careful)
            LoanLedgerEntry::factory()->create([
                'loan_id' => $loan->id,
                'type' => 'disbursement',
                'amount' => 10000,
            ]);

            // Add some payments
            LoanLedgerEntry::factory()->create([
                'loan_id' => $loan->id,
                'type' => 'payment',
                'amount' => 1000,
            ]);

             LoanLedgerEntry::factory()->create([
                'loan_id' => $loan->id,
                'type' => 'payment',
                'amount' => 1000,
            ]);
        }

        // Enable query logging
        DB::enableQueryLog();

        // Visit the index page
        $response = $this->get(route('loans.index'));
        $response->assertOk();

        // Assert we have 20 loans
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loans/Index')
            ->has('loans.data', 20)
        );

        // Get the queries
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Analyze Baseline:
        // 1. Count query for pagination
        // 2. Select loans with limit 20
        // 3. Eager load clients (1 query)
        // 4. Loop 20 loans:
        //    For each loan, ArrearsCalculator calls $loan->ledgerEntries
        //    This triggers 1 query per loan => 20 queries.
        // Total expected: ~23-25 queries.

        // Output for debugging
        // dump($queryCount);

        // Assertions
        // If optimized, we expect:
        // 1. Count
        // 2. Select loans
        // 3. Eager load clients
        // 4. Eager load ledgerEntries (filtered)
        // Total: ~4-5 queries.

        // For the baseline, we assert it IS high.
        // Once we fix it, we will assert it is low.
        // To make this test useful for both "before" (to confirm issue) and "after" (to confirm fix),
        // I will just print the count and assert the goal.
        // Since I'm in the "Measure" phase, I'll allow failure or just log it.
        // But the task says "Establish a Baseline".
        // I will make the test fail if it's NOT optimized yet, effectively TDD.
        // Or better: I will print the count and fail if > 10.

        $this->assertLessThan(10, $queryCount, "N+1 detected! Query count is {$queryCount}, expected < 10.");
    }
}
