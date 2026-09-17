<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Loan;
use App\Models\Setting;
use App\Models\SmsNotification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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
        $creditRate = $isTest ? 0.0 : $this->dominicanCreditRate();
        $costPerCreditDop = max(0, (float) (Setting::where('key', 'sms_cost_per_credit')->value('value') ?? 0));
        $creditsUsed = $isTest ? 0.0 : round($profile['segments'] * $creditRate, 4);
        $estimatedCost = round($creditsUsed * $costPerCreditDop, 4);
        $ackRequested = $this->provider->deliveryAckUrl() !== null;

        $notification = DB::transaction(function () use ($client, $loan, $message, $source, $sentBy, $profile, $creditsUsed, $estimatedCost, $ackRequested) {
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
                'estimated_cost' => $estimatedCost,
                'cost_currency' => 'DOP',
                'ack_requested' => $ackRequested,
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

            if (! $isTest) {
                $this->refreshBalanceCacheQuietly();
            }
        } catch (Throwable $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $notification->refresh();
    }

    public function dominicanCreditRate(bool $forceRefresh = false): float
    {
        if ($forceRefresh) {
            Cache::forget('labsmobile.price.DO');
        }

        try {
            return (float) Cache::remember(
                'labsmobile.price.DO',
                now()->addHours(6),
                fn () => $this->provider->countryPrice('DO')
            );
        } catch (Throwable) {
            return (float) (Cache::get('labsmobile.price.DO') ?? 0);
        }
    }

    public function refreshBalanceCacheQuietly(): ?float
    {
        try {
            $balance = $this->provider->balance();
            Cache::put('labsmobile.balance', [
                'credits' => $balance,
                'checked_at' => now()->toIso8601String(),
            ], now()->addMinutes(10));

            return $balance;
        } catch (Throwable) {
            return null;
        }
    }
}
