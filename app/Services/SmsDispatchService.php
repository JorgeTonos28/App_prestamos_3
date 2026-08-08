<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Loan;
use App\Models\Setting;
use App\Models\SmsNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class SmsDispatchService
{
    public function __construct(private readonly LabsMobileSmsService $provider) {}

    public function send(
        Client $client,
        string $message,
        ?Loan $loan = null,
        string $source = 'manual',
        ?User $sentBy = null,
    ): SmsNotification {
        $profile = $this->provider->messageProfile($message);
        $isTest = (bool) config('services.labsmobile.test_mode', true);
        $costPerCredit = max(0, (float) (Setting::where('key', 'sms_cost_per_credit')->value('value') ?? 0));
        $currency = strtoupper((string) (Setting::where('key', 'sms_cost_currency')->value('value') ?? 'EUR'));
        $creditsUsed = $isTest ? 0 : $profile['segments'];

        $notification = DB::transaction(function () use ($client, $loan, $message, $source, $sentBy, $profile, $creditsUsed, $costPerCredit, $currency) {
            Client::query()->whereKey($client->id)->lockForUpdate()->first();

            $resolvedSequence = ((int) SmsNotification::where('client_id', $client->id)
                ->where('provider', 'labsmobile')
                ->whereDate('notification_date', now()->toDateString())
                ->lockForUpdate()
                ->max('message_sequence')) + 1;

            return SmsNotification::create([
                'client_id' => $client->id,
                'loan_id' => $loan?->id,
                'sent_by_user_id' => $sentBy?->id,
                'phone' => $client->phone,
                'message' => $message,
                'provider' => 'labsmobile',
                'source' => $source,
                'status' => 'pending',
                'notification_date' => now()->toDateString(),
                'message_sequence' => $resolvedSequence,
                'segment_count' => $profile['segments'],
                'credits_used' => $creditsUsed,
                'estimated_cost' => $creditsUsed * $costPerCredit,
                'cost_currency' => in_array($currency, ['DOP', 'USD', 'EUR'], true) ? $currency : 'EUR',
            ]);
        });

        try {
            $result = $this->provider->send($client->phone, $message, [
                'label' => 'app-presto:'.$source,
            ]);

            $notification->update([
                'provider_subid' => $result['subid'] ?? null,
                'api_code' => (string) ($result['code'] ?? '0'),
                'status' => $isTest ? 'simulated' : 'accepted',
                'provider_response' => $result,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $notification->refresh();
    }
}
