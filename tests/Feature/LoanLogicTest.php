<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Loan;
use App\Models\User;
use App\Services\AmortizationService;
use App\Services\ArrearsCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LoanLogicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create user for auth if needed
        $this->user = User::factory()->create();
    }

    /** @test */
    public function arrears_calculator_respects_start_of_day_for_overdue()
    {
        // Scenario: Loan created with start date TODAY.
        // Modality: Monthly.
        // First due date: Today + 1 month.
        // It should NOT be overdue.

        $client = Client::factory()->create();

        $loan = Loan::create([
            'client_id' => $client->id,
            'code' => 'TEST-001',
            'start_date' => now()->toDateString(), // 00:00:00
            'principal_initial' => 10000,
            'principal_outstanding' => 10000,
            'balance_total' => 10000,
            'monthly_rate' => 5,
            'modality' => 'monthly',
            'interest_mode' => 'simple',
            'installment_amount' => 1000,
            'status' => 'active'
        ]);

        $calculator = new ArrearsCalculator();
        $arrears = $calculator->calculate($loan);

        // Expect count 0
        $this->assertEquals(0, $arrears['count'], 'Loan starting today should have 0 arrears.');
    }

    /** @test */
    public function arrears_calculator_counts_past_due_correctly()
    {
        // Scenario: Loan start date 2 months ago. Modality Monthly.
        // Due dates: 1 month ago, Today (maybe).
        // If "Now" is "Today 10:00 AM".
        // Start Date: 2023-01-01 (Assuming today is 2023-03-01)
        // Due 1: 2023-02-01 (Past)
        // Due 2: 2023-03-01 (Today)

        // Strict logic in ArrearsCalculator: while (due < now->startOfDay())
        // So 2023-02-01 < 2023-03-01 is TRUE. (Count 1)
        // 2023-03-01 < 2023-03-01 is FALSE. (Count 0 for today)
        // Total should be 1.

        // Let's force dates.
        Carbon::setTestNow(Carbon::parse('2023-03-01 10:00:00'));

        $client = Client::factory()->create();
        $startDate = Carbon::parse('2023-01-01'); // 2 months ago

        $loan = Loan::create([
            'client_id' => $client->id,
            'code' => 'TEST-002',
            'start_date' => $startDate->toDateString(),
            'principal_initial' => 10000,
            'principal_outstanding' => 10000,
            'balance_total' => 10000,
            'monthly_rate' => 5,
            'modality' => 'monthly',
            'interest_mode' => 'simple',
            'installment_amount' => 1000,
            'status' => 'active'
        ]);

        $calculator = new ArrearsCalculator();
        $arrears = $calculator->calculate($loan);

        // Expected: Due on Feb 1st (Overdue). Due on Mar 1st (Not overdue yet).
        $this->assertEquals(1, $arrears['count']);
    }

    /** @test */
    public function amortization_service_generates_schedule()
    {
        $service = new AmortizationService();

        // Principal 10,000. Rate 5% monthly. Installment 1,000.
        // Interest per month ~ 500. Principal pay ~ 500.
        // Term approx 20 months.

        $schedule = $service->generateSchedule(
            10000,
            5,
            'monthly',
            1000,
            '2023-01-01'
        );

        $this->assertIsArray($schedule);
        $this->assertNotEmpty($schedule);

        $firstRow = $schedule[0];
        $this->assertEquals(1, $firstRow['period']);
        $this->assertEquals(500, $firstRow['interest']); // 10000 * 0.05
        $this->assertEquals(500, $firstRow['principal']); // 1000 - 500
        $this->assertEquals(9500, $firstRow['balance']);
    }

    /** @test */
    public function arrears_calculator_respects_as_of_cutoff_for_payments()
    {
        $client = Client::factory()->create();

        $loan = Loan::create([
            'client_id' => $client->id,
            'code' => 'TEST-003',
            'start_date' => '2026-01-01',
            'principal_initial' => 1000,
            'principal_outstanding' => 1000,
            'balance_total' => 1000,
            'monthly_rate' => 5,
            'modality' => 'monthly',
            'interest_mode' => 'simple',
            'installment_amount' => 100,
            'status' => 'active'
        ]);

        $loan->ledgerEntries()->create([
            'type' => 'payment',
            'occurred_at' => '2026-03-01',
            'amount' => 100,
            'principal_delta' => -100,
            'interest_delta' => 0,
            'fees_delta' => 0,
            'balance_after' => 900,
            'meta' => ['source' => 'test'],
        ]);

        $calculator = new ArrearsCalculator();
        $arrears = $calculator->calculate($loan, Carbon::parse('2026-02-15'));

        $this->assertSame(100.0, (float) $arrears['amount']);
        $this->assertSame(0.0, (float) $arrears['paid_to_date']);
        $this->assertSame(14, (int) $arrears['days']);
    }

    /** @test */
    public function arrears_calculator_uses_installment_net_paid_without_fees()
    {
        $client = Client::factory()->create();

        $loan = Loan::create([
            'client_id' => $client->id,
            'code' => 'TEST-004',
            'start_date' => '2026-01-01',
            'principal_initial' => 1000,
            'principal_outstanding' => 1000,
            'balance_total' => 1000,
            'monthly_rate' => 5,
            'modality' => 'monthly',
            'interest_mode' => 'simple',
            'installment_amount' => 100,
            'status' => 'active'
        ]);

        $loan->ledgerEntries()->create([
            'type' => 'payment',
            'occurred_at' => '2026-02-10',
            'amount' => 100,
            'principal_delta' => -80,
            'interest_delta' => 0,
            'fees_delta' => -20,
            'balance_after' => 920,
            'meta' => [
                'source' => 'test',
                'payment_breakdown' => [
                    'late_fee' => ['paid' => 20, 'remaining' => 0],
                ],
            ],
        ]);

        $calculator = new ArrearsCalculator();
        $arrears = $calculator->calculate($loan, Carbon::parse('2026-02-15'));

        $this->assertSame(0.0, (float) $arrears['amount']);
        $this->assertSame(100.0, (float) $arrears['paid_to_date']);
        $this->assertSame(100.0, (float) $arrears['paid_gross_to_date']);
        $this->assertNull($arrears['first_unpaid_date']);
    }


    public function test_arrears_for_biweekly_uses_15_day_periods(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 10:00:00'));

        $client = Client::factory()->create();

        $loan = Loan::create([
            'client_id' => $client->id,
            'code' => 'TEST-BI-001',
            'start_date' => '2026-01-01',
            'principal_initial' => 10000,
            'principal_outstanding' => 10000,
            'balance_total' => 10000,
            'monthly_rate' => 5,
            'modality' => 'biweekly',
            'interest_mode' => 'simple',
            'installment_amount' => 1000,
            'status' => 'active',
            'days_in_period_biweekly' => 15,
        ]);

        $arrears = (new ArrearsCalculator())->calculate($loan);

        $this->assertSame(0.0, (float) $arrears['count']);
    }

    public function test_cutoff_only_mode_skips_same_day_accrual_on_payment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 10:00:00'));

        $client = Client::factory()->create();
        $loan = Loan::create([
            'client_id' => $client->id,
            'code' => 'TEST-CUT-001',
            'start_date' => '2026-01-01',
            'principal_initial' => 10000,
            'principal_outstanding' => 10000,
            'balance_total' => 10000,
            'monthly_rate' => 10,
            'modality' => 'monthly',
            'interest_mode' => 'simple',
            'installment_amount' => 1000,
            'status' => 'active',
            'payment_accrual_mode' => 'cutoff_only',
            'last_accrual_date' => '2026-01-01',
        ]);

        app(\App\Services\PaymentService::class)->registerPayment($loan, Carbon::parse('2026-01-10'), 1000, 'cash');

        $this->assertSame(
            0,
            $loan->fresh()->ledgerEntries()->where('type', 'interest_accrual')->whereDate('occurred_at', '2026-01-10')->count()
        );
    }


    public function test_cutoff_only_keeps_full_period_days_even_if_payment_between_cutoffs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-31 10:00:00'));

        $client = Client::factory()->create();
        $loan = Loan::create([
            'client_id' => $client->id,
            'code' => 'TEST-CUT-002',
            'start_date' => '2025-11-24',
            'principal_initial' => 15000,
            'principal_outstanding' => 15000,
            'balance_total' => 15000,
            'monthly_rate' => 20,
            'modality' => 'biweekly',
            'interest_mode' => 'simple',
            'installment_amount' => 3400,
            'status' => 'active',
            'payment_accrual_mode' => 'cutoff_only',
            'late_fee_cutoff_mode' => 'fixed_cutoff',
            'cutoff_anchor_date' => '2025-11-30',
            'cutoff_cycle_mode' => 'fixed_dates',
            'last_accrual_date' => '2025-12-15',
        ]);

        $loan->ledgerEntries()->create([
            'type' => 'payment',
            'occurred_at' => '2025-12-17',
            'amount' => 3400,
            'principal_delta' => -1000,
            'interest_delta' => -2400,
            'fees_delta' => 0,
            'balance_after' => 12600,
            'meta' => ['source' => 'test'],
        ]);

        app(\App\Services\PaymentService::class)->registerPayment($loan->fresh(), Carbon::parse('2025-12-29'), 3400, 'cash');

        $cutoffAccrual = $loan->fresh()->ledgerEntries()
            ->where('type', 'interest_accrual')
            ->whereDate('occurred_at', '2025-12-30')
            ->latest('id')
            ->first();

        $this->assertNotNull($cutoffAccrual);
        $this->assertSame(15, (int) data_get($cutoffAccrual->meta, 'days'));
    }

}