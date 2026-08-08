<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Loan;
use App\Services\LabsMobileSmsService;
use App\Services\SmsDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

class SmsController extends Controller
{
    public function store(Request $request, SmsDispatchService $dispatcher): RedirectResponse
    {
        if (! config('services.labsmobile.enabled', false)) {
            throw ValidationException::withMessages([
                'message' => 'LabsMobile está deshabilitado en el entorno. Activa LABSMOBILE_ENABLED.',
            ]);
        }

        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'loan_id' => ['nullable', 'integer', 'exists:loans,id'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $client = Client::findOrFail($validated['client_id']);
        if (! $client->phone) {
            throw ValidationException::withMessages([
                'client_id' => 'El cliente no tiene un teléfono registrado.',
            ]);
        }

        $loan = isset($validated['loan_id']) ? Loan::findOrFail($validated['loan_id']) : null;
        if ($loan && $loan->client_id !== $client->id) {
            throw ValidationException::withMessages([
                'loan_id' => 'El préstamo no pertenece al cliente seleccionado.',
            ]);
        }

        try {
            $notification = $dispatcher->send(
                $client,
                trim($validated['message']),
                $loan,
                'manual',
                $request->user(),
            );
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'message' => 'No se pudo enviar el SMS. Revisa el historial para ver el detalle del proveedor.',
            ]);
        }

        $message = $notification->status === 'simulated'
            ? 'LabsMobile aceptó el SMS en modo simulado. No se consumieron créditos.'
            : 'SMS aceptado por LabsMobile correctamente.';

        return redirect()->back()->with('success', $message);
    }

    public function refreshBalance(LabsMobileSmsService $provider): RedirectResponse
    {
        try {
            $balance = $provider->balance();
            Cache::put('labsmobile.balance', [
                'credits' => $balance,
                'checked_at' => now()->toIso8601String(),
            ], now()->addMinutes(10));
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'No se pudo consultar el saldo de LabsMobile: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Saldo de LabsMobile actualizado.');
    }
}
