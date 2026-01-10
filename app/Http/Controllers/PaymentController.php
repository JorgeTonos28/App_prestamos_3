<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function store(Request $request, Loan $loan, PaymentService $paymentService)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $paymentService->registerPayment(
            $loan,
            now(), // or request->paid_at if we want backdating
            $validated['amount'],
            $validated['method'],
            $validated['reference'],
            $validated['notes']
        );

        return redirect()->back()->with('success', 'Pago registrado correctamente.');
    }
}
