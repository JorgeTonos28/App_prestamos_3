<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Client;
use App\Models\LoanLedgerEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // General Stats
        $activeLoansCount = Loan::where('status', 'active')->count();
        $portfolioBalance = Loan::where('status', 'active')->sum('balance_total');
        // 'Overdue' roughly defined by next_due_date passed.
        // Note: next_due_date might not be reliable if not strictly maintained,
        // but it's the best proxy we have.
        $overdueCount = Loan::where('status', 'active')
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<', now())
            ->count();

        // Monthly Insights
        // Income = Interest Paid portion of payments
        $monthlyInterestIncome = LoanLedgerEntry::where('type', 'payment')
            ->whereBetween('occurred_at', [$startOfMonth, $endOfMonth])
            ->sum(DB::raw('ABS(interest_delta)'));

        $monthlyPrincipalRecovered = LoanLedgerEntry::where('type', 'payment')
            ->whereBetween('occurred_at', [$startOfMonth, $endOfMonth])
            ->sum(DB::raw('ABS(principal_delta)'));

        $newLoansCount = Loan::whereBetween('start_date', [$startOfMonth, $endOfMonth])->count();
        $newLoansVolume = Loan::whereBetween('start_date', [$startOfMonth, $endOfMonth])->sum('principal_initial');

        // Recent Activity (Simple Feed)
        $recentDisbursements = Loan::with('client')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($loan) {
                return [
                    'id' => $loan->id,
                    'type' => 'disbursement',
                    'description' => "Préstamo {$loan->code}",
                    'client_name' => $loan->client->first_name . ' ' . $loan->client->last_name,
                    'amount' => $loan->principal_initial,
                    'date' => $loan->start_date,
                ];
            });

        // We could also fetch recent payments, but merging them might be complex for a simple dash.
        // Let's stick to these metrics for "Resumen General".

        return Inertia::render('Dashboard', [
            'active_loans_count' => $activeLoansCount,
            'portfolio_balance' => (float) $portfolioBalance,
            'overdue_count' => $overdueCount,
            'monthly_interest_income' => (float) $monthlyInterestIncome,
            'monthly_principal_recovered' => (float) $monthlyPrincipalRecovered,
            'new_loans_count' => $newLoansCount,
            'new_loans_volume' => (float) $newLoansVolume,
            'recent_loans' => $recentDisbursements
        ]);
    }
}
