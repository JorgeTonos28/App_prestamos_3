<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function edit()
    {
        $settings = Setting::pluck('value', 'key')->all();

        return Inertia::render('Settings/Edit', [
            'settings' => $settings
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
