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
        $expectedToken = (string) config('services.labsmobile.webhook_token');
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

        if (! $notification) {
            return response()->noContent();
        }

        $ackLevel = $validated['acklevel'] ?? null;
        $failed = $validated['status'] === 'ko' || $ackLevel === 'error';
        $delivered = $validated['status'] === 'ok' && $ackLevel === 'handset';

        $notification->update([
            'status' => $failed ? 'failed' : ($delivered ? 'delivered' : 'accepted'),
            'delivered_at' => $delivered
                ? (isset($validated['timestamp']) ? Carbon::parse($validated['timestamp'], 'UTC') : now())
                : $notification->delivered_at,
            'error_message' => $failed ? ($validated['desc'] ?? 'Error de entrega reportado por LabsMobile.') : null,
            'delivery_details' => $validated,
        ]);

        return response()->noContent();
    }
}
