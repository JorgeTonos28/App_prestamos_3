<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->hasActiveSubscriptionAccess()) {
            return $next($request);
        }

        if ($request->routeIs('dashboard')) {
            return $next($request);
        }

        if ($request->routeIs([
            'settings.subscription',
            'settings.subscription.*',
            'profile.edit',
            'profile.update',
            'profile.destroy',
            'logout',
        ])) {
            return $next($request);
        }

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            abort(403, 'Suscripción inactiva. Actualice su método de pago');
        }

        return redirect()->route('settings.subscription')
            ->with('error', 'Suscripción inactiva. Actualice su método de pago.');
    }
}
