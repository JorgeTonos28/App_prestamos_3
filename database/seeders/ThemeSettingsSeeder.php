<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class ThemeSettingsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Setting::firstOrCreate(
            ['key' => 'theme_palette'],
            ['value' => 'default']
        );
    }
}
