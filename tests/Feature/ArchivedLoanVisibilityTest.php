<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArchivedLoanVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_profile_excludes_archived_loans_from_listing_and_stats(): void
    {
        $this->actingAs(User::factory()->create());

        $client = Client::factory()->create();

        $visibleLoan = Loan::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'is_archived' => false,
            'start_date' => '2026-03-01',
            'principal_initial' => 1000,
            'principal_outstanding' => 1000,
            'balance_total' => 1000,
            'monthly_rate' => 5,
            'modality' => 'monthly',
            'interest_mode' => 'simple',
            'installment_amount' => 100,
        ]);

        $archivedLoan = Loan::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'is_archived' => true,
            'start_date' => '2026-01-01',
            'principal_initial' => 9000,
            'principal_outstanding' => 9000,
            'balance_total' => 9000,
            'monthly_rate' => 5,
            'modality' => 'monthly',
            'interest_mode' => 'simple',
            'installment_amount' => 100,
        ]);

        LoanLedgerEntry::create([
            'loan_id' => $visibleLoan->id,
            'type' => 'payment',
            'occurred_at' => '2026-03-10',
            'amount' => 50,
            'principal_delta' => 0,
            'interest_delta' => -50,
            'fees_delta' => 0,
            'balance_after' => 950,
            'meta' => ['source' => 'test'],
        ]);

        LoanLedgerEntry::create([
            'loan_id' => $archivedLoan->id,
            'type' => 'payment',
            'occurred_at' => '2026-03-10',
            'amount' => 500,
            'principal_delta' => -450,
            'interest_delta' => -50,
            'fees_delta' => 0,
            'balance_after' => 8500,
            'meta' => ['source' => 'test'],
        ]);

        Payment::create([
            'loan_id' => $visibleLoan->id,
            'client_id' => $client->id,
            'paid_at' => '2026-03-10',
            'amount' => 50,
            'method' => 'cash',
            'reference' => null,
            'applied_interest' => 50,
            'applied_principal' => 0,
            'applied_fees' => 0,
            'notes' => 'visible',
        ]);

        Payment::create([
            'loan_id' => $archivedLoan->id,
            'client_id' => $client->id,
            'paid_at' => '2026-03-10',
            'amount' => 500,
            'method' => 'cash',
            'reference' => null,
            'applied_interest' => 50,
            'applied_principal' => 450,
            'applied_fees' => 0,
            'notes' => 'archived',
        ]);

        $this->get(route('clients.show', $client))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Show')
                ->has('client.loans', 1)
                ->where('client.loans.0.id', $visibleLoan->id)
                ->where('stats.total_loans', 1)
                ->where('stats.total_borrowed', 1000)
                ->where('stats.total_paid', 50)
                ->where('stats.total_interest_paid', 50)
                ->where('stats.current_arrears_count', 0)
            );
    }

    public function test_dashboard_excludes_archived_loans_from_stats(): void
    {
        $this->actingAs(User::factory()->create());

        $client = Client::factory()->create();

        $visibleLoan = Loan::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'is_archived' => false,
            'start_date' => now()->toDateString(),
            'principal_initial' => 1000,
            'principal_outstanding' => 1000,
            'balance_total' => 1000,
            'monthly_rate' => 5,
            'modality' => 'monthly',
            'interest_mode' => 'simple',
            'installment_amount' => 100,
        ]);

        $archivedLoan = Loan::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'is_archived' => true,
            'start_date' => now()->subDays(10)->toDateString(),
            'principal_initial' => 9000,
            'principal_outstanding' => 9000,
            'balance_total' => 9000,
            'monthly_rate' => 30,
            'modality' => 'weekly',
            'interest_mode' => 'simple',
            'installment_amount' => 100,
        ]);

        LoanLedgerEntry::create([
            'loan_id' => $visibleLoan->id,
            'type' => 'payment',
            'occurred_at' => now()->toDateString(),
            'amount' => 100,
            'principal_delta' => 0,
            'interest_delta' => -100,
            'fees_delta' => 0,
            'balance_after' => 900,
            'meta' => ['source' => 'test'],
        ]);

        Payment::create([
            'loan_id' => $visibleLoan->id,
            'client_id' => $client->id,
            'paid_at' => now()->toDateString(),
            'amount' => 100,
            'method' => 'cash',
            'reference' => null,
            'applied_interest' => 100,
            'applied_principal' => 0,
            'applied_fees' => 0,
            'notes' => 'visible',
        ]);

        Payment::create([
            'loan_id' => $archivedLoan->id,
            'client_id' => $client->id,
            'paid_at' => now()->toDateString(),
            'amount' => 700,
            'method' => 'cash',
            'reference' => null,
            'applied_interest' => 0,
            'applied_principal' => 700,
            'applied_fees' => 0,
            'notes' => 'archived',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.active_loans_count', 1)
                ->where('stats.portfolio_principal', 1000)
                ->where('stats.cash_income_month', 100)
                ->where('stats.loans_in_arrears_count', 0)
            );
    }
}
