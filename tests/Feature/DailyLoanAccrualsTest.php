<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Loan;
use App\Services\LateFeeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyLoanAccrualsTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_fee_daily_accrual_is_idempotent_per_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-13 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2026-01-01',
            'modality' => 'weekly',
            'late_fee_grace_period' => 0,
            'late_fee_daily_amount' => 75,
            'enable_late_fees' => true,
        ]);

        $service = app(LateFeeService::class);

        $first = $service->checkAndAccrueLateFees($loan, now());
        $second = $service->checkAndAccrueLateFees($loan->fresh(), now());

        $this->assertSame(1, $first['days']);
        $this->assertSame(75.0, (float) $first['amount']);
        $this->assertSame(0, $second['days']);
        $this->assertSame(0.0, (float) $second['amount']);

        $entries = $loan->fresh()->ledgerEntries()->where('type', 'fee_accrual')->get();

        $this->assertCount(1, $entries);
        $this->assertSame('2026-01-13', $entries->first()->meta['late_fee_date'] ?? null);
    }

    public function test_daily_accrual_command_skips_consolidated_loans(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-20 01:10:00'));

        $activeLoan = $this->makeLoan([
            'start_date' => '2026-01-01',
            'monthly_rate' => 12,
            'status' => 'active',
            'consolidated_into_loan_id' => null,
        ]);

        $consolidatedLoan = $this->makeLoan([
            'start_date' => '2026-01-01',
            'monthly_rate' => 12,
            'status' => 'active',
            'consolidated_into_loan_id' => $activeLoan->id,
        ]);

        $this->artisan('loans:daily-accrual')
            ->assertSuccessful();

        $this->assertTrue($activeLoan->fresh()->ledgerEntries()->where('type', 'interest_accrual')->exists());
        $this->assertFalse($consolidatedLoan->fresh()->ledgerEntries()->where('type', 'interest_accrual')->exists());
    }

    private function makeLoan(array $overrides = []): Loan
    {
        $client = Client::factory()->create();

        return Loan::create(array_merge([
            'client_id' => $client->id,
            'code' => 'TEST-'.uniqid(),
            'status' => 'active',
            'start_date' => now()->subDays(20)->toDateString(),
            'modality' => 'daily',
            'monthly_rate' => 5,
            'interest_mode' => 'simple',
            'interest_base' => 'principal',
            'days_in_month_convention' => 30,
            'installment_amount' => 100,
            'principal_initial' => 1000,
            'principal_outstanding' => 1000,
            'interest_accrued' => 0,
            'fees_accrued' => 0,
            'balance_total' => 1000,
            'enable_late_fees' => true,
            'late_fee_daily_amount' => 50,
            'late_fee_grace_period' => 0,
        ], $overrides));
    }
}
