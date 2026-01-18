<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanLedgerEntry;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\InterestEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(Request $request, Loan $loan, PaymentService $paymentService, InterestEngine $interestEngine)
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

        // Note: We delegate Replay Logic completely to PaymentService now.
        // It handles future payments AND future accruals.
        // So we don't need manual rollback here anymore.
        // Just calling registerPayment is enough.

        return DB::transaction(function () use ($loan, $paymentService, $validated, $paidAt, $interestEngine) {

            $paymentService->registerPayment(
                $loan,
                $paidAt,
                $validated['amount'],
                $validated['method'],
                $validated['reference'] ?? null,
                $validated['notes'] ?? null
            );

            // Accrue up to NOW to update display (Ledger will show "Payment" then "Interest to Now" if applicable? No, usually interest is BEFORE payment)
            // But if we want the "Summary" box to be correct in DB, we should accrue.
            // Since we removed it from Service to avoid spam in batch, we add it here for manual single payments.
            if ($loan->fresh()->status === 'active') {
                $interestEngine->accrueUpTo($loan->fresh(), now()->startOfDay());
            }

            return redirect()->back();
        });
    }

    public function destroy(Loan $loan, Payment $payment, PaymentService $paymentService, InterestEngine $interestEngine)
    {
        if ($payment->loan_id !== $loan->id) {
            abort(403, 'El pago no pertenece a este préstamo.');
        }

        DB::transaction(function () use ($loan, $payment, $paymentService, $interestEngine) {
            $paymentService->deletePayment($payment);

            // Ensure loan state is up to date
             if ($loan->fresh()->status === 'active') {
                $interestEngine->accrueUpTo($loan->fresh(), now()->startOfDay());
            }
        });

        return redirect()->back();
    }
}
