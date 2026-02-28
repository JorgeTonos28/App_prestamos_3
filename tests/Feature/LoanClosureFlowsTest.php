<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanClosureFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_with_prior_operational_activity_becomes_written_off(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-15 09:00:00'));

        $user = User::factory()->create();
        $loan = Loan::factory()->create([
            'status' => 'active',
            'start_date' => '2026-01-01',
            'monthly_rate' => 12,
            'principal_initial' => 10000,
            'principal_outstanding' => 10000,
            'balance_total' => 10000,
            'interest_accrued' => 0,
            'fees_accrued' => 0,
            'installment_amount' => 1000,
            'enable_late_fees' => true,
            'late_fee_grace_period' => 0,
            'late_fee_daily_amount' => 50,
        ]);

        $this->actingAs($user)
            ->post(route('loans.cancel', $loan), [
                'reason' => 'Caso con actividad previa y cierre por castigo.',
            ])
            ->assertRedirect();

        $loan->refresh();

        $this->assertSame('written_off', $loan->status);
        $this->assertSame(0.0, round((float) $loan->balance_total, 2));
        $this->assertTrue($loan->ledgerEntries()->where('type', 'write_off')->exists());
    }

    public function test_payment_endpoint_rejects_written_off_loans(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->create([
            'status' => 'written_off',
            'balance_total' => 0,
            'principal_outstanding' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('loans.payments.store', $loan), [
                'amount' => 500,
                'method' => 'cash',
                'notes' => 'Intento inválido',
            ])
            ->assertForbidden();
    }

    public function test_consolidation_marks_source_loans_as_closed_refinanced_and_links_them(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00'));

        $user = User::factory()->create();
        $client = Client::factory()->create();

        $sourceA = Loan::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'start_date' => '2026-01-01',
            'principal_initial' => 6000,
            'principal_outstanding' => 6000,
            'balance_total' => 6000,
            'installment_amount' => 1200,
        ]);

        $sourceB = Loan::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'start_date' => '2026-01-15',
            'principal_initial' => 4000,
            'principal_outstanding' => 4000,
            'balance_total' => 4000,
            'installment_amount' => 800,
        ]);

        $this->actingAs($user)
            ->post(route('loans.store'), [
                'client_id' => $client->id,
                'start_date' => '2026-03-10',
                'principal_initial' => 10000,
                'modality' => 'monthly',
                'monthly_rate' => 10,
                'days_in_month_convention' => 30,
                'interest_mode' => 'simple',
                'target_term_periods' => 12,
                'installment_amount' => null,
                'consolidation_loan_ids' => [$sourceA->id, $sourceB->id],
            ])
            ->assertRedirect();

        $sourceA->refresh();
        $sourceB->refresh();

        $this->assertSame('closed_refinanced', $sourceA->status);
        $this->assertSame('closed_refinanced', $sourceB->status);
        $this->assertNotNull($sourceA->consolidated_into_loan_id);
        $this->assertSame($sourceA->consolidated_into_loan_id, $sourceB->consolidated_into_loan_id);

        $targetLoanId = $sourceA->consolidated_into_loan_id;

        $this->assertTrue(LoanLedgerEntry::where('loan_id', $sourceA->id)->where('type', 'refinance_payoff')->exists());
        $this->assertTrue(LoanLedgerEntry::where('loan_id', $sourceB->id)->where('type', 'refinance_payoff')->exists());
        $this->assertTrue(Loan::whereKey($targetLoanId)->exists());
    }

    public function test_closed_loans_can_be_archived_from_closed_tab(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->create([
            'status' => 'closed',
            'is_archived' => false,
        ]);

        $this->actingAs($user)
            ->post(route('loans.archive'), [
                'loan_ids' => [$loan->id],
                'source_tab' => 'closed',
            ])
            ->assertRedirect(route('loans.index', ['tab' => 'closed']));

        $loan->refresh();

        $this->assertTrue($loan->is_archived);
        $this->assertNotNull($loan->archived_at);
    }

    public function test_closed_tab_only_returns_non_archived_closed_loans(): void
    {
        $user = User::factory()->create();
        $closedVisible = Loan::factory()->create([
            'status' => 'closed',
            'is_archived' => false,
            'start_date' => now()->subDay(),
        ]);

        Loan::factory()->create([
            'status' => 'closed',
            'is_archived' => true,
            'start_date' => now()->subDay(),
        ]);

        Loan::factory()->create([
            'status' => 'cancelled',
            'is_archived' => false,
            'start_date' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('loans.index', ['tab' => 'closed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Loans/Index')
                ->where('filters.tab', 'closed')
                ->has('loans.data', 1)
                ->where('loans.data.0.id', $closedVisible->id)
            );
    }

}
