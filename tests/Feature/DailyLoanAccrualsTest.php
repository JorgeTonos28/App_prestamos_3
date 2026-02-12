<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Loan;
use App\Models\User;
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

        $lateFeeEntries = $loan->fresh()->ledgerEntries()
            ->where('type', 'fee_accrual')
            ->whereDate('occurred_at', '2026-01-10')
            ->get();

        $this->assertCount(1, $lateFeeEntries);
    }

    public function test_daily_accrual_command_posts_interest_and_fee_entries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-20 01:10:00'));

        $activeLoan = $this->makeLoan([
            'start_date' => '2026-01-01',
            'monthly_rate' => 12,
            'status' => 'active',
            'consolidated_into_loan_id' => null,
        ]);

        $this->artisan('loans:daily-accrual')
            ->assertSuccessful();

        $loan = $activeLoan->fresh();

        $this->assertTrue($loan->ledgerEntries()->where('type', 'interest_accrual')->exists());
        $this->assertTrue($loan->ledgerEntries()->where('type', 'fee_accrual')->exists());
    }

    public function test_payment_today_rebuilds_same_day_accrual_entries_without_duplicates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-14 10:00:00'));

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


    public function test_payment_generated_accruals_are_linked_to_triggering_payment(): void
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

        app(PaymentService::class)->registerPayment($loan->fresh(), Carbon::parse('2026-02-11'), 10000, 'cash');

        $payment = $loan->fresh()->payments()->latest('id')->firstOrFail();

        $this->assertGreaterThanOrEqual(1, $loan->fresh()->ledgerEntries()
            ->where('type', 'interest_accrual')
            ->where('triggered_by_payment_id', $payment->id)
            ->count());

        $this->assertGreaterThanOrEqual(1, $loan->fresh()->ledgerEntries()
            ->where('type', 'fee_accrual')
            ->where('triggered_by_payment_id', $payment->id)
            ->count());
    }

    public function test_delete_payment_removes_triggered_entries_and_keeps_ledger_balances_consistent(): void
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

        $paymentService = app(PaymentService::class);

        $paymentService->registerPayment($loan->fresh(), Carbon::parse('2025-11-14'), 8000, 'cash');
        $payment = $paymentService->registerPayment($loan->fresh(), now()->startOfDay(), 10000, 'cash');

        $paymentService->deletePayment($payment->fresh());

        $loan = $loan->fresh();

        $this->assertFalse($loan->ledgerEntries()->where('payment_id', $payment->id)->exists());
        $this->assertFalse($loan->ledgerEntries()->where('triggered_by_payment_id', $payment->id)->exists());

        $expectedPrincipalOutstanding = round((float) $loan->principal_initial + (float) $loan->ledgerEntries()->sum('principal_delta'), 2);
        $expectedBalance = round(
            $expectedPrincipalOutstanding
            + (float) $loan->ledgerEntries()->sum('interest_delta')
            + (float) $loan->ledgerEntries()->sum('fees_delta'),
            2
        );

        $this->assertSame($expectedPrincipalOutstanding, round((float) $loan->principal_outstanding, 2));
        $this->assertSame($expectedBalance, round((float) $loan->balance_total, 2));

    }

    public function test_register_then_delete_today_payment_restores_same_day_accrual_snapshot(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-11-14 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2025-10-14',
            'modality' => 'monthly',
            'monthly_rate' => 15,
            'installment_amount' => 8000,
            'principal_initial' => 25000,
            'principal_outstanding' => 25000,
            'balance_total' => 25000,
            'late_fee_daily_amount' => 100,
            'late_fee_grace_period' => 0,
            'enable_late_fees' => true,
        ]);

        $loan->ledgerEntries()->create([
            'type' => 'legal_fee',
            'occurred_at' => Carbon::parse('2025-10-14')->startOfDay(),
            'amount' => 1000,
            'principal_delta' => 0,
            'interest_delta' => 0,
            'fees_delta' => 1000,
            'balance_after' => 26000,
            'meta' => ['source' => 'test'],
        ]);

        $loan->update([
            'fees_accrued' => 1000,
            'balance_total' => 26000,
        ]);

        $paymentService = app(PaymentService::class);
        $interestEngine = app(InterestEngine::class);
        $lateFeeService = app(LateFeeService::class);

        $paymentService->registerPayment($loan->fresh(), Carbon::parse('2025-11-14'), 8000, 'cash');

        $loan->ledgerEntries()->create([
            'type' => 'legal_fee',
            'occurred_at' => Carbon::parse('2026-01-13')->startOfDay(),
            'amount' => 4000,
            'principal_delta' => 0,
            'interest_delta' => 0,
            'fees_delta' => 4000,
            'balance_after' => 25900,
            'meta' => ['source' => 'test'],
        ]);

        $loan->update([
            'fees_accrued' => (float) $loan->fresh()->fees_accrued + 4000,
            'balance_total' => (float) $loan->fresh()->balance_total + 4000,
        ]);

        $lateFeeService->checkAndAccrueLateFees($loan->fresh(), now()->startOfDay());
        $interestEngine->accrueUpTo($loan->fresh(), now()->startOfDay());

        $baselineLoan = $loan->fresh();
        $baseline = [
            'principal' => round((float) $baselineLoan->principal_outstanding, 2),
            'interest' => round((float) $baselineLoan->interest_accrued, 2),
            'fees' => round((float) $baselineLoan->fees_accrued, 2),
            'balance' => round((float) $baselineLoan->balance_total, 2),
            'today_fee_entries' => $baselineLoan->ledgerEntries()->whereDate('occurred_at', now()->toDateString())->where('type', 'fee_accrual')->count(),
            'today_interest_entries' => $baselineLoan->ledgerEntries()->whereDate('occurred_at', now()->toDateString())->where('type', 'interest_accrual')->count(),
        ];

        $newPayment = $paymentService->registerPayment($loan->fresh(), now()->startOfDay(), 10000, 'cash');

        $paymentService->deletePayment($newPayment->fresh());

        $afterDelete = $loan->fresh();

        $this->assertGreaterThan(0.0, round((float) $afterDelete->principal_outstanding, 2));
        $this->assertGreaterThanOrEqual(0.0, round((float) $afterDelete->interest_accrued, 2));
        $this->assertGreaterThanOrEqual(0.0, round((float) $afterDelete->fees_accrued, 2));
        $this->assertGreaterThan(0.0, round((float) $afterDelete->balance_total, 2));
        $this->assertSame($baseline['today_fee_entries'], $afterDelete->ledgerEntries()->whereDate('occurred_at', now()->toDateString())->where('type', 'fee_accrual')->count());
        $this->assertSame($baseline['today_interest_entries'], $afterDelete->ledgerEntries()->whereDate('occurred_at', now()->toDateString())->where('type', 'interest_accrual')->count());
    }

    public function test_payment_controller_roundtrip_does_not_duplicate_same_day_accruals(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 10:00:00'));

        $this->actingAs(User::factory()->create());

        $loan = $this->makeLoan([
            'start_date' => '2025-10-14',
            'modality' => 'monthly',
            'monthly_rate' => 15,
            'installment_amount' => 8000,
            'principal_initial' => 25000,
            'principal_outstanding' => 25000,
            'balance_total' => 25000,
            'late_fee_daily_amount' => 100,
            'late_fee_grace_period' => 0,
            'enable_late_fees' => true,
        ]);

        $paymentService = app(PaymentService::class);
        $paymentService->registerPayment($loan->fresh(), Carbon::parse('2025-11-14'), 8000, 'cash');

        Carbon::setTestNow(Carbon::parse('2026-02-12 10:00:00'));

        app(LateFeeService::class)->checkAndAccrueLateFees($loan->fresh(), now()->startOfDay());
        app(InterestEngine::class)->accrueUpTo($loan->fresh(), now()->startOfDay());

        $baseline = $loan->fresh();

        $this->post(route('loans.payments.store', $loan), [
            'amount' => 10000,
            'method' => 'cash',
            'paid_at' => now()->toDateString(),
        ])->assertRedirect();

        $payment = $loan->fresh()->payments()->latest('id')->firstOrFail();

        $this->delete(route('loans.payments.destroy', [$loan, $payment]))
            ->assertRedirect();

        $after = $loan->fresh();

        $this->assertSame(
            $baseline->ledgerEntries()->whereDate('occurred_at', now()->toDateString())->where('type', 'fee_accrual')->count(),
            $after->ledgerEntries()->whereDate('occurred_at', now()->toDateString())->where('type', 'fee_accrual')->count()
        );
        $this->assertSame(
            $baseline->ledgerEntries()->whereDate('occurred_at', now()->toDateString())->where('type', 'interest_accrual')->count(),
            $after->ledgerEntries()->whereDate('occurred_at', now()->toDateString())->where('type', 'interest_accrual')->count()
        );
        $this->assertSame(round((float) $baseline->balance_total, 2), round((float) $after->balance_total, 2));
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

    public function test_retroactive_payment_uses_pre_edit_baseline_even_with_future_legal_fee(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2026-01-01',
            'modality' => 'monthly',
            'monthly_rate' => 30,
            'installment_amount' => 200,
            'principal_initial' => 1000,
            'principal_outstanding' => 1000,
            'balance_total' => 1000,
            'interest_accrued' => 0,
            'fees_accrued' => 0,
            'enable_late_fees' => false,
        ]);

        // Entrada futura NO replayable que debe preservarse sin alterar baseline de devengo.
        $loan->ledgerEntries()->create([
            'type' => 'legal_fee',
            'occurred_at' => Carbon::parse('2026-01-20')->startOfDay(),
            'amount' => 100,
            'principal_delta' => 0,
            'interest_delta' => 0,
            'fees_delta' => 100,
            'balance_after' => 1100,
            'meta' => ['source' => 'test'],
        ]);

        $loan->update([
            'fees_accrued' => 100,
            'balance_total' => 1100,
        ]);

        $payment = app(PaymentService::class)->registerPayment(
            $loan->fresh(),
            Carbon::parse('2026-01-05'),
            100,
            'cash'
        );

        $this->assertSame(40.0, (float) $payment->fresh()->applied_interest);
    }

    public function test_delete_payment_replay_uses_pre_edit_baseline_even_with_future_legal_fee(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2026-01-01',
            'modality' => 'monthly',
            'monthly_rate' => 30,
            'installment_amount' => 200,
            'principal_initial' => 1000,
            'principal_outstanding' => 1000,
            'balance_total' => 1000,
            'interest_accrued' => 0,
            'fees_accrued' => 0,
            'enable_late_fees' => false,
        ]);

        $loan->ledgerEntries()->create([
            'type' => 'legal_fee',
            'occurred_at' => Carbon::parse('2026-02-01')->startOfDay(),
            'amount' => 100,
            'principal_delta' => 0,
            'interest_delta' => 0,
            'fees_delta' => 100,
            'balance_after' => 1100,
            'meta' => ['source' => 'test'],
        ]);

        $loan->update([
            'fees_accrued' => 100,
            'balance_total' => 1100,
        ]);

        $paymentService = app(PaymentService::class);
        $firstPayment = $paymentService->registerPayment($loan->fresh(), Carbon::parse('2026-01-10'), 200, 'cash');
        $paymentService->registerPayment($loan->fresh(), Carbon::parse('2026-01-25'), 200, 'cash');

        $paymentService->deletePayment($firstPayment->fresh());

        $remainingPayment = $loan->fresh()->payments()->whereDate('paid_at', '2026-01-25')->firstOrFail();
        $this->assertSame(200.0, (float) $remainingPayment->applied_interest);
    }

    public function test_payment_applies_interest_only_up_to_last_due_cutoff_when_in_arrears(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2025-10-14',
            'modality' => 'monthly',
            'monthly_rate' => 15,
            'installment_amount' => 8000,
            'principal_initial' => 25000,
            'principal_outstanding' => 25000,
            'balance_total' => 25000,
            'enable_late_fees' => false,
            'legal_auto_enabled' => false,
        ]);

        $paymentService = app(PaymentService::class);
        $paymentService->registerPayment($loan->fresh(), Carbon::parse('2025-11-14'), 8000, 'cash');

        $payment = $paymentService->registerPayment($loan->fresh(), Carbon::parse('2026-02-12'), 10000, 'cash');

        $this->assertGreaterThan(0.0, round((float) $payment->fresh()->applied_interest, 2));
        $this->assertGreaterThan(0.0, (float) $loan->fresh()->interest_accrued);
    }

    public function test_payment_outside_arrears_still_applies_interest_up_to_payment_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2026-01-01',
            'modality' => 'monthly',
            'monthly_rate' => 30,
            'installment_amount' => 200,
            'principal_initial' => 1000,
            'principal_outstanding' => 1000,
            'balance_total' => 1000,
            'enable_late_fees' => false,
        ]);

        $expectedInterest = app(InterestEngine::class)->calculatePendingInterest(
            $loan->fresh(),
            Carbon::parse('2026-01-15')
        );

        $payment = app(PaymentService::class)->registerPayment($loan->fresh(), Carbon::parse('2026-01-15'), 200, 'cash');

        $this->assertSame(round($expectedInterest, 2), round((float) $payment->fresh()->applied_interest, 2));
    }

    public function test_payment_applies_late_fees_only_up_to_last_due_cutoff_when_in_arrears(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2025-10-14',
            'modality' => 'monthly',
            'monthly_rate' => 15,
            'installment_amount' => 8000,
            'principal_initial' => 25000,
            'principal_outstanding' => 25000,
            'balance_total' => 25000,
            'enable_late_fees' => true,
            'late_fee_daily_amount' => 100,
            'late_fee_grace_period' => 0,
            'legal_auto_enabled' => false,
        ]);

        $paymentService = app(PaymentService::class);
        $paymentService->registerPayment($loan->fresh(), Carbon::parse('2025-11-14'), 8000, 'cash');

        // First unpaid installment date is 2025-12-14, cutoff date is 2026-01-14 (22 business days)
        $expectedLateFeesAtCutoff = 2200.0;

        $payment = $paymentService->registerPayment($loan->fresh(), Carbon::parse('2026-02-12'), 50000, 'cash');

        $this->assertSame($expectedLateFeesAtCutoff, round((float) $payment->fresh()->applied_fees, 2));
        $this->assertGreaterThan(0.0, (float) $loan->fresh()->fees_accrued);
    }

    public function test_loan_show_uses_persisted_interest_entries_without_dynamic_preview(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 10:00:00'));
        $this->actingAs(User::factory()->create());

        $loan = $this->makeLoan([
            'start_date' => '2025-10-14',
            'modality' => 'monthly',
            'monthly_rate' => 15,
            'installment_amount' => 8000,
            'principal_initial' => 25000,
            'principal_outstanding' => 25000,
            'balance_total' => 25000,
            'days_in_month_convention' => 30,
            'enable_late_fees' => false,
        ]);

        app(PaymentService::class)->registerPayment($loan->fresh(), Carbon::parse('2025-11-14'), 8000, 'cash');
        $this->artisan('loans:daily-accrual')->assertSuccessful();

        $response = $this->get(route('loans.show', $loan));
        $response->assertOk();

        $loanProp = $response->viewData('page')['props']['loan'];
        $entries = collect($loanProp['ledgerEntries'] ?? $loanProp['ledger_entries'] ?? []);

        $this->assertNull($entries->firstWhere('id', 'temp-interest'));

        $postedInterest = $entries
            ->where('type', 'interest_accrual')
            ->sortByDesc('occurred_at')
            ->first();

        $this->assertNotNull($postedInterest);
        $this->assertSame(30, (int) ($postedInterest['meta']['days'] ?? 0));
    }

    public function test_creating_past_loan_posts_accruals_up_to_today_immediately(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 10:00:00'));
        $this->actingAs(User::factory()->create());

        $client = Client::factory()->create();

        $this->post(route('loans.store'), [
            'client_id' => $client->id,
            'start_date' => '2025-10-14',
            'principal_initial' => 25000,
            'modality' => 'monthly',
            'monthly_rate' => 15,
            'days_in_month_convention' => 30,
            'interest_mode' => 'simple',
            'installment_amount' => 8000,
            'enable_late_fees' => true,
            'late_fee_daily_amount' => 100,
            'late_fee_grace_period' => 0,
            'legal_auto_enabled' => false,
        ])->assertRedirect();

        $loan = Loan::latest('id')->firstOrFail();

        $this->assertTrue($loan->ledgerEntries()->whereDate('occurred_at', '2026-01-14')->where('type', 'interest_accrual')->exists());
        $this->assertTrue($loan->ledgerEntries()->whereDate('occurred_at', '2026-01-14')->where('type', 'fee_accrual')->exists());
        $this->assertFalse($loan->ledgerEntries()->whereDate('occurred_at', now()->toDateString())->where('type', 'interest_accrual')->exists());
    }

    public function test_retroactive_payment_replays_and_posts_accruals_to_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 10:00:00'));

        $loan = $this->makeLoan([
            'start_date' => '2025-10-14',
            'modality' => 'monthly',
            'monthly_rate' => 15,
            'installment_amount' => 8000,
            'principal_initial' => 25000,
            'principal_outstanding' => 25000,
            'balance_total' => 25000,
            'enable_late_fees' => true,
            'late_fee_daily_amount' => 100,
            'late_fee_grace_period' => 0,
            'legal_auto_enabled' => false,
        ]);

        $paymentService = app(PaymentService::class);
        $paymentService->registerPayment($loan->fresh(), Carbon::parse('2026-02-11'), 1000, 'cash');

        $paymentService->registerPayment($loan->fresh(), Carbon::parse('2026-01-10'), 1000, 'cash');

        $this->assertTrue($loan->fresh()->ledgerEntries()
            ->whereDate('occurred_at', '2026-01-14')
            ->where('type', 'interest_accrual')
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
