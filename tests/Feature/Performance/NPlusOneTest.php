<?php

namespace Tests\Feature\Performance;

use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NPlusOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_show_n_plus_one()
    {
        // Authenticate
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a client
        $client = Client::factory()->create();

        // Create 5 active loans for the client
        $loans = Loan::factory()->count(5)->create([
            'client_id' => $client->id,
            'status' => 'active',
            'start_date' => now()->subMonths(2),
            'installment_amount' => 100,
            'modality' => 'monthly',
        ]);

        // Add ledger entries for each loan to ensure calculator does something
        foreach ($loans as $loan) {
            // Add a disbursement entry (should be ignored by eager load, but shouldn't break calc)
            LoanLedgerEntry::factory()->create([
                'loan_id' => $loan->id,
                'type' => 'disbursement',
                'amount' => 1000,
            ]);

            LoanLedgerEntry::factory()->create([
                'loan_id' => $loan->id,
                'type' => 'payment',
                'amount' => 50,
            ]);
        }

        // Enable query logging
        DB::enableQueryLog();

        // Visit the page
        $response = $this->get(route('clients.show', $client));
        $response->assertOk();

        // Verify that the arrears calculation is correct (not broken by optimization)
        // With 2 months passed and 50 paid, arrears should be > 0.
        $response->assertInertia(fn ($page) => $page
            ->component('Clients/Show')
            ->has('client.loans', 5)
            ->where('client.loans.0.arrears_info.amount', fn ($amount) => $amount > 0)
        );

        // Get the queries
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // We expect N+1 queries.
        // 1 (Client) + 1 (Loans) + 1 (Total Interest) + 1 (Total Paid) + 5 (Ledger Entries per loan) = ~9 queries

        // If optimized, we expect:
        // 1 (Client) + 1 (Loans with Ledger Entries) + 1 (Total Interest) + 1 (Total Paid) = ~4 queries
        // Or if lazy loading ledger entries happens differently, maybe 5.

        // Assert that we have OPTIMIZED the N+1 problem
        // With optimization, it should be constant (around 5-7) regardless of loan count.
        // Baseline was 11. New count is ~7.
        $this->assertLessThan(8, $queryCount, "Optimization failed: query count ($queryCount) is too high. N+1 problem likely persists.");
    }
}
