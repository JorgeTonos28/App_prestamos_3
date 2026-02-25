<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LateFeeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        Setting::firstOrCreate(
            ['key' => 'global_late_fee_daily_amount'],
            ['value' => '100.00']
        );

        Setting::firstOrCreate(
            ['key' => 'global_late_fee_grace_period'],
            ['value' => '3']
        );

        Setting::firstOrCreate(
            ['key' => 'global_late_fee_cutoff_mode'],
            ['value' => 'dynamic_payment']
        );

        Setting::firstOrCreate(
            ['key' => 'global_payment_accrual_mode'],
            ['value' => 'realtime']
        );
    }
}
