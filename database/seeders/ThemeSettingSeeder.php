<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

class ThemeSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if the setting already exists to avoid overwriting or duplicates
        if (!Setting::where('key', 'system_theme')->exists()) {
            Setting::create([
                'key' => 'system_theme',
                'value' => 'default',
            ]);
        }
    }
}
