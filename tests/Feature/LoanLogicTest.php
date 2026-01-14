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
}
