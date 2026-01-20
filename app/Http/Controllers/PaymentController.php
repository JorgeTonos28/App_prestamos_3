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
use Illuminate\Support\Facades\Log;

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

        return DB::transaction(function () use ($loan, $paymentService, $validated, $paidAt, $interestEngine) {

            $paymentService->registerPayment(
                $loan,
                $paidAt,
                $validated['amount'],
                $validated['method'],
                $validated['reference'] ?? null,
                $validated['notes'] ?? null
            );

            if ($loan->fresh()->status === 'active') {
                $interestEngine->accrueUpTo($loan->fresh(), now()->startOfDay());
            }

            return redirect()->back();
        });
    }

    public function destroy(Loan $loan, Payment $payment, PaymentService $paymentService, InterestEngine $interestEngine)
    {
        if ((int)$payment->loan_id !== (int)$loan->id) {
            abort(403, 'El pago no pertenece a este préstamo.');
        }

        try {
            DB::transaction(function () use ($loan, $payment, $paymentService, $interestEngine) {
                $paymentService->deletePayment($payment);

                if ($loan->fresh()->status === 'active') {
                    $interestEngine->accrueUpTo($loan->fresh(), now()->startOfDay());
                }
            });
        } catch (\Throwable $e) {
            Log::error("Error deleting payment {$payment->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            // Throw as ValidationException to make it visible in frontend if Inertia handles it
            // or just abort with message
            throw ValidationException::withMessages([
                'payment' => 'Error al eliminar el pago: ' . $e->getMessage()
            ]);
        }

        return redirect()->back();
    }
}
