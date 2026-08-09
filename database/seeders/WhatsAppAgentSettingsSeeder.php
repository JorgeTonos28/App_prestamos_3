<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\WhatsAppAgentSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class WhatsAppAgentSettingsSeeder extends Seeder
{
    public function run(WhatsAppAgentSettings $settings): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach ($settings->defaults() as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => (string) $value]);
        }
    }
}
