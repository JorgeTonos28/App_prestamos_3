<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Loan;
use App\Services\InstallmentCalculator;
use App\Services\InterestEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

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

    public function store(Request $request, InstallmentCalculator $calculator)
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
            'notes' => 'nullable'
        ]);

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
        $loan->status = 'draft'; // User needs to "disburse" it or we do it auto?
        // Let's assume auto disbursement for simplicity of UI flow unless specified.
        // Prompt says: "Crear préstamo (wizard): ... -> interés mode -> cuota auto"
        // And "Active: genera interés diario".
        // Let's save as active immediately for MVP.
        $loan->status = 'active';
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

        return redirect()->route('loans.show', $loan);
    }

    public function show(Loan $loan, InterestEngine $interestEngine)
    {
        // Accrue interest on view
        $interestEngine->accrueUpTo($loan, now());

        $loan->load(['client', 'ledgerEntries']);

        return Inertia::render('Loans/Show', [
            'loan' => $loan
        ]);
    }
}
