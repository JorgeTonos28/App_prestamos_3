<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Client;
use App\Models\LoanLedgerEntry;
use App\Services\ArrearsCalculator;
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

        // Calculate Overdue Count using strict ArrearsCalculator logic
        // This ensures consistency with the detailed views.
        $calculator = new ArrearsCalculator();
        $activeLoans = Loan::where('status', 'active')->with('ledgerEntries')->get(); // Eager load for performance

        $overdueCount = 0;
        foreach ($activeLoans as $loan) {
            $arrears = $calculator->calculate($loan);
            if ($arrears['amount'] > 0) {
                $overdueCount++;
            }
        }

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

        $activeClientsCount = Client::where('status', 'active')->count();
        $arrearsRate = $activeLoansCount > 0 ? round(($overdueCount / $activeLoansCount) * 100, 1) : 0;

        return Inertia::render('Dashboard', [
            'stats' => [
                'active_loans_count' => $activeLoansCount,
                'portfolio_principal' => (float) $portfolioBalance, // Renamed to match Vue
                'loans_in_arrears_count' => $overdueCount,
                'interest_earnings_month' => (float) $monthlyInterestIncome,
                'principal_recovered_month' => (float) $monthlyPrincipalRecovered,
                'new_loans_month' => $newLoansCount,
                'new_loans_volume' => (float) $newLoansVolume,
                'active_clients_count' => $activeClientsCount,
                'arrears_rate' => $arrearsRate
            ]
        ]);
    }
}
