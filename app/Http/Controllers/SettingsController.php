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
            'global_late_fee_daily_amount' => 'nullable|numeric|min:0',
            'global_late_fee_grace_period' => 'nullable|integer|min:0',
            'legal_fee_default_amount' => 'nullable|numeric|min:0',
            'legal_contract_template' => 'nullable|string',
            'legal_entry_fee_default' => 'nullable|numeric|min:0',
            'legal_days_overdue_threshold' => 'nullable|integer|min:0',
            'admin_notification_email' => 'nullable|email|max:255',
        ]);

        // General Settings
        if ($request->has('app_name')) {
            Setting::updateOrCreate(['key' => 'app_name'], ['value' => $validated['app_name']]);
        }

        if ($request->has('sidebar_logo_height')) {
            Setting::updateOrCreate(['key' => 'sidebar_logo_height'], ['value' => $request->input('sidebar_logo_height')]);
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
