<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rewriteDeliveryTimestamps(convertToLocalTime: true);
    }

    public function down(): void
    {
        $this->rewriteDeliveryTimestamps(convertToLocalTime: false);
    }

    private function rewriteDeliveryTimestamps(bool $convertToLocalTime): void
    {
        $timezone = (string) config('app.timezone', 'UTC');

        DB::table('sms_notifications')
            ->where('provider', 'labsmobile')
            ->whereNotNull('delivered_at')
            ->whereNotNull('delivery_details')
            ->orderBy('id')
            ->chunkById(100, function ($notifications) use ($convertToLocalTime, $timezone): void {
                foreach ($notifications as $notification) {
                    $details = json_decode((string) $notification->delivery_details, true);
                    $timestamp = is_array($details) ? ($details['timestamp'] ?? null) : null;

                    if (! is_string($timestamp) || trim($timestamp) === '') {
                        continue;
                    }

                    try {
                        $providerTime = CarbonImmutable::parse($timestamp, 'UTC');
                    } catch (Throwable) {
                        continue;
                    }

                    $storedTime = $convertToLocalTime
                        ? $providerTime->setTimezone($timezone)
                        : $providerTime;

                    DB::table('sms_notifications')
                        ->where('id', $notification->id)
                        ->update(['delivered_at' => $storedTime->format('Y-m-d H:i:s')]);
                }
            });
    }
};
