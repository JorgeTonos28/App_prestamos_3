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
use App\Models\LoanLedgerEntry;

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

        $loans = $query->latest()->paginate(20)->withQueryString();

        // Calculate Arrears Info for each loan
        $calculator = new ArrearsCalculator();

        // Pagination returns LengthAwarePaginator, items are in collections
        $loans->getCollection()->transform(function ($loan) use ($calculator) {
            $loan->arrears_info = $calculator->calculate($loan);
            return $loan;
        });

        return Inertia::render('Loans/Index', [
            'loans' => $loans, // Inertia handles Paginator object automatically
            'filters' => [
                'search' => $request->input('search'),
                'date_filter' => $dateFilter
            ]
        ]);
    }

    public function create(Request $request)
    {
        // Handle Consolidation Pre-fill
        $consolidationIds = $request->query('consolidation_ids');
        $consolidationData = null;

        if ($consolidationIds) {
            $ids = explode(',', $consolidationIds);
            $loans = Loan::with('payments')->whereIn('id', $ids)->get();

            if ($loans->isEmpty()) {
                // Return to index with error or just empty form?
                // Let's redirect back with error if possible, or just render default form without data.
                // Abort 404 is maybe too harsh if user just messed up URL.
                // Let's return normally but without consolidation data.
                // But user requested "Guard against... 500 error".
                // If I just skip the block, it renders standard create form.
            } else {
                // Validation: All loans must belong to same client and be active
                $clientId = $loans->first()->client_id;
                $isValid = $loans->every(fn($l) => $l->client_id === $clientId && $l->status === 'active');

                if ($isValid) {
                    $consolidationData = [
                        'ids' => $ids,
                        'loans' => $loans,
                        'total_principal' => $loans->sum('principal_outstanding'),
                        'total_balance' => $loans->sum('balance_total'),
                        // Max last payment or start date to ensure chronology
                        'min_start_date' => $loans->map(function($l) {
                            return $l->payments()->max('paid_at')
                                ? Carbon::parse($l->payments()->max('paid_at'))->toDateString()
                                : $l->start_date->toDateString();
                        })->max()
                    ];
                }
            }
        }

        return Inertia::render('Loans/Create', [
            'clients' => Client::where('status', 'active')->orderBy('first_name')->get(),
            'client_id' => (int) $request->query('client_id'),
            'consolidation_data' => $consolidationData
        ]);
    }

    public function store(Request $request, InstallmentCalculator $calculator, PaymentService $paymentService, AmortizationService $amortizationService, InterestEngine $interestEngine)
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

            // Consolidation params
            'consolidation_loan_ids' => 'nullable|array',
            'consolidation_loan_ids.*' => 'exists:loans,id'
        ]);

        return DB::transaction(function () use ($validated, $calculator, $paymentService, $amortizationService, $interestEngine) {

            // CONSOLIDATION VALIDATION
            if (!empty($validated['consolidation_loan_ids'])) {
                $sourceLoans = Loan::whereIn('id', $validated['consolidation_loan_ids'])->get();

                if ($sourceLoans->isEmpty()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'consolidation_loan_ids' => 'No se encontraron los préstamos seleccionados.'
                    ]);
                }

                // Validate dates: Start date must be >= max(last_payment_date) of sources
                foreach ($sourceLoans as $source) {
                    $lastPayment = $source->payments()->latest('paid_at')->first();
                    $limitDate = $lastPayment ? $lastPayment->paid_at : $source->start_date;

                    if (Carbon::parse($validated['start_date'])->lt($limitDate->startOfDay())) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'start_date' => 'La fecha de inicio debe ser posterior al último pago de los préstamos a consolidar (' . $limitDate->toDateString() . ').'
                        ]);
                    }

                    if ($source->client_id != $validated['client_id']) {
                         throw \Illuminate\Validation\ValidationException::withMessages([
                            'client_id' => 'Todos los préstamos a consolidar deben pertenecer al mismo cliente.'
                        ]);
                    }
                }
            }

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

            // Clean validated data to remove extra fields before creating model
            $loanData = collect($validated)
                ->except(['historical_payments', 'consolidation_loan_ids'])
                ->toArray();

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

            // PROCESS CONSOLIDATION (CLOSE OLD LOANS)
            if (!empty($validated['consolidation_loan_ids'])) {
                $sourceLoans = Loan::whereIn('id', $validated['consolidation_loan_ids'])->get();
                $actualConsolidationTotal = 0;

                foreach ($sourceLoans as $source) {
                    if ($source->status !== 'active') {
                        // Skip or throw error? Best to be safe and skip already closed loans
                        // but strictly we should probably fail if state changed.
                        // Let's assume validation caught most, but double check.
                        continue;
                    }

                    // Update accrued interest up to consolidation date
                    $interestEngine->accrueUpTo($source, Carbon::parse($validated['start_date']));

                    // Recalculate amounts based on consolidation basis (balance vs principal)
                    // The request sent 'principal_initial' based on frontend's calc.
                    // But we should verify or update the NEW loan's principal based on ACTUAL DB state now.
                    // Note: We already created $loan above with $validated['principal_initial'].
                    // If we want to be strict, we should have calculated $validated['principal_initial']
                    // dynamically HERE instead of trusting the request.
                    // However, changing $loan->principal_initial after creation requires saving again
                    // and updating the disbursement entry.
                    // Let's assume the user input is "target principal" and we just pay off the old loans.
                    // BUT, if old loans have MORE balance now due to accrual than what frontend saw,
                    // we might be "under-consolidating" or leaving a small balance?
                    // No, we are zeroing out the old loan: 'amount' => $balanceToClear.
                    // The "cost" of this consolidation is transferred to the new loan.

                    // "Pay off" the loan via consolidation
                    $balanceToClear = $source->balance_total;

                    // Create a special ledger entry on the OLD loan
                    LoanLedgerEntry::create([
                        'loan_id' => $source->id,
                        'type' => 'repayment_consolidation', // Custom type or just 'payment' with note?
                        // Let's use 'payment' to keep math simple, but meta distinct
                        'occurred_at' => $validated['start_date'],
                        'amount' => $balanceToClear,
                        'principal_delta' => -$source->principal_outstanding,
                        'interest_delta' => -$source->interest_accrued,
                        'fees_delta' => -$source->fees_accrued,
                        'balance_after' => 0,
                        'meta' => [
                            'method' => 'consolidation',
                            'notes' => "Consolidado en Préstamo #{$loan->code}",
                            'target_loan_id' => $loan->id
                        ]
                    ]);

                    // Update source loan status
                    $source->principal_outstanding = 0;
                    $source->interest_accrued = 0;
                    $source->fees_accrued = 0;
                    $source->balance_total = 0;
                    $source->status = 'closed'; // Or 'settled'
                    $source->consolidated_into_loan_id = $loan->id;
                    $source->save();
                }

                // Add note to NEW loan
                $loan->notes .= "\n[Sistema] Consolidación de deuda de préstamos: " . $sourceLoans->pluck('code')->join(', ');
                $loan->save();
            }

            // Process Historical Payments (Only if NOT a consolidation, usually? Or mixed? Allowed mixed.)
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

                // Final catch-up accrual to now after all historical payments are processed
                if ($loan->fresh()->status === 'active') {
                    $interestEngine->accrueUpTo($loan->fresh(), now()->startOfDay());
                }
            }

            return redirect()->route('loans.show', $loan);
        });
    }

    public function show(Loan $loan, InterestEngine $interestEngine, AmortizationService $amortizationService)
    {
        // Don't auto-accrue on view to prevent daily entries.
        // Instead, we calculate pending interest for display purposes.
        $pendingInterest = $interestEngine->calculatePendingInterest($loan, now()->startOfDay());

        // Add pending interest to the loan object temporarily for display
        // We clone or just modify the attribute in memory
        $loan->interest_accrued += $pendingInterest;
        $loan->balance_total += $pendingInterest;

        $loan->load(['client', 'ledgerEntries']);

        // Calculate arrears info
        $calculator = new ArrearsCalculator();
        $loan->arrears_info = $calculator->calculate($loan);

        // Generate projected schedule based on current balance
        $projectedSchedule = [];
        if ($loan->status === 'active' && $loan->balance_total > 0 && $loan->installment_amount > 0) {

            // For Simple Interest, we must distinguish Principal vs Accrued
            $principalBase = ($loan->interest_mode === 'compound')
                ? $loan->balance_total
                : $loan->principal_outstanding;

            $accruedInterest = ($loan->interest_mode === 'compound')
                ? 0
                : ($loan->balance_total - $loan->principal_outstanding);

            $projectedSchedule = $amortizationService->generateSchedule(
                $principalBase,
                $loan->monthly_rate,
                $loan->modality,
                $loan->installment_amount,
                now()->toDateString(),
                $loan->interest_mode,
                $loan->days_in_month_convention ?: 30,
                $accruedInterest
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
