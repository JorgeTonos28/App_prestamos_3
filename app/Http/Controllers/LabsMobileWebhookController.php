<?php

namespace App\Http\Controllers;

use App\Models\SmsNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LabsMobileWebhookController extends Controller
{
    public function delivery(Request $request): Response
    {
        $expectedToken = trim((string) config('services.labsmobile.webhook_token'));
        $providedToken = (string) $request->query('token');

        abort_if($expectedToken === '' || ! hash_equals($expectedToken, $providedToken), 403);

        $validated = $request->validate([
            'subid' => ['required', 'string', 'max:100'],
            'acklevel' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:ok,ko'],
            'desc' => ['nullable', 'string', 'max:100'],
            'timestamp' => ['nullable', 'date'],
            'msisdn' => ['nullable', 'string', 'max:30'],
            'type' => ['nullable', 'string', 'max:20'],
        ]);

        $notification = SmsNotification::where('provider', 'labsmobile')
            ->where('provider_subid', $validated['subid'])
            ->first();

        // LabsMobile can retry callbacks. A missing notification should still
        // receive a 204 so the provider does not keep retrying an unknown subid.
        if (! $notification) {
            return response()->noContent();
        }

        $ackLevel = strtolower((string) ($validated['acklevel'] ?? ''));
        $description = strtoupper((string) ($validated['desc'] ?? ''));
        $failed = $validated['status'] === 'ko'
            || $ackLevel === 'error'
            || in_array($description, ['BLOCKED', 'EXPIRED', 'REJECTD', 'UNDELIV', 'UNKNOWN'], true);
        $delivered = ! $failed
            && ($description === 'DELIVRD' || ($validated['status'] === 'ok' && $ackLevel === 'handset'));

        // Delivery callbacks may arrive more than once and not necessarily in a
        // useful order. Once delivered, never downgrade the local record.
        $nextStatus = $notification->status;
        if ($notification->status !== 'delivered') {
            $nextStatus = $failed ? 'failed' : ($delivered ? 'delivered' : 'accepted');
        }

        $notification->update([
            'status' => $nextStatus,
            'delivered_at' => $delivered
                ? $this->providerTimestamp($validated['timestamp'] ?? null)
                : $notification->delivered_at,
            'error_message' => $failed ? $this->diagnosticMessage($description) : null,
            'delivery_details' => [
                ...($notification->delivery_details ?? []),
                ...$validated,
                'diagnostic' => $this->diagnosticMessage($description),
                'received_at' => now()->toIso8601String(),
            ],
        ]);

        return response()->noContent();
    }

    private function providerTimestamp(?string $timestamp): Carbon
    {
        return $timestamp
            ? Carbon::parse($timestamp, 'UTC')
            : now();
    }

    private function diagnosticMessage(string $description): string
    {
        return match ($description) {
            'DELIVRD' => 'Entregado y confirmado por el dispositivo del destinatario.',
            'BLOCKED' => 'Bloqueado por filtros de seguridad o antispam del operador/proveedor.',
            'EXPIRED' => 'El SMS expiró antes de que pudiera ser entregado al dispositivo.',
            'REJECTD' => 'El SMS fue rechazado por el operador o la red de destino.',
            'UNDELIV' => 'No entregable. Revisa el número, disponibilidad del dispositivo y cobertura del operador.',
            'UNKNOWN' => 'LabsMobile informó un error de entrega sin una causa más específica.',
            'READ' => 'El mensaje fue marcado como leído por el destinatario.',
            default => $description !== ''
                ? 'Estado reportado por LabsMobile: '.$description.'.'
                : 'LabsMobile todavía no ha reportado un diagnóstico final de entrega.',
        };
    }
}
