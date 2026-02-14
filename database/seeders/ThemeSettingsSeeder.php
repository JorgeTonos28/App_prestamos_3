<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ThemeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        Setting::firstOrCreate(
            ['key' => 'color_theme'],
            ['value' => 'default']
        );
    }
}
