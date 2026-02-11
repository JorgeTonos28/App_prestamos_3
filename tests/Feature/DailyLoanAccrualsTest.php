<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Loan;
use App\Services\InterestEngine;
use App\Services\LateFeeService;
use App\Services\PaymentService;
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

        $this->assertSame(3, $first['days']);
        $this->assertSame(225.0, (float) $first['amount']);
        $this->assertSame(0, $second['days']);
        $this->assertSame(0.0, (float) $second['amount']);

        $entries = $loan->fresh()->ledgerEntries()->where('type', 'fee_accrual')->get();

        $this->assertCount(1, $entries);
        $this->assertSame('2026-01-13', $entries->first()->meta['late_fee_date'] ?? null);
        $this->assertSame(3, (int) ($entries->first()->meta['late_fee_days'] ?? 0));
    }

    public function test_late_fee_legacy_as_of_entry_is_considered_for_idempotency(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-13 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2026-01-01',
            'modality' => 'weekly',
            'late_fee_grace_period' => 0,
            'late_fee_daily_amount' => 75,
            'enable_late_fees' => true,
        ]);

        $loan->ledgerEntries()->create([
            'type' => 'fee_accrual',
            'occurred_at' => now()->startOfDay(),
            'amount' => 225,
            'principal_delta' => 0,
            'interest_delta' => 0,
            'fees_delta' => 225,
            'balance_after' => 1225,
            'meta' => [
                'late_fee_days' => 3,
                'daily_amount' => 75,
                'as_of' => now()->toDateString(),
            ],
        ]);

        $loan->update([
            'fees_accrued' => 225,
            'balance_total' => 1225,
        ]);

        $result = app(LateFeeService::class)->checkAndAccrueLateFees($loan->fresh(), now());

        $this->assertSame(0, $result['days']);
        $this->assertSame(0.0, (float) $result['amount']);
        $this->assertCount(1, $loan->fresh()->ledgerEntries()->where('type', 'fee_accrual')->get());
    }

    public function test_retroactive_payment_does_not_backfill_daily_late_fee_entries_until_new_payment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-20 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2026-01-01',
            'modality' => 'weekly',
            'late_fee_grace_period' => 0,
            'late_fee_daily_amount' => 50,
            'installment_amount' => 100,
        ]);

        app(PaymentService::class)->registerPayment(
            $loan,
            Carbon::parse('2026-01-10'),
            100,
            'cash',
            null,
            'Pago retroactivo'
        );

        $lateFeeEntries = $loan->fresh()->ledgerEntries()->where('type', 'fee_accrual')->get();

        $this->assertCount(1, $lateFeeEntries);
        $this->assertSame('2026-01-10', Carbon::parse($lateFeeEntries->first()->occurred_at)->toDateString());
    }

    public function test_daily_accrual_command_skips_interest_and_fee_postings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-20 01:10:00'));

        $activeLoan = $this->makeLoan([
            'start_date' => '2026-01-01',
            'monthly_rate' => 12,
            'status' => 'active',
            'consolidated_into_loan_id' => null,
        ]);

        $this->artisan('loans:daily-accrual')
            ->assertSuccessful();

        $loan = $activeLoan->fresh();

        $this->assertFalse($loan->ledgerEntries()->where('type', 'interest_accrual')->exists());
        $this->assertFalse($loan->ledgerEntries()->where('type', 'fee_accrual')->exists());
    }

    public function test_payment_today_rebuilds_same_day_accrual_entries_without_duplicates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2025-10-14',
            'modality' => 'monthly',
            'monthly_rate' => 15,
            'installment_amount' => 8000,
            'principal_initial' => 25000,
            'principal_outstanding' => 25000,
            'balance_total' => 25000,
        ]);

        $paymentService = app(PaymentService::class);
        $interestEngine = app(InterestEngine::class);
        $lateFeeService = app(LateFeeService::class);

        $paymentService->registerPayment($loan, Carbon::parse('2025-11-14'), 8000, 'cash');

        // Simula entradas acumuladas del mismo día previas al nuevo pago.
        $interestEngine->accrueUpTo($loan->fresh(), now()->startOfDay());
        $lateFeeService->checkAndAccrueLateFees($loan->fresh(), now()->startOfDay());

        $this->assertSame(1, $loan->fresh()->ledgerEntries()
            ->whereDate('occurred_at', now()->toDateString())
            ->where('type', 'interest_accrual')
            ->count());

        $this->assertSame(1, $loan->fresh()->ledgerEntries()
            ->whereDate('occurred_at', now()->toDateString())
            ->where('type', 'fee_accrual')
            ->count());

        $paymentService->registerPayment($loan->fresh(), now()->startOfDay(), 10000, 'cash');

        $this->assertSame(1, $loan->fresh()->ledgerEntries()
            ->whereDate('occurred_at', now()->toDateString())
            ->where('type', 'interest_accrual')
            ->count());

        $this->assertSame(1, $loan->fresh()->ledgerEntries()
            ->whereDate('occurred_at', now()->toDateString())
            ->where('type', 'fee_accrual')
            ->count());
    }

    public function test_deleting_past_payment_replays_without_deleting_non_replayable_entries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2025-10-14',
            'modality' => 'monthly',
            'monthly_rate' => 15,
            'installment_amount' => 8000,
            'principal_initial' => 25000,
            'principal_outstanding' => 25000,
            'balance_total' => 25000,
            'late_fee_daily_amount' => 100,
            'enable_late_fees' => true,
        ]);

        $loan->ledgerEntries()->create([
            'type' => 'legal_fee',
            'occurred_at' => Carbon::parse('2026-01-13')->startOfDay(),
            'amount' => 4000,
            'principal_delta' => 0,
            'interest_delta' => 0,
            'fees_delta' => 4000,
            'balance_after' => 29000,
            'meta' => ['source' => 'test'],
        ]);

        $loan->update([
            'fees_accrued' => 4000,
            'balance_total' => 29000,
        ]);

        $paymentService = app(PaymentService::class);
        $firstPayment = $paymentService->registerPayment($loan->fresh(), Carbon::parse('2025-11-14'), 8000, 'cash');
        $paymentService->registerPayment($loan->fresh(), Carbon::parse('2026-02-11'), 10000, 'cash');

        $paymentService->deletePayment($firstPayment->fresh());

        $this->assertTrue($loan->fresh()->ledgerEntries()
            ->where('type', 'legal_fee')
            ->whereDate('occurred_at', '2026-01-13')
            ->exists());
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
