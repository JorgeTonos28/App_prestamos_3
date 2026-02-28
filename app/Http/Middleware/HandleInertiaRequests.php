<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $settings = [];

        try {
            if (Schema::hasTable('settings')) {
                $settings = Setting::pluck('value', 'key')->all();
            }
        } catch (\Exception $e) {
            // Log the error but continue to load the page without settings
            Log::error('Failed to load settings in HandleInertiaRequests: ' . $e->getMessage());
        }

        $user = $request->user();

        $subscriptionStatus = 'expired';
        $readOnly = false;

        if ($user) {
            if (app()->environment('testing')) {
                $subscriptionStatus = 'active';
                $readOnly = false;
            } else {
                $subscriptionStatus = $user->subscriptionState();
                $readOnly = ! $user->hasActiveSubscriptionAccess();
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'settings' => $settings,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'subscription' => [
                'status' => $subscriptionStatus,
                'read_only' => $readOnly,
            ],
        ];
    }
}
