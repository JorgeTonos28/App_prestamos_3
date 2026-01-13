<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Loan;
use App\Services\InstallmentCalculator;
use App\Services\InterestEngine;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index()
    {
        $loans = Loan::with('client')->latest()->get();
        return Inertia::render('Loans/Index', [
            'loans' => $loans
        ]);
    }

    public function create()
    {
        return Inertia::render('Loans/Create', [
            'clients' => Client::where('status', 'active')->orderBy('first_name')->get()
        ]);
    }

    public function store(Request $request, InstallmentCalculator $calculator, PaymentService $paymentService)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'code' => 'required|unique:loans',
            'start_date' => 'required|date',
            'principal_initial' => 'required|numeric|min:1',
            'modality' => 'required|in:daily,weekly,biweekly,monthly',
            'monthly_rate' => 'required|numeric|min:0',
            'days_in_month_convention' => 'required|integer|in:30,31', // Simple choice for now
            'interest_mode' => 'required|in:simple,compound',
            'target_term_periods' => 'nullable|integer|min:1',
            'notes' => 'nullable',
            // Historical Payments Validation
            'historical_payments' => 'nullable|array',
            'historical_payments.*.date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                'before_or_equal:today'
            ],
            'historical_payments.*.amount' => 'required|numeric|min:0.01',
            'historical_payments.*.method' => 'required|string',
            'historical_payments.*.reference' => 'nullable|string',
            'historical_payments.*.notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $calculator, $paymentService) {
            // Calculate installment
            $installment = $calculator->calculateInstallment(
                $validated['principal_initial'],
                $validated['monthly_rate'],
                $validated['modality'],
                $validated['days_in_month_convention'],
                $validated['target_term_periods'] ?? null
            );

            $loan = new Loan($validated);
            $loan->installment_amount = $installment;
            $loan->principal_outstanding = $validated['principal_initial'];
            $loan->balance_total = $validated['principal_initial'];

            // Ensure interest_base matches interest_mode logic
            $loan->interest_base = $validated['interest_mode'] === 'compound' ? 'total_balance' : 'principal';

            // Calculate Maturity Date
            if (!empty($validated['target_term_periods'])) {
                 $daysToAdd = 0;
                 $periods = (int) $validated['target_term_periods'];
                 $convention = (int) $validated['days_in_month_convention'];

                 if ($validated['modality'] === 'daily') {
                     $daysToAdd = $periods * 1;
                 } elseif ($validated['modality'] === 'weekly') {
                     $daysToAdd = $periods * 7; // Default
                 } elseif ($validated['modality'] === 'biweekly') {
                     $daysToAdd = $periods * 15; // Default
                 } elseif ($validated['modality'] === 'monthly') {
                     $daysToAdd = $periods * $convention;
                 }

                 $loan->maturity_date = Carbon::parse($validated['start_date'])->addDays($daysToAdd);
            }

            $loan->status = 'active'; // Auto-activate
            $loan->save();

            // Create Disbursement Ledger Entry
            $loan->ledgerEntries()->create([
                'type' => 'disbursement',
                'occurred_at' => $validated['start_date'],
                'amount' => $validated['principal_initial'],
                'principal_delta' => $validated['principal_initial'],
                'interest_delta' => 0,
                'fees_delta' => 0,
                'balance_after' => $validated['principal_initial'],
                'meta' => ['auto_created' => true]
            ]);

            // Process Historical Payments
            if (!empty($validated['historical_payments'])) {
                // Sort by date to be safe
                $payments = collect($validated['historical_payments'])->sortBy('date');

                // Safety check: Avoid immediate close or negative logic if input is garbage
                // Sum of payments shouldn't technically exceed Total Initial Debt + some interest buffer
                // But exact interest is unknown until calculation.
                // We rely on PaymentService to cap principal payment.

                foreach ($payments as $paymentData) {
                     // Stop processing if loan is already closed (paid off)
                     if ($loan->fresh()->status === 'closed') {
                         break;
                     }

                     $paymentService->registerPayment(
                         $loan,
                         Carbon::parse($paymentData['date']),
                         $paymentData['amount'],
                         $paymentData['method'],
                         $paymentData['reference'] ?? null,
                         $paymentData['notes'] ?? 'Pago histórico al crear préstamo'
                     );
                }
            }

            return redirect()->route('loans.show', $loan);
        });
    }

    public function show(Loan $loan, InterestEngine $interestEngine)
    {
        // Accrue interest on view, BUT only up to the START of today.
        // This prevents intra-day changes causing re-accruals or fractional issues if logic was flawed.
        // Also it makes it idempotent for "today".
        // Use startOfDay() to ensure we are comparing dates, not times.
        $interestEngine->accrueUpTo($loan, now()->startOfDay());

        $loan->load(['client', 'ledgerEntries']);

        return Inertia::render('Loans/Show', [
            'loan' => $loan
        ]);
    }
}
