<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import GeneralSettingsTab from '@/Pages/Settings/Partials/GeneralSettingsTab.vue';
import LoanSettingsTab from '@/Pages/Settings/Partials/LoanSettingsTab.vue';
import LegalSettingsTab from '@/Pages/Settings/Partials/LegalSettingsTab.vue';
import EmailSettingsTab from '@/Pages/Settings/Partials/EmailSettingsTab.vue';
import SmsSettingsTab from '@/Pages/Settings/Partials/SmsSettingsTab.vue';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
    activeTab: { type: String, default: 'general' },
    clients: { type: Array, default: () => [] },
    smsHistory: { type: Object, required: true },
    smsFilters: { type: Object, default: () => ({}) },
    smsSummary: { type: Object, required: true },
    smsProvider: { type: Object, required: true },
});

const truthy = (value) => ['1', 'true', 'yes', 'on'].includes(String(value ?? '').toLowerCase());

const tabs = [
    { id: 'general', label: 'General', icon: 'fa-palette', description: 'Identidad y apariencia' },
    { id: 'loans', label: 'Préstamos', icon: 'fa-sliders', description: 'Mora y pagos' },
    { id: 'legal', label: 'Legal', icon: 'fa-scale-balanced', description: 'Costes y contratos' },
    { id: 'email', label: 'Correo', icon: 'fa-envelope', description: 'Remitentes y reportes' },
    { id: 'sms', label: 'SMS', icon: 'fa-comment-sms', description: 'Envíos e historial' },
];

const currentTab = computed(() => tabs.find((tab) => tab.id === props.activeTab) || tabs[0]);

const form = useForm({
    app_name: props.settings.app_name || 'LendApp',
    logo: null,
    dark_logo: null,
    favicon: null,
    email_sender_name: props.settings.email_sender_name || 'LendApp Notifications',
    email_sender_address: props.settings.email_sender_address || 'noreply@lendapp.com',
    overdue_email_subject: props.settings.overdue_email_subject || 'Aviso de Atraso en Préstamo',
    overdue_email_body: props.settings.overdue_email_body || 'Estimado cliente, le recordamos que tiene cuotas vencidas en su préstamo. Por favor realice el pago lo antes posible.',
    sidebar_logo_height: props.settings.sidebar_logo_height || '40',
    color_theme: ['carolina', 'pinky'].includes(String(props.settings.color_theme || '').toLowerCase()) ? 'carolina' : 'default',
    butterfly_enabled: truthy(props.settings.butterfly_enabled),
    butterfly_color: props.settings.butterfly_color || 'rose',
    butterfly_interval_seconds: Number(props.settings.butterfly_interval_seconds || 30),
    global_late_fee_daily_amount: props.settings.global_late_fee_daily_amount ?? '100.00',
    global_late_fee_grace_period: props.settings.global_late_fee_grace_period ?? 3,
    global_late_fee_cutoff_mode: props.settings.global_late_fee_cutoff_mode ?? 'dynamic_payment',
    global_payment_accrual_mode: props.settings.global_payment_accrual_mode ?? 'realtime',
    global_cutoff_cycle_mode: props.settings.global_cutoff_cycle_mode ?? 'calendar',
    global_month_day_count_mode: props.settings.global_month_day_count_mode ?? 'exact',
    global_late_fee_trigger_type: 'installments',
    global_late_fee_trigger_value: Number(props.settings.global_late_fee_trigger_value ?? 3),
    global_late_fee_day_type: props.settings.global_late_fee_day_type ?? 'business',
    legal_fee_default_amount: props.settings.legal_fee_default_amount ?? '1000.00',
    legal_contract_template: props.settings.legal_contract_template ?? '',
    legal_entry_fee_default: props.settings.legal_entry_fee_default ?? '4000.00',
    legal_days_overdue_threshold: props.settings.legal_days_overdue_threshold ?? 30,
    admin_notification_email: props.settings.admin_notification_email ?? '',
    disable_payment_deletion: truthy(props.settings.disable_payment_deletion),
    overdue_sms_enabled: truthy(props.settings.overdue_sms_enabled),
    overdue_sms_send_time: props.settings.overdue_sms_send_time || '08:05',
    overdue_sms_interval_days: Number(props.settings.overdue_sms_interval_days || 1),
    overdue_sms_messages_per_day: Number(props.settings.overdue_sms_messages_per_day || 1),
    overdue_sms_body: props.settings.overdue_sms_body || 'Hola {client_first_name}. Tiene RD${amount_due} vencidos y {days_overdue} dias de atraso. Favor regularizar su pago. Gracias.',
    sms_cost_per_credit: props.settings.sms_cost_per_credit ?? '0.0000',
    sms_cost_currency: 'DOP',
});

const submit = () => {
    form.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset('logo', 'dark_logo', 'favicon'),
    });
};
</script>

<template>
    <Head title="Configuración" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-2xl font-bold leading-tight text-surface-800">Configuración</h2>
                <p class="text-sm text-surface-500">{{ currentTab.description }}</p>
            </div>
        </template>

        <div class="mx-auto min-w-0 max-w-7xl space-y-6 py-4">
            <nav class="overflow-x-auto rounded-2xl border border-surface-200 bg-white p-2 shadow-sm" aria-label="Secciones de configuración">
                <div class="flex min-w-max gap-1">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.id"
                        :href="route('settings.edit', { tab: tab.id })"
                        preserve-scroll
                        class="flex min-w-36 items-center gap-3 rounded-xl px-4 py-3 text-left transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500"
                        :class="activeTab === tab.id ? 'bg-primary-600 text-white shadow-sm' : 'text-surface-600 hover:bg-surface-50 hover:text-surface-900'"
                    >
                        <i class="fa-solid w-5 text-center" :class="tab.icon"></i>
                        <span>
                            <span class="block text-sm font-semibold">{{ tab.label }}</span>
                            <span class="block text-[11px]" :class="activeTab === tab.id ? 'text-primary-100' : 'text-surface-400'">{{ tab.description }}</span>
                        </span>
                    </Link>
                </div>
            </nav>

            <Card class="overflow-hidden rounded-2xl border border-surface-200 shadow-sm">
                <CardContent class="min-w-0 p-5 sm:p-8">
                    <GeneralSettingsTab v-if="activeTab === 'general'" :form="form" :settings="settings" />
                    <LoanSettingsTab v-else-if="activeTab === 'loans'" :form="form" />
                    <LegalSettingsTab v-else-if="activeTab === 'legal'" :form="form" />
                    <EmailSettingsTab v-else-if="activeTab === 'email'" :form="form" />
                    <SmsSettingsTab
                        v-else
                        :form="form"
                        :clients="clients"
                        :history="smsHistory"
                        :filters="smsFilters"
                        :summary="smsSummary"
                        :provider="smsProvider"
                    />

                    <div v-if="Object.keys(form.errors).length" class="mt-6 rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700" role="alert">
                        Revisa los campos marcados antes de guardar.
                    </div>

                    <div v-if="activeTab !== 'sms'" class="mt-8 flex justify-end border-t border-surface-100 pt-6">
                        <Button type="button" :disabled="form.processing" class="rounded-xl bg-primary-600 px-8 text-white hover:bg-primary-700" @click="submit">
                            <i class="fa-solid fa-save mr-2"></i>{{ form.processing ? 'Guardando...' : 'Guardar configuración' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
