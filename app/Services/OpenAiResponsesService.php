<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiResponsesService
{
    public function __construct(private readonly WhatsAppAgentSettings $settings) {}

    public function isConfigured(): bool
    {
        return filled($this->settings->secret('openai_api_key'));
    }

    public function structured(
        string $developerPrompt,
        array $userContent,
        string $schemaName,
        array $schema,
        int $maxOutputTokens = 2000
    ): array {
        $apiKey = $this->settings->secret('openai_api_key');
        if (! filled($apiKey)) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $model = trim((string) $this->settings->get('openai_model', 'gpt-5.6-terra'));
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $model)) {
            throw new RuntimeException('The configured OpenAI model identifier is invalid.');
        }

        $effort = (string) $this->settings->get('openai_reasoning_effort', 'medium');
        if (! in_array($effort, ['none', 'low', 'medium', 'high', 'xhigh', 'max'], true)) {
            $effort = 'medium';
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        if (! str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('OpenAI base URL must use HTTPS.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(120)
            ->post($baseUrl.'/responses', [
                'model' => $model,
                'store' => false,
                'reasoning' => ['effort' => $effort],
                'max_output_tokens' => max(256, min($maxOutputTokens, 12000)),
                'input' => [
                    [
                        'role' => 'developer',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => $developerPrompt,
                        ]],
                    ],
                    [
                        'role' => 'user',
                        'content' => $userContent,
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => $schemaName,
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException("OpenAI request failed with status {$response->status()}.");
        }

        $text = $this->outputText($response->json());
        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned an invalid structured response.');
        }

        return $decoded;
    }

    private function outputText(array $response): string
    {
        if (is_string($response['output_text'] ?? null) && $response['output_text'] !== '') {
            return $response['output_text'];
        }

        foreach (($response['output'] ?? []) as $output) {
            foreach (($output['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('OpenAI refused the requested analysis.');
                }

                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('OpenAI response did not contain output text.');
    }
}
