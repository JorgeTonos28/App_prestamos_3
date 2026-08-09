<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class WhatsAppAgentSettings
{
    public const SECRET_KEYS = [
        'whatsapp_access_token',
        'whatsapp_app_secret',
        'whatsapp_verify_token',
        'openai_api_key',
    ];

    public const DOCUMENT_CATALOG = [
        'identity_document' => 'Documento de identidad',
        'credit_history' => 'Historial crediticio',
        'income_report' => 'Informe o constancia de ingresos',
        'credit_score' => 'Reporte de score crediticio',
        'bank_statements_6_months' => 'Estados de cuenta de los últimos 6 meses',
        'employment_letter' => 'Carta de trabajo',
        'personal_reference' => 'Referencia personal',
        'proof_of_address' => 'Comprobante de domicilio',
    ];

    public function defaults(): array
    {
        return [
            'whatsapp_agent_enabled' => '0',
            'whatsapp_graph_version' => (string) config('services.whatsapp_cloud.graph_version', 'v23.0'),
            'whatsapp_phone_number_id' => (string) config('services.whatsapp_cloud.phone_number_id', ''),
            'whatsapp_business_account_id' => (string) config('services.whatsapp_cloud.business_account_id', ''),
            'whatsapp_template_language' => 'es_DO',
            'whatsapp_approval_template' => '',
            'whatsapp_rejection_template' => '',
            'whatsapp_agent_welcome_message' => 'Hola. Soy el asistente virtual de préstamos. Te ayudaré a completar una solicitud para revisión humana.',
            'whatsapp_agent_privacy_notice' => 'Usaremos tus datos y documentos únicamente para evaluar tu solicitud, prevenir fraude y administrar la relación crediticia. Responde SI para aceptar o NO para cancelar.',
            'whatsapp_agent_additional_instructions' => '',
            'whatsapp_custom_documents' => '[]',
            'whatsapp_required_documents' => json_encode(array_keys(self::DOCUMENT_CATALOG), JSON_THROW_ON_ERROR),
            'whatsapp_max_document_mb' => '15',
            'whatsapp_application_expiry_days' => '30',
            'whatsapp_auto_create_client' => '1',
            'openai_model' => (string) config('services.openai.model', 'gpt-5.6-terra'),
            'openai_reasoning_effort' => 'medium',
            'risk_low_max_score' => '35',
            'risk_medium_max_score' => '65',
            'risk_max_debt_to_income' => '0.40',
            'risk_max_installment_to_income' => '0.35',
            'risk_max_loan_to_monthly_income' => '6',
            'risk_min_monthly_income' => '15000',
            'risk_min_employment_months' => '6',
            'risk_policy_notes' => '',
        ];
    }

    public function all(): array
    {
        $stored = Schema::hasTable('settings')
            ? Setting::query()->whereNotIn('key', self::SECRET_KEYS)->pluck('value', 'key')->all()
            : [];

        return [...$this->defaults(), ...$stored];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default ? '1' : '0');

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public function secret(string $key): ?string
    {
        if (! in_array($key, self::SECRET_KEYS, true)) {
            throw new \InvalidArgumentException("{$key} is not a registered secret setting.");
        }

        $encrypted = Schema::hasTable('settings')
            ? Setting::query()->where('key', $key)->value('value')
            : null;

        if (filled($encrypted)) {
            try {
                return Crypt::decryptString((string) $encrypted);
            } catch (DecryptException) {
                return null;
            }
        }

        return match ($key) {
            'whatsapp_access_token' => config('services.whatsapp_cloud.access_token'),
            'whatsapp_app_secret' => config('services.whatsapp_cloud.app_secret'),
            'whatsapp_verify_token' => config('services.whatsapp_cloud.verify_token'),
            'openai_api_key' => config('services.openai.api_key'),
        };
    }

    public function put(string $key, mixed $value): void
    {
        if (in_array($key, self::SECRET_KEYS, true)) {
            throw new \InvalidArgumentException('Use putSecret() for sensitive settings.');
        }

        Setting::updateOrCreate(['key' => $key], ['value' => is_array($value)
            ? json_encode($value, JSON_THROW_ON_ERROR)
            : (string) $value]);
    }

    public function putSecret(string $key, string $value): void
    {
        if (! in_array($key, self::SECRET_KEYS, true)) {
            throw new \InvalidArgumentException("{$key} is not a registered secret setting.");
        }

        Setting::updateOrCreate([
            'key' => $key,
        ], [
            'value' => Crypt::encryptString($value),
        ]);
    }

    public function forgetSecret(string $key): void
    {
        if (in_array($key, self::SECRET_KEYS, true)) {
            Setting::query()->where('key', $key)->delete();
        }
    }

    public function secretStatus(): array
    {
        return collect(self::SECRET_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => filled($this->secret($key))])
            ->all();
    }

    public function requiredDocuments(): array
    {
        $selected = json_decode((string) $this->get('whatsapp_required_documents', '[]'), true);
        $selected = is_array($selected) ? $selected : [];
        $catalog = collect($this->documentCatalog())->keyBy('key');

        return collect($selected)
            ->filter(fn ($key): bool => $catalog->has((string) $key))
            ->unique()
            ->map(fn ($key): array => [
                'key' => (string) $key,
                'label' => $catalog->get((string) $key)['label'],
                'required' => true,
            ])
            ->values()
            ->all();
    }

    public function documentCatalog(): array
    {
        $builtIn = collect(self::DOCUMENT_CATALOG)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'custom' => false,
            ]);
        $decoded = json_decode((string) $this->get('whatsapp_custom_documents', '[]'), true);

        $custom = collect(is_array($decoded) ? $decoded : [])
            ->filter(fn ($document): bool => is_array($document))
            ->map(fn (array $document): array => [
                'key' => trim((string) ($document['key'] ?? '')),
                'label' => trim((string) ($document['label'] ?? '')),
                'custom' => true,
            ])
            ->filter(fn (array $document): bool => preg_match('/^[a-z0-9_]{2,80}$/', $document['key']) === 1
                && $document['label'] !== ''
                && ! array_key_exists($document['key'], self::DOCUMENT_CATALOG))
            ->unique('key');

        return $builtIn->concat($custom)->values()->all();
    }

    public function configurationStatus(): array
    {
        $settings = $this->all();
        $secrets = $this->secretStatus();

        return [
            'enabled' => $this->boolean('whatsapp_agent_enabled'),
            'whatsapp_ready' => filled($settings['whatsapp_phone_number_id'])
                && $secrets['whatsapp_access_token']
                && $secrets['whatsapp_app_secret']
                && $secrets['whatsapp_verify_token'],
            'openai_ready' => $secrets['openai_api_key'],
            'auto_create_client' => $this->boolean('whatsapp_auto_create_client', true),
            'secrets' => $secrets,
        ];
    }
}
