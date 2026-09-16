<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'overdue_sms_start_day'],
            ['value' => '1'],
        );
    }

    public function down(): void
    {
        Setting::where('key', 'overdue_sms_start_day')->delete();
    }
};
