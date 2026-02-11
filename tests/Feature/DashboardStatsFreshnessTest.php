<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardStatsFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_cash_and_bank_cards_refresh_after_new_payments(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::factory()->create();
        $loan = Loan::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'start_date' => now()->startOfMonth()->toDateString(),
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.cash_income_month', 0)
                ->where('stats.bank_income_month', 0)
            );

        Payment::create([
            'loan_id' => $loan->id,
            'client_id' => $client->id,
            'paid_at' => now()->subDays(3),
            'amount' => 1200,
            'method' => 'cash',
            'reference' => null,
            'applied_interest' => 0,
            'applied_principal' => 0,
            'applied_fees' => 0,
            'notes' => 'test cash',
        ]);

        Payment::create([
            'loan_id' => $loan->id,
            'client_id' => $client->id,
            'paid_at' => now(),
            'amount' => 800,
            'method' => 'transfer',
            'reference' => null,
            'applied_interest' => 0,
            'applied_principal' => 0,
            'applied_fees' => 0,
            'notes' => 'test bank',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.cash_income_month', 1200)
                ->where('stats.bank_income_month', 800)
            );
    }
}
