<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LabsMobileSmsService
{
    public function send(string $phone, string $message, array $options = []): array
    {
        [$username, $token] = $this->credentials();
        $profile = $this->messageProfile($message);

        $payload = [
            'message' => $message,
            'recipient' => [
                ['msisdn' => $this->normalizeDominicanPhone($phone)],
            ],
            'test' => config('services.labsmobile.test_mode', true) ? '1' : '0',
        ];

        if ($profile['segments'] > 1) {
            $payload['long'] = '1';
        }

        if ($profile['unicode']) {
            $payload['ucs2'] = '1';
        }

        $label = trim((string) ($options['label'] ?? ''));
        if ($label !== '') {
            $payload['label'] = mb_substr($label, 0, 255);
        }

        $ackUrl = $this->deliveryAckUrl();
        if ($ackUrl !== null) {
            $payload['ackurl'] = $ackUrl;
        }

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

    public function balance(): float
    {
        [$username, $token] = $this->credentials();

        try {
            $response = Http::withBasicAuth($username, $token)
                ->acceptJson()
                ->timeout(20)
                ->retry(2, 500)
                ->get((string) config('services.labsmobile.balance_endpoint'));
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not connect to LabsMobile.', previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('LabsMobile HTTP error '.$response->status().': '.$response->body());
        }

        $data = $response->json();
        if (! is_array($data) || (string) ($data['code'] ?? '') !== '0' || ! is_numeric($data['credits'] ?? null)) {
            throw new RuntimeException('LabsMobile returned an invalid balance response.');
        }

        return (float) $data['credits'];
    }

    public function countryPrice(string $country = 'DO'): float
    {
        [$username, $token] = $this->credentials();
        $country = strtoupper(trim($country));

        try {
            $response = Http::withBasicAuth($username, $token)
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->retry(2, 500)
                ->post((string) config('services.labsmobile.prices_endpoint'), [
                    'format' => 'JSON',
                    'countries' => [$country],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not connect to LabsMobile.', previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('LabsMobile HTTP error '.$response->status().': '.$response->body());
        }

        $data = $response->json();
        $rate = is_array($data) ? ($data[$country]['credits'] ?? null) : null;

        if (! is_numeric($rate)) {
            $code = is_array($data) ? ($data['code'] ?? null) : null;
            $message = is_array($data) ? ($data['message'] ?? null) : null;
            $detail = $code ? " (code {$code}: {$message})" : '';
            throw new RuntimeException("LabsMobile did not return a valid {$country} price{$detail}.");
        }

        return (float) $rate;
    }

    public function deliveryAckUrl(): ?string
    {
        $baseUrl = trim((string) config('services.labsmobile.ack_url'));
        $token = trim((string) config('services.labsmobile.webhook_token'));

        if ($baseUrl === '' || $token === '') {
            return null;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.'token='.rawurlencode($token);
    }

    /**
     * @return array{characters: int, segments: int, unicode: bool}
     */
    public function messageProfile(string $message): array
    {
        $unicode = (bool) preg_match('/[^\x{000A}\x{000D}\x{0020}-\x{007E}£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¤¡ÄÖÑÜ§¿äöñüà€]/u', $message);
        $characters = mb_strlen($message);

        if ($unicode) {
            $segments = $characters <= 70 ? 1 : (int) ceil($characters / 67);
        } else {
            $extendedCharacters = preg_match_all('/[\^{}\[\]~|€\\\\]/u', $message) ?: 0;
            $septets = $characters + $extendedCharacters;
            $segments = $septets <= 160 ? 1 : (int) ceil($septets / 153);
        }

        return [
            'characters' => $characters,
            'segments' => max(1, $segments),
            'unicode' => $unicode,
        ];
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

    /**
     * @return array{string, string}
     */
    private function credentials(): array
    {
        $username = trim((string) config('services.labsmobile.username'));
        $token = trim((string) config('services.labsmobile.token'));

        if ($username === '' || $token === '') {
            throw new RuntimeException('LabsMobile credentials are not configured.');
        }

        return [$username, $token];
    }
}
