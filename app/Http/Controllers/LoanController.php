<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Loan;
use App\Services\InstallmentCalculator;
use App\Services\InterestEngine;
use App\Services\PaymentService;
use App\Services\ArrearsCalculator;
use App\Services\AmortizationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $query = Loan::with('client');

        // Text Filter (Code, Amount, Client Name)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('principal_initial', 'like', "%{$search}%")
                  ->orWhereHas('client', function($cq) use ($search) {
                      $cq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        // Date Filter (End Date / Cutoff)
        // User request: "registros desde este día hacia atrás... indefinidamente"
        // This implies: Created Date <= Selected Date.
        // Default to TODAY if not present? User said "fecha actual por defecto".
        $dateFilter = $request->input('date_filter', now()->toDateString());

        // We filter by start_date <= dateFilter
        $query->whereDate('start_date', '<=', $dateFilter);

        $loans = $query->latest()->get();

        return Inertia::render('Loans/Index', [
            'loans' => $loans,
            'filters' => [
                'search' => $request->input('search'),
                'date_filter' => $dateFilter
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('Loans/Create', [
            'clients' => Client::where('status', 'active')->orderBy('first_name')->get()
        ]);
    }

    public function store(Request $request, InstallmentCalculator $calculator, PaymentService $paymentService, AmortizationService $amortizationService)
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
            'installment_amount' => 'nullable|numeric|min:0.01', // User can provide this directly
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

        return DB::transaction(function () use ($validated, $calculator, $paymentService, $amortizationService) {

            $installment = 0;
            $termPeriods = $validated['target_term_periods'] ?? null;
            $maturityDate = null;

            // Logic A: Defined Quota (Fixed Installment) -> Calculate Term
            if (!empty($validated['installment_amount'])) {
                $installment = $validated['installment_amount'];

                // We need to calculate the term (maturity date) based on this quota
                $schedule = $amortizationService->generateSchedule(
                    $validated['principal_initial'],
                    $validated['monthly_rate'],
                    $validated['modality'],
                    $installment,
                    $validated['start_date'],
                    $validated['interest_mode'],
                    $validated['days_in_month_convention']
                );

                // Check if calculation failed (quota too low)
                if (isset($schedule['error'])) {
                     throw \Illuminate\Validation\ValidationException::withMessages([
                        'installment_amount' => $schedule['error']
                    ]);
                } else {
                     $lastItem = end($schedule);
                     if ($lastItem) {
                         $termPeriods = $lastItem['period'];
                         $maturityDate = Carbon::parse($lastItem['date']);
                     }
                }

            }
            // Logic B: Defined Term -> Calculate Quota (Old Way)
            elseif (!empty($validated['target_term_periods'])) {
                $installment = $calculator->calculateInstallment(
                    $validated['principal_initial'],
                    $validated['monthly_rate'],
                    $validated['modality'],
                    $validated['days_in_month_convention'],
                    $validated['target_term_periods']
                );
                $termPeriods = $validated['target_term_periods'];
            }

            // Clean validated data to remove historical_payments before creating model
            $loanData = collect($validated)->except('historical_payments')->toArray();

            $loan = new Loan($loanData);
            $loan->installment_amount = $installment;
            $loan->target_term_periods = $termPeriods; // Update derived term
            $loan->principal_outstanding = $validated['principal_initial'];
            $loan->balance_total = $validated['principal_initial'];

            // Ensure interest_base matches interest_mode logic
            $loan->interest_base = $validated['interest_mode'] === 'compound' ? 'total_balance' : 'principal';

            // Calculate Maturity Date if not already set by Amortization Service
            if (!$maturityDate && !empty($termPeriods)) {
                 $daysToAdd = 0;
                 $periods = (int) $termPeriods;
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

                 $maturityDate = Carbon::parse($validated['start_date'])->addDays($daysToAdd);
            }

            $loan->maturity_date = $maturityDate;

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

    public function show(Loan $loan, InterestEngine $interestEngine, AmortizationService $amortizationService)
    {
        // Accrue interest on view, BUT only up to the START of today.
        // This prevents intra-day changes causing re-accruals or fractional issues if logic was flawed.
        // Also it makes it idempotent for "today".
        // Use startOfDay() to ensure we are comparing dates, not times.
        $interestEngine->accrueUpTo($loan, now()->startOfDay());

        $loan->load(['client', 'ledgerEntries']);

        // Calculate arrears info
        $calculator = new ArrearsCalculator();
        $loan->arrears_info = $calculator->calculate($loan);

        // Generate projected schedule based on current balance
        $projectedSchedule = [];
        if ($loan->status === 'active' && $loan->balance_total > 0 && $loan->installment_amount > 0) {
            $projectedSchedule = $amortizationService->generateSchedule(
                $loan->balance_total, // Start from current balance
                $loan->monthly_rate,
                $loan->modality,
                $loan->installment_amount,
                now()->toDateString(), // Start projection from today
                $loan->interest_mode, // Or strict simple on outstanding
                $loan->days_in_month_convention ?: 30
            );
        }

        return Inertia::render('Loans/Show', [
            'loan' => $loan,
            'projected_schedule' => $projectedSchedule
        ]);
    }

    public function calculateAmortization(Request $request, AmortizationService $service)
    {
        $validated = $request->validate([
            'principal' => 'required|numeric',
            'monthly_rate' => 'required|numeric',
            'modality' => 'required|string',
            'installment_amount' => 'required|numeric',
            'start_date' => 'required|date',
            'interest_mode' => 'required|string',
            'days_in_month_convention' => 'required|integer'
        ]);

        $schedule = $service->generateSchedule(
            $validated['principal'],
            $validated['monthly_rate'],
            $validated['modality'],
            $validated['installment_amount'],
            $validated['start_date'],
            $validated['interest_mode'],
            $validated['days_in_month_convention']
        );

        return response()->json($schedule);
    }
}
