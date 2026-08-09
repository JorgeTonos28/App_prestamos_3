<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\WhatsAppAgentSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

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
                $settings = Setting::query()
                    ->whereNotIn('key', WhatsAppAgentSettings::SECRET_KEYS)
                    ->pluck('value', 'key')
                    ->all();
            }
        } catch (\Exception $e) {
            Log::error('Failed to load settings in HandleInertiaRequests: '.$e->getMessage());
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'settings' => $settings,
            'smsPricing' => [
                'test_mode' => (bool) config('services.labsmobile.test_mode', true),
                'credit_rate' => (float) (Cache::get('labsmobile.price.DO') ?? 0),
                'cost_per_credit_dop' => max(0, (float) ($settings['sms_cost_per_credit'] ?? 0)),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
