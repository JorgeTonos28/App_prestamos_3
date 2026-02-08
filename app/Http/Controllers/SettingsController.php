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
