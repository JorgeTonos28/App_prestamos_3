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

        Setting::firstOrCreate(['key' => 'global_late_fee_daily_amount'], ['value' => '100.00']);
        Setting::firstOrCreate(['key' => 'global_late_fee_grace_period'], ['value' => '3']);
        Setting::firstOrCreate(['key' => 'global_late_fee_cutoff_mode'], ['value' => 'dynamic_payment']);
        Setting::firstOrCreate(['key' => 'global_payment_accrual_mode'], ['value' => 'realtime']);
        Setting::firstOrCreate(['key' => 'global_cutoff_cycle_mode'], ['value' => 'calendar']);
        Setting::firstOrCreate(['key' => 'global_month_day_count_mode'], ['value' => 'exact']);
        Setting::firstOrCreate(['key' => 'global_late_fee_trigger_type'], ['value' => 'days']);
        Setting::firstOrCreate(['key' => 'global_late_fee_trigger_value'], ['value' => '3']);
        Setting::firstOrCreate(['key' => 'global_late_fee_day_type'], ['value' => 'business']);
    }
}
