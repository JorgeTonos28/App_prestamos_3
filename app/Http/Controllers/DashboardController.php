<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Client;
use App\Models\Payment;
use App\Models\LoanLedgerEntry;
use App\Services\ArrearsCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $defaultEndDate = Carbon::today();
        $defaultStartDate = $defaultEndDate->copy()->subMonthNoOverflow();

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : $defaultStartDate->copy()->startOfDay();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : $defaultEndDate->copy()->endOfDay();

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        $loanCheckpoint = Loan::max('updated_at');
        $paymentCheckpoint = Payment::max('updated_at');
        $ledgerCheckpoint = LoanLedgerEntry::max('updated_at');

        $cacheKey = sprintf(
            'dashboard_stats_v2:%s:%s:%s:%s',
            $startDate->format('Y-m-d') . '_' . $endDate->format('Y-m-d'),
            $loanCheckpoint ? Carbon::parse($loanCheckpoint)->timestamp : 'none',
            $paymentCheckpoint ? Carbon::parse($paymentCheckpoint)->timestamp : 'none',
            $ledgerCheckpoint ? Carbon::parse($ledgerCheckpoint)->timestamp : 'none'
        );

        $stats = Cache::remember($cacheKey, 600, function () use ($startDate, $endDate) {
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
                ->whereBetween('occurred_at', [$startDate, $endDate])
                ->sum(DB::raw('ABS(interest_delta)'));

            $monthlyPrincipalRecovered = LoanLedgerEntry::where('type', 'payment')
                ->whereBetween('occurred_at', [$startDate, $endDate])
                ->sum(DB::raw('ABS(principal_delta)'));

            $newLoansCount = Loan::whereBetween('start_date', [$startDate, $endDate])->count();
            $newLoansVolume = Loan::whereBetween('start_date', [$startDate, $endDate])->sum('principal_initial');

            $monthlyLegalFees = Loan::where('legal_fee_enabled', true)
                ->whereBetween('start_date', [$startDate, $endDate])
                ->sum('legal_fee_amount');

            $monthlyCashIncome = Payment::query()
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->where('method', 'cash')
                ->sum('amount');

            $monthlyBankIncome = Payment::query()
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->whereIn('method', ['transfer', 'card'])
                ->sum('amount');

            $activeClientsCount = Client::where('status', 'active')->count();
            $arrearsRate = $activeLoansCount > 0 ? round(($overdueCount / $activeLoansCount) * 100, 1) : 0;

            return [
                'active_loans_count' => $activeLoansCount,
                'portfolio_principal' => (float) $portfolioBalance, // Renamed to match Vue
                'loans_in_arrears_count' => $overdueCount,
                'interest_earnings_month' => (float) $monthlyInterestIncome,
                'principal_recovered_month' => (float) $monthlyPrincipalRecovered,
                'new_loans_month' => $newLoansCount,
                'new_loans_volume' => (float) $newLoansVolume,
                'legal_fees_month' => (float) $monthlyLegalFees,
                'cash_income_month' => (float) $monthlyCashIncome,
                'bank_income_month' => (float) $monthlyBankIncome,
                'active_clients_count' => $activeClientsCount,
                'arrears_rate' => $arrearsRate,
            ];
        });

        // Recent Activity (Simple Feed)
        // Use withTrashed() for client to ensure we can display name even if client is soft deleted.
        $recentDisbursements = Loan::with(['client' => function ($query) {
                $query->withTrashed();
            }])
            ->whereBetween('start_date', [$startDate, $endDate])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($loan) {
                return [
                    'id' => $loan->id,
                    'type' => 'disbursement',
                    'description' => "Préstamo {$loan->code}",
                    'client_name' => $loan->client ? ($loan->client->first_name . ' ' . $loan->client->last_name) : 'Cliente Desconocido',
                    'amount' => (float) $loan->principal_initial,
                    'date' => $loan->start_date,
                ];
            });

        // We could also fetch recent payments, but merging them might be complex for a simple dash.
        // Let's stick to these metrics for "Resumen General".

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recent_loans' => $recentDisbursements,
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'default_start_date' => $defaultStartDate->toDateString(),
                'default_end_date' => $defaultEndDate->toDateString(),
            ],
        ]);
    }
}
