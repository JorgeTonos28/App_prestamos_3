<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LabsMobileSmsService
{
    public function send(string $phone, string $message): array
    {
        $username = (string) config('services.labsmobile.username');
        $token = (string) config('services.labsmobile.token');

        if ($username === '' || $token === '') {
            throw new RuntimeException('LabsMobile credentials are not configured.');
        }

        $payload = [
            'message' => $message,
            'recipient' => [
                ['msisdn' => $this->normalizeDominicanPhone($phone)],
            ],
            'test' => config('services.labsmobile.test_mode', true) ? 1 : 0,
        ];

        try {
            $response = Http::withBasicAuth($username, $token)
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->retry(2, 500)
                ->post((string) config('services.labsmobile.endpoint'), $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not connect to LabsMobile.', previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('LabsMobile HTTP error '.$response->status().': '.$response->body());
        }

        $data = $response->json();
        $code = (string) ($data['code'] ?? '');

        if ($code !== '0') {
            throw new RuntimeException('LabsMobile rejected the SMS (code '.$code.'): '.($data['message'] ?? 'Unknown error'));
        }

        return is_array($data) ? $data : [];
    }

    public function normalizeDominicanPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10 || ! preg_match('/^(809|829|849)\d{7}$/', $digits)) {
            throw new RuntimeException('Invalid Dominican phone number: '.$phone);
        }

        return '1'.$digits;
    }
}
