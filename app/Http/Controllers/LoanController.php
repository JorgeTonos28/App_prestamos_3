<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Loan;
use App\Services\InstallmentCalculator;
use App\Services\InterestEngine;
use App\Services\PaymentService;
use App\Services\ArrearsCalculator;
use App\Services\AmortizationService;
use App\Services\LegalStatusService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\LoanLedgerEntry;
use App\Models\Setting;
use App\Helpers\FinancialHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $query = Loan::with(['client', 'ledgerEntries' => function ($q) {
            $q->where('type', 'payment');
        }]);

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

        $legalOnly = $request->boolean('legal_filter');
        if ($legalOnly) {
            $query->where('legal_status', true);
        }

        $loans = $query->latest()->paginate(20)->withQueryString();

        // Calculate Arrears Info for each loan
        $calculator = new ArrearsCalculator();

        // Pagination returns LengthAwarePaginator, items are in collections
        $loans->getCollection()->transform(function ($loan) use ($calculator) {
            $loan->arrears_info = $calculator->calculate($loan);
            // Optimization: Remove ledgerEntries from the response to reduce payload size
            // since it was only needed for calculation
            $loan->unsetRelation('ledgerEntries');
            return $loan;
        });

        return Inertia::render('Loans/Index', [
            'loans' => $loans, // Inertia handles Paginator object automatically
            'filters' => [
                'search' => $request->input('search'),
                'date_filter' => $dateFilter,
                'legal_filter' => $legalOnly,
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

    public function store(Request $request, InstallmentCalculator $calculator, PaymentService $paymentService, AmortizationService $amortizationService, InterestEngine $interestEngine, LegalStatusService $legalStatusService)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'start_date' => 'required|date',
            'principal_initial' => 'required|numeric|min:1',
            'modality' => 'required|in:daily,weekly,biweekly,monthly',
            'monthly_rate' => 'required|numeric|min:0',
            'days_in_month_convention' => 'required|integer|in:30,31', // Simple choice for now
            'interest_mode' => 'required|in:simple,compound',
            'target_term_periods' => 'nullable|integer|min:1',
            'installment_amount' => 'nullable|numeric|min:0.01', // User can provide this directly
            'notes' => 'nullable',
            'enable_late_fees' => 'nullable|boolean',
            'late_fee_daily_amount' => 'nullable|numeric|min:0',
            'late_fee_grace_period' => 'nullable|integer|min:0',
            'late_fee_cutoff_mode' => 'nullable|in:dynamic_payment,fixed_cutoff',
            'payment_accrual_mode' => 'nullable|in:realtime,cutoff_only',
            'cutoff_anchor_date' => 'nullable|date|after_or_equal:start_date',
            'legal_fee_enabled' => 'nullable|boolean',
            'legal_fee_amount' => 'nullable|numeric|min:0',
            'legal_fee_financed' => 'nullable|boolean',
            'legal_auto_enabled' => 'nullable|boolean',
            'legal_days_overdue_threshold' => 'nullable|integer|min:0',
            'legal_entry_fee_amount' => 'nullable|numeric|min:0',
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

        try {
            return DB::transaction(function () use ($validated, $calculator, $paymentService, $amortizationService, $interestEngine, $legalStatusService) {
                $validated['enable_late_fees'] = (bool) ($validated['enable_late_fees'] ?? false);
                $validated['legal_fee_enabled'] = (bool) ($validated['legal_fee_enabled'] ?? false);
                $validated['legal_fee_financed'] = (bool) ($validated['legal_fee_financed'] ?? false);
                $validated['legal_auto_enabled'] = (bool) ($validated['legal_auto_enabled'] ?? true);
                $validated['late_fee_cutoff_mode'] = $validated['late_fee_cutoff_mode'] ?? $this->getGlobalLateFeeCutoffMode();
                $validated['payment_accrual_mode'] = $validated['payment_accrual_mode'] ?? $this->getGlobalPaymentAccrualMode();
                $validated['cutoff_anchor_date'] = $validated['cutoff_anchor_date'] ?? $validated['start_date'];

                if (!$validated['enable_late_fees']) {
                    $validated['late_fee_daily_amount'] = null;
                }

                if (!$validated['legal_fee_enabled']) {
                    $validated['legal_fee_amount'] = 0;
                    $validated['legal_fee_financed'] = false;
                }

                if (!isset($validated['late_fee_grace_period'])) {
                    $validated['late_fee_grace_period'] = $this->getGlobalLateFeeGracePeriod();
                }

                if ($validated['legal_fee_enabled'] && !isset($validated['legal_fee_amount'])) {
                    $validated['legal_fee_amount'] = $this->getGlobalLegalFeeAmount();
                }

                if (!isset($validated['legal_days_overdue_threshold'])) {
                    $validated['legal_days_overdue_threshold'] = $this->getGlobalLegalDaysThreshold();
                }

                if (!isset($validated['legal_entry_fee_amount'])) {
                    $validated['legal_entry_fee_amount'] = $this->getGlobalLegalEntryFeeAmount();
                }

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
                        $validated['interest_mode'],
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

                if ($loan->legal_fee_enabled && $loan->legal_fee_financed && $loan->legal_fee_amount > 0) {
                    $newBalance = $loan->balance_total + $loan->legal_fee_amount;

                    $loan->ledgerEntries()->create([
                        'type' => 'legal_fee',
                        'occurred_at' => $validated['start_date'],
                        'amount' => $loan->legal_fee_amount,
                        'principal_delta' => 0,
                        'interest_delta' => 0,
                        'fees_delta' => $loan->legal_fee_amount,
                        'balance_after' => $newBalance,
                        'meta' => ['auto_created' => true]
                    ]);

                    $loan->fees_accrued += $loan->legal_fee_amount;
                    $loan->balance_total = $newBalance;
                    $loan->save();
                }

                // PROCESS CONSOLIDATION (CLOSE OLD LOANS)
                if (!empty($validated['consolidation_loan_ids'])) {
                    $sourceLoans = Loan::whereIn('id', $validated['consolidation_loan_ids'])->get();
                    foreach ($sourceLoans as $source) {
                        if ($source->status !== 'active') {
                            continue;
                        }

                        $consolidationDate = Carbon::parse($validated['start_date'])->startOfDay();

                        // Bring source loan balances up to date with current accrual/legal rules
                        $paymentService->postAccrualsThroughDueDates($source->fresh(), $consolidationDate);
                        $interestEngine->accrueUpTo($source->fresh(), $consolidationDate);
                        $legalStatusService->recalculateLegalEntry($source->fresh(), $consolidationDate);

                        // "Pay off" the loan via consolidation
                        $source->refresh();
                        $balanceToClear = $source->balance_total;

                        // Create a special ledger entry on the OLD loan
                        LoanLedgerEntry::create([
                            'loan_id' => $source->id,
                            'type' => 'refinance_payoff', // Use allowed enum value
                            // Let's use 'payment' to keep math simple, but meta distinct
                            'occurred_at' => $consolidationDate,
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
                        $source->status = 'closed_refinanced';
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

                }

                $paymentService->postAccrualsThroughDueDates($loan->fresh(), now()->startOfDay());

                $legalStatusService->recalculateLegalEntry($loan->fresh(), now()->startOfDay());
                $paymentService->recalculateLedgerBalances($loan->fresh());

                return redirect()->route('loans.show', $loan);
            });
        } catch (\Throwable $e) {
            Log::error("Error creating loan: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw ValidationException::withMessages([
                'notes' => 'Error del servidor al crear préstamo: ' . $e->getMessage()
            ]);
        }
    }

    public function show(Loan $loan, InterestEngine $interestEngine, AmortizationService $amortizationService, LegalStatusService $legalStatusService)
    {
        $legalStatusService->moveToLegalIfNeeded($loan->fresh(), now());
        $loan = $loan->fresh();

        $pendingInterestToday = $interestEngine->calculatePendingInterest($loan, now()->startOfDay());
        $lastAccrualDate = $loan->last_accrual_date
            ? Carbon::parse($loan->last_accrual_date)->startOfDay()
            : Carbon::parse($loan->start_date)->startOfDay();
        $pendingInterestDays = max(0, FinancialHelper::diffInDays($lastAccrualDate, now()->startOfDay(), (int) ($loan->days_in_month_convention ?: 30)));

        $postedInterestAtCuts = (float) $loan->interest_accrued;

        $loan->load(['client', 'ledgerEntries']);
        $loan->loadCount('payments');

        // Calculate arrears info
        $calculator = new ArrearsCalculator();
        $loan->arrears_info = $calculator->calculate($loan);

        $pendingLateFees = 0.0;
        $displayBalanceTotal = (float) $loan->balance_total;

        // Dynamic display-only ledger entries (not persisted)
        $ledgerEntries = $loan->ledgerEntries->map(function ($entry) {
            return $entry->toArray();
        })->values();

        $ledgerEntries = $ledgerEntries->sortBy(function ($entry) {
            return sprintf('%s-%s', $entry['occurred_at'] ?? '', (string) ($entry['id'] ?? ''));
        })->values();

        $loan->setRelation('ledgerEntries', collect($ledgerEntries));

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

        $feeBuckets = $this->resolveFeeBucketsFromLedger(collect($ledgerEntries));
        $legalFeesTotal = collect($ledgerEntries)->where('type', 'legal_fee')->sum('amount');
        $legalEntryFeesTotal = (float) ($feeBuckets['legal_entry_fee'] ?? 0);
        $lateFeesTotal = (float) ($feeBuckets['late_fee'] ?? 0);
        $capitalDisplay = (float) $loan->balance_total - (float) $loan->interest_accrued;
        $totalDue = (float) $loan->balance_total;

        return Inertia::render('Loans/Show', [
            'loan' => $loan,
            'projected_schedule' => $projectedSchedule,
            'display_balance_total' => (float) $displayBalanceTotal,
            'payoff_summary' => [
                'principal' => (float) $loan->principal_outstanding,
                'interest' => (float) $postedInterestAtCuts,
                'interest_display' => (float) $postedInterestAtCuts,
                'interest_at_cutoff' => (float) $pendingInterestToday,
                'interest_next_cut_days' => (int) $pendingInterestDays,
                'capital_display' => (float) $capitalDisplay,
                'late_fees' => (float) ($lateFeesTotal + $pendingLateFees),
                'legal_fees' => (float) $legalFeesTotal,
                'legal_entry_fees' => (float) $legalEntryFeesTotal,
                'total_due' => (float) $totalDue,
            ],
        ]);
    }

    public function legalIndex(Request $request)
    {
        $query = Loan::with('client')->where('legal_status', true);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $loans = $query->latest()->paginate(20)->withQueryString();

        $calculator = new ArrearsCalculator();
        $loans->getCollection()->transform(function ($loan) use ($calculator) {
            $loan->arrears_info = $calculator->calculate($loan);
            return $loan;
        });

        return Inertia::render('Loans/Legal', [
            'loans' => $loans,
            'filters' => [
                'search' => $request->input('search'),
            ],
        ]);
    }

    public function addLegalFee(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'occurred_at' => 'nullable|date',
            'notes' => 'required|string|max:1000',
        ]);

        if (in_array($loan->status, ['closed', 'closed_refinanced', 'cancelled', 'written_off'])) {
            throw ValidationException::withMessages([
                'amount' => 'No se pueden agregar gastos legales a un préstamo cerrado.'
            ]);
        }

        return DB::transaction(function () use ($loan, $validated) {
            $occurredAt = isset($validated['occurred_at'])
                ? Carbon::parse($validated['occurred_at'])
                : now();

            $newBalance = $loan->balance_total + $validated['amount'];

            $loan->ledgerEntries()->create([
                'type' => 'legal_fee',
                'occurred_at' => $occurredAt,
                'amount' => $validated['amount'],
                'principal_delta' => 0,
                'interest_delta' => 0,
                'fees_delta' => $validated['amount'],
                'balance_after' => $newBalance,
                'meta' => [
                    'reason' => 'manual',
                    'notes' => trim($validated['notes']),
                ],
            ]);

            $loan->fees_accrued += $validated['amount'];
            $loan->balance_total = $newBalance;
            $loan->save();

            return redirect()->route('loans.show', $loan);
        });
    }

    public function downloadLegalContract(Loan $loan)
    {
        $loan->load('client');

        $template = Setting::where('key', 'legal_contract_template')->value('value');

        if (!$template) {
            $template = "CONTRATO DE PRÉSTAMO\n\nCliente: {client_name}\nCédula: {client_national_id}\nDirección: {client_address}\nTeléfono: {client_phone}\n\nPréstamo: {loan_code}\nFecha de inicio: {loan_start_date}\nMonto principal: {loan_amount}\nGastos legales: {legal_fee_amount}\n\nFecha de generación: {today_date}\n";
        }

        $replacements = [
            '{client_name}' => trim($loan->client->first_name . ' ' . $loan->client->last_name),
            '{client_national_id}' => $loan->client->national_id,
            '{client_address}' => $loan->client->address ?? 'N/A',
            '{client_phone}' => $loan->client->phone ?? 'N/A',
            '{client_email}' => $loan->client->email ?? 'N/A',
            '{loan_code}' => $loan->code,
            '{loan_start_date}' => $loan->start_date?->format('Y-m-d'),
            '{loan_amount}' => number_format((float) $loan->principal_initial, 2),
            '{legal_fee_amount}' => number_format((float) $loan->legal_fee_amount, 2),
            '{today_date}' => now()->format('Y-m-d'),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);
        $fileName = "contrato_{$loan->code}.txt";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    public function legalSummary(Loan $loan, InterestEngine $interestEngine)
    {
        $loan->load(['client', 'ledgerEntries']);

        $pendingInterestToday = $interestEngine->calculatePendingInterest($loan, now()->startOfDay());

        $feeBuckets = $this->resolveFeeBucketsFromLedger($loan->ledgerEntries);
        $legalFeesTotal = $loan->ledgerEntries->where('type', 'legal_fee')->sum('amount');
        $legalEntryFeesTotal = (float) ($feeBuckets['legal_entry_fee'] ?? 0);
        $lateFeesTotal = (float) ($feeBuckets['late_fee'] ?? 0);
        $totalDue = (float) $loan->balance_total;

        return view('loans.legal-summary', [
            'loan' => $loan,
            'summary' => [
                'principal' => (float) $loan->principal_outstanding,
                'interest' => (float) $loan->interest_accrued,
                'interest_at_cutoff' => (float) $pendingInterestToday,
                'late_fees' => (float) $lateFeesTotal,
                'legal_fees' => (float) $legalFeesTotal,
                'legal_entry_fees' => (float) $legalEntryFeesTotal,
                'total_due' => (float) $totalDue,
            ],
        ]);
    }

    private function getGlobalLateFeeGracePeriod(): int
    {
        $value = Setting::where('key', 'global_late_fee_grace_period')->value('value');

        if ($value === null) {
            return 3;
        }

        return max(0, (int) $value);
    }

    private function resolveFeeBucketsFromLedger($entries): array
    {
        $lateAccrued = 0.0;
        $legalEntryAccrued = 0.0;
        $legalOtherAccrued = 0.0;
        $latePaid = 0.0;
        $legalEntryPaid = 0.0;
        $legalOtherPaid = 0.0;

        foreach ($entries as $entry) {
            $type = is_array($entry) ? ($entry['type'] ?? null) : $entry->type;
            $meta = is_array($entry) ? ($entry['meta'] ?? []) : ($entry->meta ?? []);
            $amount = (float) (is_array($entry) ? ($entry['amount'] ?? 0) : $entry->amount);

            if ($type === 'fee_accrual') {
                $lateAccrued += $amount;
                continue;
            }

            if ($type === 'legal_fee') {
                if ((string) data_get($meta, 'reason', '') === 'legal_entry') {
                    $legalEntryAccrued += $amount;
                } else {
                    $legalOtherAccrued += $amount;
                }
                continue;
            }

            if ($type === 'payment') {
                $breakdown = data_get($meta, 'payment_breakdown', []);
                $latePaid += (float) data_get($breakdown, 'late_fee.paid', 0);
                $legalEntryPaid += (float) data_get($breakdown, 'legal_entry_fee.paid', 0);
                $legalOtherPaid += (float) data_get($breakdown, 'legal_other_fee.paid', 0);
            }
        }

        return [
            'late_fee' => max(0, round($lateAccrued - $latePaid, 2)),
            'legal_entry_fee' => max(0, round($legalEntryAccrued - $legalEntryPaid, 2)),
            'legal_other_fee' => max(0, round($legalOtherAccrued - $legalOtherPaid, 2)),
        ];
    }


    private function getGlobalLateFeeCutoffMode(): string
    {
        $value = Setting::where('key', 'global_late_fee_cutoff_mode')->value('value');

        return in_array($value, ['dynamic_payment', 'fixed_cutoff'], true)
            ? $value
            : 'dynamic_payment';
    }

    private function getGlobalPaymentAccrualMode(): string
    {
        $value = Setting::where('key', 'global_payment_accrual_mode')->value('value');

        return in_array($value, ['realtime', 'cutoff_only'], true)
            ? $value
            : 'realtime';
    }

    private function getGlobalLegalFeeAmount(): float
    {
        $value = Setting::where('key', 'legal_fee_default_amount')->value('value');

        if ($value === null) {
            return 1000.00;
        }

        return max(0, (float) $value);
    }

    private function getGlobalLegalDaysThreshold(): int
    {
        $value = Setting::where('key', 'legal_days_overdue_threshold')->value('value');

        if ($value === null) {
            return 30;
        }

        return max(0, (int) $value);
    }

    private function getGlobalLegalEntryFeeAmount(): float
    {
        $value = Setting::where('key', 'legal_entry_fee_default')->value('value');

        if ($value === null) {
            return 4000.00;
        }

        return max(0, (float) $value);
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

    public function calculateInstallment(Request $request, InstallmentCalculator $calculator)
    {
        $validated = $request->validate([
            'principal' => 'required|numeric',
            'monthly_rate' => 'required|numeric',
            'modality' => 'required|string',
            'interest_mode' => 'required|in:simple,compound',
            'days_in_month_convention' => 'required|integer',
            'target_term_periods' => 'required|integer|min:1'
        ]);

        $installment = $calculator->calculateInstallment(
            $validated['principal'],
            $validated['monthly_rate'],
            $validated['modality'],
            $validated['interest_mode'],
            $validated['days_in_month_convention'],
            $validated['target_term_periods']
        );

        return response()->json(['installment' => $installment]);
    }

    public function cancel(Request $request, Loan $loan, InterestEngine $interestEngine, PaymentService $paymentService, LegalStatusService $legalStatusService)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        if (in_array($loan->status, ['closed', 'closed_refinanced', 'cancelled', 'written_off'])) {
            throw ValidationException::withMessages(['reason' => 'Este préstamo ya está cerrado o cancelado.']);
        }

        return DB::transaction(function () use ($loan, $validated, $interestEngine, $paymentService, $legalStatusService) {
            $asOf = now()->startOfDay();

            // Bring balances up to date before cancellation/write-off
            $paymentService->postAccrualsThroughDueDates($loan->fresh(), $asOf);
            $interestEngine->accrueUpTo($loan->fresh(), $asOf);
            $legalStatusService->recalculateLegalEntry($loan->fresh(), $asOf);
            $loan->refresh();

            $hasOperationalActivity = $loan->payments()->exists()
                || $loan->ledgerEntries()
                    ->whereNotIn('type', ['disbursement', 'cancellation', 'write_off'])
                    ->exists();

            if (!$hasOperationalActivity) {
                // Cancel (Mistake)
                $newStatus = 'cancelled';
                $ledgerType = 'cancellation';
            } else {
                // Write-off (Uncollectible)
                $newStatus = 'written_off';
                $ledgerType = 'write_off';
            }

            // Zero out balance
            $pDelta = -$loan->principal_outstanding;
            $iDelta = -$loan->interest_accrued;
            $fDelta = -$loan->fees_accrued;

            $loan->ledgerEntries()->create([
                'type' => $ledgerType,
                'occurred_at' => now(),
                'amount' => 0, // No cash exchange
                'principal_delta' => $pDelta,
                'interest_delta' => $iDelta,
                'fees_delta' => $fDelta,
                'balance_after' => 0,
                'meta' => [
                    'reason' => $validated['reason'],
                    'previous_status' => $loan->status
                ]
            ]);

            $loan->status = $newStatus;
            $loan->cancellation_reason = $validated['reason'];
            $loan->cancellation_date = now();
            $loan->principal_outstanding = 0;
            $loan->interest_accrued = 0;
            $loan->fees_accrued = 0;
            $loan->balance_total = 0;
            $loan->save();

            return redirect()->back();
        });
    }
}
