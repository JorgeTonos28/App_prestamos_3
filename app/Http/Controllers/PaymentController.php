<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use App\Models\Payment;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(Request $request, Loan $loan, PaymentService $paymentService)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
            'paid_at' => 'nullable|date|before_or_equal:today' // Allow retroactive
        ]);

        $paidAt = $request->input('paid_at')
            ? Carbon::parse($validated['paid_at'])->startOfDay()
            : now()->startOfDay();

        // Retroactive Restriction: Check if any payment exists AFTER this date
        $futurePayments = $loan->payments()
            ->where('paid_at', '>', $paidAt)
            ->exists();

        if ($futurePayments) {
            throw ValidationException::withMessages([
                'paid_at' => 'No se puede registrar un pago retroactivo si ya existen pagos posteriores a esta fecha. Debe seguir el orden cronológico.'
            ]);
        }

        // Check if there are future INTEREST ACCRUALS (from auto-view calculations) that need rollback
        $futureAccruals = $loan->ledgerEntries()
            ->where('type', 'interest_accrual')
            ->whereDate('occurred_at', '>', $paidAt)
            ->exists();

        return DB::transaction(function () use ($loan, $paymentService, $validated, $paidAt, $futureAccruals) {

            if ($futureAccruals) {
                // Rollback future interest accruals
                // We delete them. The loan balance must be reduced by the sum of these accruals.
                $accruals = $loan->ledgerEntries()
                    ->where('type', 'interest_accrual')
                    ->whereDate('occurred_at', '>', $paidAt)
                    ->get();

                $totalReversedInterest = $accruals->sum('interest_delta'); // Positive amount

                // Delete entries
                $loan->ledgerEntries()
                    ->where('type', 'interest_accrual')
                    ->whereDate('occurred_at', '>', $paidAt)
                    ->delete();

                // Update Loan State
                $loan->interest_accrued -= $totalReversedInterest;
                $loan->balance_total -= $totalReversedInterest;

                // Reset last_accrual_date to the payment date (or just let it be re-calculated)
                // Actually, if we wipe everything after $paidAt, the last valid state is effectively $paidAt (or before).
                // But PaymentService calls accrueUpTo($paidAt).
                // So we should set last_accrual_date to something safe?
                // If we leave it as "Today", PaymentService won't accrue up to $paidAt (past).
                // So we must reset last_accrual_date.

                // Find the latest entry BEFORE or ON $paidAt
                $lastEntry = $loan->ledgerEntries()
                    ->whereDate('occurred_at', '<=', $paidAt)
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('id')
                    ->first();

                $loan->last_accrual_date = $lastEntry ? $lastEntry->occurred_at : $loan->start_date;
                $loan->save();
            }

            $paymentService->registerPayment(
                $loan,
                $paidAt,
                $validated['amount'],
                $validated['method'],
                $validated['reference'] ?? null,
                $validated['notes'] ?? null
            );

            return redirect()->back();
        });
    }
}
