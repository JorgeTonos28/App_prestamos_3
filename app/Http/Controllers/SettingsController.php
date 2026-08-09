<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Setting;
use App\Models\SmsNotification;
use App\Services\SmsDispatchService;
use App\Services\WhatsAppAgentSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit(
        Request $request,
        SmsDispatchService $smsDispatcher,
        WhatsAppAgentSettings $agentSettings
    )
    {
        $settings = $agentSettings->all();
        $tabs = ['general', 'loans', 'legal', 'email', 'sms', 'whatsapp'];
        $activeTab = in_array($request->input('tab'), $tabs, true)
            ? $request->input('tab')
            : 'general';

        $smsToRules = ['nullable', 'date'];
        if ($request->filled('sms_from')) {
            $smsToRules[] = 'after_or_equal:sms_from';
        }

        $smsFilters = $request->validate([
            'sms_search' => ['nullable', 'string', 'max:150'],
            'sms_from' => ['nullable', 'date'],
            'sms_to' => $smsToRules,
            'sms_status' => ['nullable', 'in:pending,simulated,accepted,delivered,failed'],
            'sms_source' => ['nullable', 'in:manual,overdue'],
        ]);

        $providerConfigured = trim((string) config('services.labsmobile.username')) !== ''
            && trim((string) config('services.labsmobile.token')) !== '';

        // The SMS screen should always show a fresh balance when it is opened.
        // Failures are deliberately non-blocking: history/settings must remain usable.
        if ($activeTab === 'sms' && $providerConfigured) {
            $smsDispatcher->refreshBalanceCacheQuietly();
        }

        $creditRate = $providerConfigured
            ? $smsDispatcher->dominicanCreditRate()
            : 0.0;
        $costPerCreditDop = max(0, (float) ($settings['sms_cost_per_credit'] ?? 0));

        $smsQuery = SmsNotification::query()
            ->with([
                'client:id,first_name,last_name,client_code',
                'loan:id,code',
                'sentBy:id,name',
            ])
            ->latest('id');

        if (! empty($smsFilters['sms_from'])) {
            $smsQuery->whereDate('notification_date', '>=', $smsFilters['sms_from']);
        }

        if (! empty($smsFilters['sms_to'])) {
            $smsQuery->whereDate('notification_date', '<=', $smsFilters['sms_to']);
        }

        if (! empty($smsFilters['sms_status'])) {
            $smsQuery->where('status', $smsFilters['sms_status']);
        }

        if (! empty($smsFilters['sms_source'])) {
            $smsQuery->where('source', $smsFilters['sms_source']);
        }

        if (! empty($smsFilters['sms_search'])) {
            $search = trim($smsFilters['sms_search']);
            $smsQuery->where(function ($query) use ($search) {
                $query->where('phone', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                    ->orWhere('provider_subid', 'like', "%{$search}%")
                    ->orWhere('api_code', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('client_code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('loan', fn ($loanQuery) => $loanQuery->where('code', 'like', "%{$search}%"))
                    ->orWhereHas('sentBy', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $totalSegments = (int) (clone $smsQuery)->sum('segment_count');
        $billableSegments = (int) (clone $smsQuery)
            ->whereNotNull('sent_at')
            ->where('status', '!=', 'simulated')
            ->sum('segment_count');
        $calculatedCredits = $creditRate > 0
            ? round($billableSegments * $creditRate, 4)
            : (float) (clone $smsQuery)->sum('credits_used');

        $smsSummary = [
            'total' => (clone $smsQuery)->count(),
            'sms_count' => $totalSegments,
            'successful' => (clone $smsQuery)->whereIn('status', ['simulated', 'accepted', 'delivered'])->count(),
            'delivered' => (clone $smsQuery)->where('status', 'delivered')->count(),
            'failed' => (clone $smsQuery)->where('status', 'failed')->count(),
            'credits_used' => $calculatedCredits,
            'estimated_cost' => round($calculatedCredits * $costPerCreditDop, 4),
            'currency' => 'DOP',
        ];

        $smsHistory = $smsQuery->paginate(15)->withQueryString();
        $smsHistory->getCollection()->transform(function (SmsNotification $notification) use ($creditRate, $costPerCreditDop) {
            $isBillable = $notification->sent_at !== null && $notification->status !== 'simulated';
            $displayCredits = $isBillable && $creditRate > 0
                ? round($notification->segment_count * $creditRate, 4)
                : ($isBillable ? (float) $notification->credits_used : 0.0);

            $notification->setAttribute('display_credits', $displayCredits);
            $notification->setAttribute('display_cost_dop', round($displayCredits * $costPerCreditDop, 4));
            $notification->setAttribute('sms_count', (int) $notification->segment_count);
            $notification->setAttribute(
                'delivery_diagnostic',
                $notification->delivery_details['diagnostic']
                    ?? $notification->error_message
                    ?? null
            );

            return $notification;
        });

        $ackUrl = trim((string) config('services.labsmobile.ack_url'));
        $webhookToken = trim((string) config('services.labsmobile.webhook_token'));

        return Inertia::render('Settings/Edit', [
            'settings' => [
                ...$settings,
                'sms_cost_currency' => 'DOP',
            ],
            'activeTab' => $activeTab,
            'clients' => Client::query()
                ->whereNotNull('phone')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'client_code', 'first_name', 'last_name', 'phone', 'status']),
            'smsHistory' => $smsHistory,
            'smsFilters' => [
                'sms_search' => $smsFilters['sms_search'] ?? '',
                'sms_from' => $smsFilters['sms_from'] ?? '',
                'sms_to' => $smsFilters['sms_to'] ?? '',
                'sms_status' => $smsFilters['sms_status'] ?? '',
                'sms_source' => $smsFilters['sms_source'] ?? '',
            ],
            'smsSummary' => $smsSummary,
            'smsProvider' => [
                'enabled' => (bool) config('services.labsmobile.enabled', false),
                'configured' => $providerConfigured,
                'test_mode' => (bool) config('services.labsmobile.test_mode', true),
                'balance' => Cache::get('labsmobile.balance'),
                'credit_rate' => $creditRate,
                'cost_per_credit_dop' => $costPerCreditDop,
                'ack_configured' => $ackUrl !== '' && $webhookToken !== '',
                'ack_https' => str_starts_with(strtolower($ackUrl), 'https://'),
            ],
            'whatsappAgent' => [
                ...$agentSettings->configurationStatus(),
                'webhook_url' => route('webhooks.whatsapp.receive'),
                'document_catalog' => collect(WhatsAppAgentSettings::DOCUMENT_CATALOG)
                    ->map(fn (string $label, string $key): array => compact('key', 'label'))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function update(Request $request, WhatsAppAgentSettings $agentSettings)
    {
        $validated = $request->validate([
            'app_name' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:1024',
            'dark_logo' => 'nullable|image|max:1024',
            'favicon' => 'nullable|image|mimes:ico,png|max:512',
            'email_sender_name' => 'nullable|string|max:255',
            'email_sender_address' => 'nullable|email|max:255',
            'overdue_email_subject' => 'nullable|string|max:255',
            'overdue_email_body' => 'nullable|string',
            'overdue_sms_enabled' => 'nullable|boolean',
            'overdue_sms_send_time' => 'nullable|date_format:H:i',
            'overdue_sms_interval_days' => 'nullable|integer|min:1|max:365',
            'overdue_sms_messages_per_day' => 'nullable|integer|min:1|max:5',
            'overdue_sms_body' => 'nullable|string|max:1000',
            'sms_cost_per_credit' => 'nullable|numeric|min:0|max:999999',
            'sidebar_logo_height' => 'nullable|integer|min:20|max:120',
            'color_theme' => 'nullable|in:default,carolina,pinky',
            'butterfly_enabled' => 'nullable|boolean',
            'butterfly_color' => 'nullable|in:rose,violet,sunset',
            'butterfly_interval_seconds' => 'nullable|integer|min:10|max:120',
            'global_late_fee_daily_amount' => 'nullable|numeric|min:0',
            'global_late_fee_grace_period' => 'nullable|integer|min:0',
            'global_late_fee_cutoff_mode' => 'nullable|in:dynamic_payment,fixed_cutoff',
            'global_payment_accrual_mode' => 'nullable|in:realtime,cutoff_only',
            'global_cutoff_cycle_mode' => 'nullable|in:calendar,fixed_dates',
            'global_month_day_count_mode' => 'nullable|in:exact,thirty',
            'global_late_fee_trigger_value' => 'nullable|integer|min:0',
            'global_late_fee_day_type' => 'nullable|in:business,calendar',
            'legal_fee_default_amount' => 'nullable|numeric|min:0',
            'legal_contract_template' => 'nullable|string',
            'legal_entry_fee_default' => 'nullable|numeric|min:0',
            'legal_days_overdue_threshold' => 'nullable|integer|min:0',
            'admin_notification_email' => 'nullable|email|max:255',
            'disable_payment_deletion' => 'nullable|boolean',
            'whatsapp_agent_enabled' => 'nullable|boolean',
            'whatsapp_graph_version' => ['nullable', 'regex:/^v\d{1,2}\.\d$/'],
            'whatsapp_phone_number_id' => ['nullable', 'regex:/^\d{5,30}$/'],
            'whatsapp_business_account_id' => ['nullable', 'regex:/^\d{5,30}$/'],
            'whatsapp_template_language' => ['nullable', 'regex:/^[a-z]{2,3}_[A-Z]{2}$/'],
            'whatsapp_approval_template' => ['nullable', 'regex:/^[a-z0-9_]*$/', 'max:512'],
            'whatsapp_rejection_template' => ['nullable', 'regex:/^[a-z0-9_]*$/', 'max:512'],
            'whatsapp_agent_welcome_message' => ['nullable', 'string', 'max:2000'],
            'whatsapp_agent_privacy_notice' => ['nullable', 'string', 'max:3000'],
            'whatsapp_agent_additional_instructions' => ['nullable', 'string', 'max:5000'],
            'whatsapp_required_documents' => ['nullable', 'array'],
            'whatsapp_required_documents.*' => ['string', Rule::in(array_keys(WhatsAppAgentSettings::DOCUMENT_CATALOG))],
            'whatsapp_max_document_mb' => ['nullable', 'integer', 'min:1', 'max:25'],
            'whatsapp_application_expiry_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'whatsapp_auto_create_client' => ['nullable', 'boolean'],
            'openai_model' => ['nullable', 'regex:/^[a-zA-Z0-9._-]+$/', 'max:100'],
            'openai_reasoning_effort' => ['nullable', Rule::in(['none', 'low', 'medium', 'high', 'xhigh', 'max'])],
            'risk_low_max_score' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'risk_medium_max_score' => ['nullable', 'numeric', 'min:1', 'max:100', 'gte:risk_low_max_score'],
            'risk_max_debt_to_income' => ['nullable', 'numeric', 'min:0.01', 'max:2'],
            'risk_max_installment_to_income' => ['nullable', 'numeric', 'min:0.01', 'max:2'],
            'risk_max_loan_to_monthly_income' => ['nullable', 'numeric', 'min:0.1', 'max:120'],
            'risk_min_monthly_income' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'risk_min_employment_months' => ['nullable', 'integer', 'min:0', 'max:720'],
            'risk_policy_notes' => ['nullable', 'string', 'max:5000'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:10000'],
            'whatsapp_app_secret' => ['nullable', 'string', 'max:1000'],
            'whatsapp_verify_token' => ['nullable', 'string', 'min:16', 'max:1000'],
            'openai_api_key' => ['nullable', 'string', 'max:1000'],
            'clear_whatsapp_access_token' => ['nullable', 'boolean'],
            'clear_whatsapp_app_secret' => ['nullable', 'boolean'],
            'clear_whatsapp_verify_token' => ['nullable', 'boolean'],
            'clear_openai_api_key' => ['nullable', 'boolean'],
        ]);

        if ($request->has('app_name')) {
            Setting::updateOrCreate(['key' => 'app_name'], ['value' => $validated['app_name']]);
        }

        if ($request->has('sidebar_logo_height')) {
            Setting::updateOrCreate(['key' => 'sidebar_logo_height'], ['value' => $request->input('sidebar_logo_height')]);
        }

        if ($request->has('color_theme')) {
            $normalizedTheme = ($validated['color_theme'] ?? 'default') === 'pinky'
                ? 'carolina'
                : ($validated['color_theme'] ?? 'default');

            Setting::updateOrCreate(['key' => 'color_theme'], ['value' => $normalizedTheme]);
        }

        if ($request->has('butterfly_enabled')) {
            Setting::updateOrCreate(['key' => 'butterfly_enabled'], ['value' => $request->boolean('butterfly_enabled') ? '1' : '0']);
        }

        if ($request->has('butterfly_color')) {
            Setting::updateOrCreate(['key' => 'butterfly_color'], ['value' => $validated['butterfly_color']]);
        }

        if ($request->has('butterfly_interval_seconds')) {
            Setting::updateOrCreate(['key' => 'butterfly_interval_seconds'], ['value' => (string) $validated['butterfly_interval_seconds']]);
        }

        $emailKeys = ['email_sender_name', 'email_sender_address', 'overdue_email_subject', 'overdue_email_body'];
        foreach ($emailKeys as $key) {
            if ($request->has($key)) {
                Setting::updateOrCreate(['key' => $key], ['value' => $validated[$key]]);
            }
        }

        if ($request->has('overdue_sms_enabled')) {
            Setting::updateOrCreate(['key' => 'overdue_sms_enabled'], ['value' => $request->boolean('overdue_sms_enabled') ? '1' : '0']);
        }

        $smsKeys = [
            'overdue_sms_send_time',
            'overdue_sms_interval_days',
            'overdue_sms_messages_per_day',
            'overdue_sms_body',
            'sms_cost_per_credit',
        ];
        foreach ($smsKeys as $key) {
            if ($request->has($key)) {
                Setting::updateOrCreate(['key' => $key], ['value' => (string) $validated[$key]]);
            }
        }
        Setting::updateOrCreate(['key' => 'sms_cost_currency'], ['value' => 'DOP']);

        if ($request->has('global_late_fee_daily_amount')) {
            Setting::updateOrCreate(['key' => 'global_late_fee_daily_amount'], ['value' => $validated['global_late_fee_daily_amount']]);
        }

        if ($request->has('global_late_fee_grace_period')) {
            Setting::updateOrCreate(['key' => 'global_late_fee_grace_period'], ['value' => $validated['global_late_fee_grace_period']]);
        }

        if ($request->has('global_late_fee_cutoff_mode')) {
            Setting::updateOrCreate(['key' => 'global_late_fee_cutoff_mode'], ['value' => $validated['global_late_fee_cutoff_mode']]);
        }

        if ($request->has('global_payment_accrual_mode')) {
            Setting::updateOrCreate(['key' => 'global_payment_accrual_mode'], ['value' => $validated['global_payment_accrual_mode']]);
        }

        if ($request->has('global_cutoff_cycle_mode')) {
            Setting::updateOrCreate(['key' => 'global_cutoff_cycle_mode'], ['value' => $validated['global_cutoff_cycle_mode']]);
        }

        if ($request->has('global_month_day_count_mode')) {
            Setting::updateOrCreate(['key' => 'global_month_day_count_mode'], ['value' => $validated['global_month_day_count_mode']]);
        }

        Setting::updateOrCreate(['key' => 'global_late_fee_trigger_type'], ['value' => 'installments']);

        if ($request->has('global_late_fee_trigger_value')) {
            Setting::updateOrCreate(['key' => 'global_late_fee_trigger_value'], ['value' => $validated['global_late_fee_trigger_value']]);
        }

        if ($request->has('global_late_fee_day_type')) {
            Setting::updateOrCreate(['key' => 'global_late_fee_day_type'], ['value' => $validated['global_late_fee_day_type']]);
        }

        if ($request->has('legal_fee_default_amount')) {
            Setting::updateOrCreate(['key' => 'legal_fee_default_amount'], ['value' => $validated['legal_fee_default_amount']]);
        }

        if ($request->has('legal_contract_template')) {
            Setting::updateOrCreate(['key' => 'legal_contract_template'], ['value' => $validated['legal_contract_template']]);
        }

        if ($request->has('legal_entry_fee_default')) {
            Setting::updateOrCreate(['key' => 'legal_entry_fee_default'], ['value' => $validated['legal_entry_fee_default']]);
        }

        if ($request->has('legal_days_overdue_threshold')) {
            Setting::updateOrCreate(['key' => 'legal_days_overdue_threshold'], ['value' => $validated['legal_days_overdue_threshold']]);
        }

        if ($request->has('admin_notification_email')) {
            Setting::updateOrCreate(['key' => 'admin_notification_email'], ['value' => $validated['admin_notification_email']]);
        }

        if ($request->has('disable_payment_deletion')) {
            Setting::updateOrCreate(['key' => 'disable_payment_deletion'], ['value' => $request->boolean('disable_payment_deletion') ? '1' : '0']);
        }

        $agentScalarKeys = [
            'whatsapp_graph_version',
            'whatsapp_phone_number_id',
            'whatsapp_business_account_id',
            'whatsapp_template_language',
            'whatsapp_approval_template',
            'whatsapp_rejection_template',
            'whatsapp_agent_welcome_message',
            'whatsapp_agent_privacy_notice',
            'whatsapp_agent_additional_instructions',
            'whatsapp_max_document_mb',
            'whatsapp_application_expiry_days',
            'openai_model',
            'openai_reasoning_effort',
            'risk_low_max_score',
            'risk_medium_max_score',
            'risk_max_debt_to_income',
            'risk_max_installment_to_income',
            'risk_max_loan_to_monthly_income',
            'risk_min_monthly_income',
            'risk_min_employment_months',
            'risk_policy_notes',
        ];
        foreach ($agentScalarKeys as $key) {
            if ($request->has($key)) {
                $agentSettings->put($key, $validated[$key] ?? '');
            }
        }

        if ($request->has('whatsapp_required_documents')) {
            $agentSettings->put('whatsapp_required_documents', $validated['whatsapp_required_documents'] ?? []);
        }

        if ($request->has('whatsapp_auto_create_client')) {
            $agentSettings->put('whatsapp_auto_create_client', $request->boolean('whatsapp_auto_create_client') ? '1' : '0');
        }

        foreach (WhatsAppAgentSettings::SECRET_KEYS as $secretKey) {
            $clearKey = 'clear_'.$secretKey;
            if ($request->boolean($clearKey)) {
                $agentSettings->forgetSecret($secretKey);
            } elseif (filled($validated[$secretKey] ?? null)) {
                $agentSettings->putSecret($secretKey, $validated[$secretKey]);
            }
        }

        if ($request->has('whatsapp_agent_enabled')) {
            if ($request->boolean('whatsapp_agent_enabled')) {
                $status = $agentSettings->configurationStatus();
                if (! $status['whatsapp_ready'] || ! $status['openai_ready']) {
                    return redirect()->back()->withErrors([
                        'whatsapp_agent_enabled' => 'Configura Phone Number ID, Access Token, App Secret, Verify Token y OpenAI API Key antes de habilitar el agente.',
                    ]);
                }
            }

            $agentSettings->put('whatsapp_agent_enabled', $request->boolean('whatsapp_agent_enabled') ? '1' : '0');
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'logo_path'], ['value' => Storage::url($path)]);
        }

        if ($request->hasFile('dark_logo')) {
            $path = $request->file('dark_logo')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'dark_logo_path'], ['value' => Storage::url($path)]);
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'favicon_path'], ['value' => Storage::url($path)]);
        }

        return redirect()->back()->with('success', 'Configuración actualizada correctamente.');
    }
}
