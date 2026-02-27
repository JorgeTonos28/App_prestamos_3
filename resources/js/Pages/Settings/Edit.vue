<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent } from '@/Components/ui/card';

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    app_name: props.settings.app_name || 'LendApp',
    logo: null,
    dark_logo: null,
    favicon: null,
    email_sender_name: props.settings.email_sender_name || 'LendApp Notifications',
    email_sender_address: props.settings.email_sender_address || 'noreply@lendapp.com',
    overdue_email_subject: props.settings.overdue_email_subject || 'Aviso de Atraso en Préstamo',
    overdue_email_body: props.settings.overdue_email_body || 'Estimado cliente, le recordamos que tiene cuotas vencidas en su préstamo. Por favor realice el pago lo antes posible.',
    sidebar_logo_height: props.settings.sidebar_logo_height || '40', // Default 40px
    color_theme: ['carolina', 'pinky'].includes(String(props.settings.color_theme || '').toLowerCase()) ? 'carolina' : 'default',
    butterfly_enabled: ['1', 'true', 'yes', 'on'].includes(String(props.settings.butterfly_enabled ?? '0').toLowerCase()),
    butterfly_color: props.settings.butterfly_color || 'rose',
    butterfly_interval_seconds: Number(props.settings.butterfly_interval_seconds || 30),
    global_late_fee_daily_amount: props.settings.global_late_fee_daily_amount ?? '100.00',
    global_late_fee_grace_period: props.settings.global_late_fee_grace_period ?? 3,
    global_late_fee_cutoff_mode: props.settings.global_late_fee_cutoff_mode ?? 'dynamic_payment',
    global_payment_accrual_mode: props.settings.global_payment_accrual_mode ?? 'realtime',
    global_cutoff_cycle_mode: props.settings.global_cutoff_cycle_mode ?? 'calendar',
    global_month_day_count_mode: props.settings.global_month_day_count_mode ?? 'exact',
    global_late_fee_trigger_type: props.settings.global_late_fee_trigger_type ?? 'days',
    global_late_fee_trigger_value: Number(props.settings.global_late_fee_trigger_value ?? 3),
    global_late_fee_day_type: props.settings.global_late_fee_day_type ?? 'business',
    legal_fee_default_amount: props.settings.legal_fee_default_amount ?? '1000.00',
    legal_contract_template: props.settings.legal_contract_template ?? '',
    legal_entry_fee_default: props.settings.legal_entry_fee_default ?? '4000.00',
    legal_days_overdue_threshold: props.settings.legal_days_overdue_threshold ?? 30,
    admin_notification_email: props.settings.admin_notification_email ?? '',
    disable_payment_deletion: ['1', 'true', 'yes', 'on'].includes(String(props.settings.disable_payment_deletion ?? '0').toLowerCase()),
});

const submit = () => {
    form.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('logo', 'dark_logo', 'favicon');
        },
    });
};
</script>

<template>
    <Head title="Configuración" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-surface-800 leading-tight">Configuración del Sistema</h2>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto space-y-6">

                <Card class="rounded-2xl shadow-sm border border-surface-100 overflow-hidden">
                    <div class="p-6 border-b border-surface-100 bg-surface-50/50">
                        <h3 class="font-bold text-lg text-surface-800">Identidad Visual</h3>
                        <p class="text-sm text-surface-500">Personaliza la apariencia de la aplicación.</p>
                    </div>
                    <CardContent class="p-8">
                        <form @submit.prevent="submit" class="space-y-8">

                            <div class="space-y-4">
                                <div>
                                    <Label for="app_name">Nombre de la Aplicación</Label>
                                    <Input id="app_name" v-model="form.app_name" class="max-w-md mt-1" />
                                </div>
                                <div class="space-y-3 max-w-xl">
                                    <Label>Tema de Color</Label>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-1">
                                        <label
                                            class="rounded-2xl border px-4 py-4 cursor-pointer transition-all"
                                            :class="form.color_theme === 'default'
                                                ? 'border-primary-400 bg-primary-50 shadow-sm'
                                                : 'border-surface-200 bg-white hover:border-surface-300'"
                                        >
                                            <input v-model="form.color_theme" type="radio" name="color_theme" value="default" class="sr-only" />
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="font-semibold text-surface-800">Default</p>
                                                    <p class="text-xs text-surface-500">Clásico azul y neutro.</p>
                                                </div>
                                                <span class="h-4 w-4 rounded-full border-2" :class="form.color_theme === 'default' ? 'border-primary-500 bg-primary-500' : 'border-surface-300'" />
                                            </div>
                                        </label>

                                        <label
                                            class="rounded-2xl border px-4 py-4 cursor-pointer transition-all"
                                            :class="form.color_theme === 'carolina'
                                                ? 'border-primary-400 bg-primary-50 shadow-sm'
                                                : 'border-surface-200 bg-white hover:border-surface-300'"
                                        >
                                            <input v-model="form.color_theme" type="radio" name="color_theme" value="carolina" class="sr-only" />
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="font-semibold text-surface-800">Carolina</p>
                                                    <p class="text-xs text-surface-500">Rosa/violeta con mariposas ✨</p>
                                                </div>
                                                <span class="h-4 w-4 rounded-full border-2" :class="form.color_theme === 'carolina' ? 'border-primary-500 bg-primary-500' : 'border-surface-300'" />
                                            </div>
                                        </label>
                                    </div>
                                    <p class="text-xs text-surface-500">Elige el estilo visual para toda la app.</p>
                                </div>

                                <div
                                    v-if="form.color_theme === 'carolina'"
                                    class="rounded-2xl border border-primary-200 bg-primary-50/60 p-5 space-y-4"
                                >
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <h4 class="font-semibold text-primary-800">Mariposa Mascota ✨</h4>
                                            <p class="text-xs text-primary-700">Aparece aleatoriamente en toda la app cuando el tema Carolina está activo.</p>
                                        </div>
                                        <label class="inline-flex items-center gap-2 rounded-xl border border-primary-200 bg-white px-3 py-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                v-model="form.butterfly_enabled"
                                                class="h-4 w-4 rounded border-primary-300 text-primary-600 focus:ring-primary-500"
                                            />
                                            <span class="text-sm font-medium text-primary-700">Activar</span>
                                        </label>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <Label for="butterfly_color">Color de Mariposa</Label>
                                            <select
                                                id="butterfly_color"
                                                v-model="form.butterfly_color"
                                                :disabled="!form.butterfly_enabled"
                                                class="flex h-11 w-full rounded-xl border border-primary-200 bg-white px-4 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:opacity-60"
                                            >
                                                <option value="rose">Rose Bloom</option>
                                                <option value="violet">Lavender Glow</option>
                                                <option value="sunset">Coral Sunset</option>
                                            </select>
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="butterfly_interval_seconds">Frecuencia (segundos)</Label>
                                            <Input
                                                id="butterfly_interval_seconds"
                                                type="number"
                                                min="10"
                                                max="120"
                                                v-model.number="form.butterfly_interval_seconds"
                                                :disabled="!form.butterfly_enabled"
                                            />
                                            <p class="text-xs text-primary-700">Intervalo base. La aparición exacta tendrá una variación aleatoria para verse natural.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="h-px bg-surface-100"></div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-4">
                                    <Label for="logo">Logo Principal (Light Mode)</Label>
                                    <div class="flex items-start gap-4">
                                        <div v-if="settings.logo_path" class="w-20 h-20 bg-surface-100 rounded-xl flex items-center justify-center border border-surface-200 p-2">
                                            <img :src="settings.logo_path" alt="Logo Actual" class="max-h-full max-w-full" />
                                        </div>
                                        <div v-else class="w-20 h-20 bg-surface-100 rounded-xl flex items-center justify-center border border-surface-200 text-surface-400">
                                            <i class="fa-solid fa-image text-2xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <Input id="logo" type="file" @input="form.logo = $event.target.files[0]" accept="image/*" class="mt-1" />
                                            <p class="text-xs text-surface-500 mt-2">
                                                Usado en Login y fondos claros.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <Label for="dark_logo">Logo Menú (Dark Mode)</Label>
                                    <div class="flex items-start gap-4">
                                        <div v-if="settings.dark_logo_path" class="w-20 h-20 bg-surface-800 rounded-xl flex items-center justify-center border border-surface-700 p-2">
                                            <img :src="settings.dark_logo_path" alt="Logo Dark" class="max-h-full max-w-full" />
                                        </div>
                                        <div v-else class="w-20 h-20 bg-surface-800 rounded-xl flex items-center justify-center border border-surface-700 text-surface-400">
                                            <i class="fa-solid fa-image text-2xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <Input id="dark_logo" type="file" @input="form.dark_logo = $event.target.files[0]" accept="image/*" class="mt-1" />
                                            <p class="text-xs text-surface-500 mt-2">
                                                Usado en la barra lateral oscura.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <Label for="sidebar_logo_height">Altura del Logo en Menú (px)</Label>
                                        <div class="flex items-center gap-2 mt-1">
                                            <Input id="sidebar_logo_height" type="number" v-model="form.sidebar_logo_height" class="w-24" min="20" max="120" />
                                            <span class="text-sm text-surface-500">px</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <Label for="favicon">Favicon</Label>
                                    <div class="flex items-start gap-4">
                                        <div v-if="settings.favicon_path" class="w-12 h-12 bg-surface-100 rounded-lg flex items-center justify-center border border-surface-200 p-2">
                                            <img :src="settings.favicon_path" alt="Favicon Actual" class="max-h-full max-w-full" />
                                        </div>
                                        <div v-else class="w-12 h-12 bg-surface-100 rounded-lg flex items-center justify-center border border-surface-200 text-surface-400">
                                            <i class="fa-solid fa-globe"></i>
                                        </div>
                                        <div class="flex-1">
                                            <Input id="favicon" type="file" @input="form.favicon = $event.target.files[0]" accept="image/x-icon,image/png" class="mt-1" />
                                            <p class="text-xs text-surface-500 mt-2">
                                                Recomendado: 32x32px o 64x64px (ICO/PNG). Max 512KB.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="h-px bg-surface-100"></div>

                            <div class="space-y-4">
                                <h3 class="font-bold text-lg text-surface-800">Configuración de Mora</h3>
                                <p class="text-sm text-surface-500">Define el monto diario por defecto para mora automática.</p>

                            <div class="space-y-2 max-w-sm">
                                <Label for="global_late_fee_daily_amount">Monto de Mora Diario por Defecto (RD$)</Label>
                                <Input
                                    id="global_late_fee_daily_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.global_late_fee_daily_amount"
                                />
                            </div>

                            <div class="space-y-2 max-w-sm">
                                <Label for="global_late_fee_grace_period">Días de Gracia por Defecto (Laborables)</Label>
                                <Input
                                    id="global_late_fee_grace_period"
                                    type="number"
                                    min="0"
                                    v-model="form.global_late_fee_grace_period"
                                />
                            </div>

                            <div class="space-y-2 max-w-md">
                                <Label for="global_late_fee_cutoff_mode">Modo de corte para mora (global)</Label>
                                <select id="global_late_fee_cutoff_mode" v-model="form.global_late_fee_cutoff_mode" class="w-full rounded-md border border-surface-200 px-3 py-2">
                                    <option value="dynamic_payment">Dinámico por pagos</option>
                                    <option value="fixed_cutoff">Fijo por fecha de corte</option>
                                </select>
                            </div>

                            <div class="space-y-2 max-w-md">
                                <Label for="global_payment_accrual_mode">Devengo al registrar pagos (global)</Label>
                                <select id="global_payment_accrual_mode" v-model="form.global_payment_accrual_mode" class="w-full rounded-md border border-surface-200 px-3 py-2">
                                    <option value="realtime">En tiempo real</option>
                                    <option value="cutoff_only">Solo en cortes</option>
                                </select>
                            </div>

                            <div class="space-y-2 max-w-md">
                                <Label for="global_cutoff_cycle_mode">Ciclo de cortes (quincenal/mensual)</Label>
                                <select id="global_cutoff_cycle_mode" v-model="form.global_cutoff_cycle_mode" class="w-full rounded-md border border-surface-200 px-3 py-2">
                                    <option value="calendar">Calendario desde fecha de corte</option>
                                    <option value="fixed_dates">Fechas fijas</option>
                                </select>
                            </div>

                            <div class="space-y-2 max-w-md">
                                <Label for="global_month_day_count_mode">Cálculo de meses</Label>
                                <select id="global_month_day_count_mode" v-model="form.global_month_day_count_mode" class="w-full rounded-md border border-surface-200 px-3 py-2">
                                    <option value="exact">Días exactos del mes</option>
                                    <option value="thirty">Mes comercial (30 días)</option>
                                </select>
                            </div>

                            <div class="space-y-2 max-w-md">
                                <Label for="global_late_fee_trigger_type">Mora inicia por</Label>
                                <select id="global_late_fee_trigger_type" v-model="form.global_late_fee_trigger_type" class="w-full rounded-md border border-surface-200 px-3 py-2">
                                    <option value="days">Cantidad de días</option>
                                    <option value="installments">Cantidad de cuotas vencidas</option>
                                </select>
                            </div>

                            <div class="space-y-2 max-w-sm">
                                <Label for="global_late_fee_trigger_value">Valor disparador de mora</Label>
                                <Input id="global_late_fee_trigger_value" type="number" min="0" v-model="form.global_late_fee_trigger_value" />
                            </div>

                            <div class="space-y-2 max-w-md">
                                <Label for="global_late_fee_day_type">Tipo de días para mora</Label>
                                <select id="global_late_fee_day_type" v-model="form.global_late_fee_day_type" class="w-full rounded-md border border-surface-200 px-3 py-2">
                                    <option value="business">Días laborables</option>
                                    <option value="calendar">Días calendario</option>
                                </select>
                            </div>
                            </div>

                            <div class="h-px bg-surface-100"></div>

                            <div class="space-y-4">
                                <h3 class="font-bold text-lg text-surface-800">Gastos Legales</h3>
                                <p class="text-sm text-surface-500">Define el costo por defecto del documento legal y el modelo del contrato.</p>

                                <div class="space-y-2 max-w-sm">
                                    <Label for="legal_fee_default_amount">Costo Legal por Defecto (RD$)</Label>
                                    <Input
                                        id="legal_fee_default_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model="form.legal_fee_default_amount"
                                    />
                                </div>

                                <div class="space-y-2 max-w-sm">
                                    <Label for="legal_entry_fee_default">Costo Legal por Entrada a Legal (RD$)</Label>
                                    <Input
                                        id="legal_entry_fee_default"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model="form.legal_entry_fee_default"
                                    />
                                </div>

                                <div class="space-y-2 max-w-sm">
                                    <Label for="legal_days_overdue_threshold">Días de Mora para pasar a Legal</Label>
                                    <Input
                                        id="legal_days_overdue_threshold"
                                        type="number"
                                        min="0"
                                        v-model="form.legal_days_overdue_threshold"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="legal_contract_template">Modelo de Contrato</Label>
                                    <textarea
                                        id="legal_contract_template"
                                        v-model="form.legal_contract_template"
                                        class="flex min-h-[180px] w-full rounded-xl border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        placeholder="Use marcadores como {client_name}, {client_national_id}, {loan_code}, {loan_amount}, {loan_start_date}, {legal_fee_amount}."
                                    ></textarea>
                                    <p class="text-xs text-surface-500">
                                        Marcadores disponibles: {client_name}, {client_national_id}, {client_address}, {client_phone}, {client_email}, {loan_code}, {loan_start_date}, {loan_amount}, {legal_fee_amount}, {today_date}.
                                    </p>
                                </div>
                            </div>

                            <div class="h-px bg-surface-100"></div>

                            <div class="space-y-4">
                                <h3 class="font-bold text-lg text-surface-800">Notificaciones Administrativas</h3>
                                <p class="text-sm text-surface-500">Define el correo que recibirá los reportes diarios de mora y legal.</p>

                                <div class="space-y-2 max-w-sm">
                                    <Label for="admin_notification_email">Correo del Administrador</Label>
                                    <Input
                                        id="admin_notification_email"
                                        type="email"
                                        v-model="form.admin_notification_email"
                                        placeholder="admin@empresa.com"
                                    />
                                </div>
                            </div>

                            <div class="h-px bg-surface-100"></div>

                            <div class="space-y-4">
                                <h3 class="font-bold text-lg text-surface-800">Control de Pagos</h3>
                                <p class="text-sm text-surface-500">Puedes bloquear la eliminación de pagos en todos los préstamos.</p>

                                <label class="inline-flex items-center gap-3 rounded-xl border border-surface-200 bg-surface-50 px-4 py-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.disable_payment_deletion"
                                        class="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                                    />
                                    <span class="text-sm font-medium text-surface-700">Deshabilitar eliminación de pagos</span>
                                </label>
                            </div>

                            <div class="h-px bg-surface-100"></div>

                            <div class="space-y-4">
                                <h3 class="font-bold text-lg text-surface-800">Automatización de Correos</h3>
                                <p class="text-sm text-surface-500">Configura el envío automático de correos a clientes en mora.</p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="email_sender_name">Nombre del Remitente</Label>
                                        <Input id="email_sender_name" v-model="form.email_sender_name" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="email_sender_address">Correo del Remitente</Label>
                                        <Input id="email_sender_address" v-model="form.email_sender_address" type="email" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="overdue_email_subject">Asunto del Correo</Label>
                                    <Input id="overdue_email_subject" v-model="form.overdue_email_subject" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="overdue_email_body">Cuerpo del Mensaje</Label>
                                    <textarea
                                        id="overdue_email_body"
                                        v-model="form.overdue_email_body"
                                        class="flex min-h-[120px] w-full rounded-xl border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    ></textarea>
                                    <p class="text-xs text-surface-500">Variables disponibles: {client_name}, {amount_due}, {days_overdue}</p>
                                </div>
                            </div>

                            <div class="flex justify-end pt-4">
                                <Button type="submit" :disabled="form.processing" class="bg-primary-600 hover:bg-primary-700 text-white rounded-xl shadow-md px-8 cursor-pointer">
                                    <i class="fa-solid fa-save mr-2"></i> Guardar Cambios
                                </Button>
                            </div>

                        </form>
                    </CardContent>
                </Card>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
