<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $basePrice = (int) config('plans.base_monthly_price');
        $currency = config('plans.currency', 'DOP');

        $plans = collect(config('plans.billing_cycles', []))
            ->map(function (array $plan, string $key) use ($basePrice) {
                $months = (int) ($plan['months'] ?? 1);
                $discountPercent = (float) ($plan['discount_percent'] ?? 0);
                $fullPrice = $basePrice * $months;
                $discountAmount = (int) round($fullPrice * ($discountPercent / 100));

                return [
                    'key' => $key,
                    'label' => $plan['label'] ?? ucfirst($key),
                    'months' => $months,
                    'discount_percent' => $discountPercent,
                    'stripe_price_id' => $plan['stripe_price_id'] ?? null,
                    'full_price' => $fullPrice,
                    'final_price' => $fullPrice - $discountAmount,
                    'savings' => $discountAmount,
                ];
            })
            ->values();

        return Inertia::render('Settings/Subscription', [
            'plans' => $plans,
            'currency' => $currency,
            'currentPlanPriceId' => optional($user->subscription('default'))->stripe_price,
            'status' => $user->subscriptionState(),
            'stripeKey' => config('cashier.key'),
            'invoices' => $user->invoices()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'date' => $invoice->date()->toDateTimeString(),
                'total' => $invoice->total(),
                'status' => $invoice->status,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
            ]),
        ]);
    }

    public function setupIntent(Request $request): array
    {
        return ['client_secret' => $request->user()->createSetupIntent()->client_secret];
    }

    public function updatePaymentMethod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        $user = $request->user();
        $user->updateDefaultPaymentMethod($validated['payment_method']);

        return back()->with('success', 'Método de pago actualizado correctamente.');
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:monthly,quarterly,semiannual,annual'],
        ]);

        $user = $request->user();
        $priceId = config('plans.billing_cycles.'.$validated['plan'].'.stripe_price_id');

        if (! $priceId) {
            return back()->withErrors(['plan' => 'El plan seleccionado no está configurado en Stripe.']);
        }

        try {
            $subscription = $user->subscription('default');

            if ($subscription) {
                $subscription->swap($priceId);
            } else {
                $user->newSubscription('default', $priceId)->create();
            }
        } catch (IncompletePayment $exception) {
            return redirect()->route('cashier.payment', [$exception->payment->id, 'redirect' => route('settings.subscription')]);
        }

        return back()->with('success', 'Suscripción actualizada correctamente.');
    }

    public function invoiceDownload(Request $request, string $invoiceId): StreamedResponse
    {
        return $request->user()->downloadInvoice($invoiceId, [
            'vendor' => config('app.name'),
            'product' => 'Suscripción SaaS',
        ]);
    }
}
