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
            'logo' => 'nullable|image|max:1024', // 1MB max
            'favicon' => 'nullable|image|mimes:ico,png|max:512', // 512KB max
        ]);

        if ($request->has('app_name')) {
            Setting::updateOrCreate(['key' => 'app_name'], ['value' => $validated['app_name']]);
        }

        if ($request->hasFile('logo')) {
            // Store in the 'public' disk (storage/app/public/settings)
            $path = $request->file('logo')->store('settings', 'public');
            $url = Storage::url($path);
            Setting::updateOrCreate(['key' => 'logo_path'], ['value' => $url]);
        }

        if ($request->hasFile('favicon')) {
            // Store in the 'public' disk (storage/app/public/settings)
            $path = $request->file('favicon')->store('settings', 'public');
            $url = Storage::url($path);
            Setting::updateOrCreate(['key' => 'favicon_path'], ['value' => $url]);
        }

        return redirect()->back()->with('success', 'Configuración actualizada correctamente.');
    }
}
