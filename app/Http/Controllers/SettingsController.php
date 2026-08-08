<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Setting;
use App\Models\SmsNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $settings = Setting::pluck('value', 'key')->all();
        $tabs = ['general', 'loans', 'legal', 'email', 'sms'];
        $activeTab = in_array($request->input('tab'), $tabs, true)
            ? $request->input('tab')
            : 'general';

        $smsToRules = ['nullable', 'date'];
        if ($request->filled('sms_from')) {
            $smsToRules[] = 'after_or_equal:sms_from';
        }

        $smsFilters = $request->validate([
            'sms_search' => ['nullable', 'string', 'max:150'],
            'sms_from' => ['nullable', 'date'],
            'sms_to' => $smsToRules,
            'sms_status' => ['nullable', 'in:pending,simulated,accepted,delivered,failed'],
            'sms_source' => ['nullable', 'in:manual,overdue'],
        ]);

        $smsQuery = SmsNotification::query()
            ->with([
                'client:id,first_name,last_name,client_code',
                'loan:id,code',
                'sentBy:id,name',
            ])
            ->latest('id');

        if (! empty($smsFilters['sms_from'])) {
            $smsQuery->whereDate('notification_date', '>=', $smsFilters['sms_from']);
        }

        if (! empty($smsFilters['sms_to'])) {
            $smsQuery->whereDate('notification_date', '<=', $smsFilters['sms_to']);
        }

        if (! empty($smsFilters['sms_status'])) {
            $smsQuery->where('status', $smsFilters['sms_status']);
        }

        if (! empty($smsFilters['sms_source'])) {
            $smsQuery->where('source', $smsFilters['sms_source']);
        }

        if (! empty($smsFilters['sms_search'])) {
            $search = trim($smsFilters['sms_search']);
            $smsQuery->where(function ($query) use ($search) {
                $query->where('phone', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                    ->orWhere('provider_subid', 'like', "%{$search}%")
                    ->orWhere('api_code', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('client_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('loan', fn ($loanQuery) => $loanQuery->where('code', 'like', "%{$search}%"))
                    ->orWhereHas('sentBy', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $currency = strtoupper((string) ($settings['sms_cost_currency'] ?? 'EUR'));
        $smsSummary = [
            'total' => (clone $smsQuery)->count(),
            'successful' => (clone $smsQuery)->whereIn('status', ['simulated', 'accepted', 'delivered'])->count(),
            'delivered' => (clone $smsQuery)->where('status', 'delivered')->count(),
            'failed' => (clone $smsQuery)->where('status', 'failed')->count(),
            'credits_used' => (float) (clone $smsQuery)->sum('credits_used'),
            'estimated_cost' => (float) (clone $smsQuery)->sum('estimated_cost'),
            'currency' => in_array($currency, ['DOP', 'USD', 'EUR'], true) ? $currency : 'EUR',
        ];

        return Inertia::render('Settings/Edit', [
            'settings' => $settings,
            'activeTab' => $activeTab,
            'clients' => Client::query()
                ->whereNotNull('phone')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'client_code', 'first_name', 'last_name', 'phone', 'status']),
            'smsHistory' => $smsQuery->paginate(15)->withQueryString(),
            'smsFilters' => [
                'sms_search' => $smsFilters['sms_search'] ?? '',
                'sms_from' => $smsFilters['sms_from'] ?? '',
                'sms_to' => $smsFilters['sms_to'] ?? '',
                'sms_status' => $smsFilters['sms_status'] ?? '',
                'sms_source' => $smsFilters['sms_source'] ?? '',
            ],
            'smsSummary' => $smsSummary,
            'smsProvider' => [
                'enabled' => (bool) config('services.labsmobile.enabled', false),
                'configured' => trim((string) config('services.labsmobile.username')) !== ''
                    && trim((string) config('services.labsmobile.token')) !== '',
                'test_mode' => (bool) config('services.labsmobile.test_mode', true),
                'balance' => Cache::get('labsmobile.balance'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:1024',
            'dark_logo' => 'nullable|image|max:1024',
            'favicon' => 'nullable|image|mimes:ico,png|max:512',
            'email_sender_name' => 'nullable|string|max:255',
            'email_sender_address' => 'nullable|email|max:255',
            'overdue_email_subject' => 'nullable|string|max:255',
            'overdue_email_body' => 'nullable|string',
            'overdue_sms_enabled' => 'nullable|boolean',
            'overdue_sms_send_time' => 'nullable|date_format:H:i',
            'overdue_sms_interval_days' => 'nullable|integer|min:1|max:365',
            'overdue_sms_messages_per_day' => 'nullable|integer|min:1|max:5',
            'overdue_sms_body' => 'nullable|string|max:1000',
            'sms_cost_per_credit' => 'nullable|numeric|min:0|max:999999',
            'sms_cost_currency' => 'nullable|in:DOP,USD,EUR',
            'sidebar_logo_height' => 'nullable|integer|min:20|max:120',
            'color_theme' => 'nullable|in:default,carolina,pinky',
            'butterfly_enabled' => 'nullable|boolean',
            'butterfly_color' => 'nullable|in:rose,violet,sunset',
            'butterfly_interval_seconds' => 'nullable|integer|min:10|max:120',
            'global_late_fee_daily_amount' => 'nullable|numeric|min:0',
            'global_late_fee_grace_period' => 'nullable|integer|min:0',
            'global_late_fee_cutoff_mode' => 'nullable|in:dynamic_payment,fixed_cutoff',
            'global_payment_accrual_mode' => 'nullable|in:realtime,cutoff_only',
            'global_cutoff_cycle_mode' => 'nullable|in:calendar,fixed_dates',
            'global_month_day_count_mode' => 'nullable|in:exact,thirty',
            'global_late_fee_trigger_value' => 'nullable|integer|min:0',
            'global_late_fee_day_type' => 'nullable|in:business,calendar',
            'legal_fee_default_amount' => 'nullable|numeric|min:0',
            'legal_contract_template' => 'nullable|string',
            'legal_entry_fee_default' => 'nullable|numeric|min:0',
            'legal_days_overdue_threshold' => 'nullable|integer|min:0',
            'admin_notification_email' => 'nullable|email|max:255',
            'disable_payment_deletion' => 'nullable|boolean',
        ]);

        // General Settings
        if ($request->has('app_name')) {
            Setting::updateOrCreate(['key' => 'app_name'], ['value' => $validated['app_name']]);
        }

        if ($request->has('sidebar_logo_height')) {
            Setting::updateOrCreate(['key' => 'sidebar_logo_height'], ['value' => $request->input('sidebar_logo_height')]);
        }

        if ($request->has('color_theme')) {
            $normalizedTheme = ($validated['color_theme'] ?? 'default') === 'pinky'
                ? 'carolina'
                : ($validated['color_theme'] ?? 'default');

            Setting::updateOrCreate(
                ['key' => 'color_theme'],
                ['value' => $normalizedTheme]
            );
        }

        if ($request->has('butterfly_enabled')) {
            Setting::updateOrCreate(
                ['key' => 'butterfly_enabled'],
                ['value' => $request->boolean('butterfly_enabled') ? '1' : '0']
            );
        }

        if ($request->has('butterfly_color')) {
            Setting::updateOrCreate(
                ['key' => 'butterfly_color'],
                ['value' => $validated['butterfly_color']]
            );
        }

        if ($request->has('butterfly_interval_seconds')) {
            Setting::updateOrCreate(
                ['key' => 'butterfly_interval_seconds'],
                ['value' => (string) $validated['butterfly_interval_seconds']]
            );
        }

        // Email Settings
        $emailKeys = ['email_sender_name', 'email_sender_address', 'overdue_email_subject', 'overdue_email_body'];
        foreach ($emailKeys as $key) {
            if ($request->has($key)) {
                Setting::updateOrCreate(['key' => $key], ['value' => $validated[$key]]);
            }
        }

        // Overdue SMS settings. Provider credentials remain in .env; operational
        // behavior is controlled by the administrator from the Settings screen.
        if ($request->has('overdue_sms_enabled')) {
            Setting::updateOrCreate(
                ['key' => 'overdue_sms_enabled'],
                ['value' => $request->boolean('overdue_sms_enabled') ? '1' : '0']
            );
        }

        $smsKeys = [
            'overdue_sms_send_time',
            'overdue_sms_interval_days',
            'overdue_sms_messages_per_day',
            'overdue_sms_body',
            'sms_cost_per_credit',
            'sms_cost_currency',
        ];
        foreach ($smsKeys as $key) {
            if ($request->has($key)) {
                Setting::updateOrCreate(['key' => $key], ['value' => (string) $validated[$key]]);
            }
        }

        if ($request->has('global_late_fee_daily_amount')) {
            Setting::updateOrCreate(
                ['key' => 'global_late_fee_daily_amount'],
                ['value' => $validated['global_late_fee_daily_amount']]
            );
        }

        if ($request->has('global_late_fee_grace_period')) {
            Setting::updateOrCreate(
                ['key' => 'global_late_fee_grace_period'],
                ['value' => $validated['global_late_fee_grace_period']]
            );
        }

        if ($request->has('global_late_fee_cutoff_mode')) {
            Setting::updateOrCreate(
                ['key' => 'global_late_fee_cutoff_mode'],
                ['value' => $validated['global_late_fee_cutoff_mode']]
            );
        }

        if ($request->has('global_payment_accrual_mode')) {
            Setting::updateOrCreate(
                ['key' => 'global_payment_accrual_mode'],
                ['value' => $validated['global_payment_accrual_mode']]
            );
        }

        if ($request->has('global_cutoff_cycle_mode')) {
            Setting::updateOrCreate(
                ['key' => 'global_cutoff_cycle_mode'],
                ['value' => $validated['global_cutoff_cycle_mode']]
            );
        }

        if ($request->has('global_month_day_count_mode')) {
            Setting::updateOrCreate(
                ['key' => 'global_month_day_count_mode'],
                ['value' => $validated['global_month_day_count_mode']]
            );
        }

        Setting::updateOrCreate(
            ['key' => 'global_late_fee_trigger_type'],
            ['value' => 'installments']
        );

        if ($request->has('global_late_fee_trigger_value')) {
            Setting::updateOrCreate(
                ['key' => 'global_late_fee_trigger_value'],
                ['value' => $validated['global_late_fee_trigger_value']]
            );
        }

        if ($request->has('global_late_fee_day_type')) {
            Setting::updateOrCreate(
                ['key' => 'global_late_fee_day_type'],
                ['value' => $validated['global_late_fee_day_type']]
            );
        }

        if ($request->has('legal_fee_default_amount')) {
            Setting::updateOrCreate(
                ['key' => 'legal_fee_default_amount'],
                ['value' => $validated['legal_fee_default_amount']]
            );
        }

        if ($request->has('legal_contract_template')) {
            Setting::updateOrCreate(
                ['key' => 'legal_contract_template'],
                ['value' => $validated['legal_contract_template']]
            );
        }

        if ($request->has('legal_entry_fee_default')) {
            Setting::updateOrCreate(
                ['key' => 'legal_entry_fee_default'],
                ['value' => $validated['legal_entry_fee_default']]
            );
        }

        if ($request->has('legal_days_overdue_threshold')) {
            Setting::updateOrCreate(
                ['key' => 'legal_days_overdue_threshold'],
                ['value' => $validated['legal_days_overdue_threshold']]
            );
        }

        if ($request->has('admin_notification_email')) {
            Setting::updateOrCreate(
                ['key' => 'admin_notification_email'],
                ['value' => $validated['admin_notification_email']]
            );
        }

        if ($request->has('disable_payment_deletion')) {
            Setting::updateOrCreate(
                ['key' => 'disable_payment_deletion'],
                ['value' => $request->boolean('disable_payment_deletion') ? '1' : '0']
            );
        }

        // Files
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'logo_path'], ['value' => Storage::url($path)]);
        }

        if ($request->hasFile('dark_logo')) {
            $path = $request->file('dark_logo')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'dark_logo_path'], ['value' => Storage::url($path)]);
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'favicon_path'], ['value' => Storage::url($path)]);
        }

        return redirect()->back()->with('success', 'Configuración actualizada correctamente.');
    }
}
