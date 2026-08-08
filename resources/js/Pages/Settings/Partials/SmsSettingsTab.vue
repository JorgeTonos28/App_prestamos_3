<script setup>
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import Pagination from '@/Components/Pagination.vue';
import SmsComposer from '@/Components/SmsComposer.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

const props = defineProps({
    form: { type: Object, required: true },
    clients: { type: Array, default: () => [] },
    history: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
    provider: { type: Object, required: true },
});

const filterForm = reactive({
    sms_search: props.filters.sms_search || '',
    sms_from: props.filters.sms_from || '',
    sms_to: props.filters.sms_to || '',
    sms_status: props.filters.sms_status || '',
    sms_source: props.filters.sms_source || '',
});

const balanceForm = useForm({});

const applyFilters = () => {
    router.get(route('settings.edit'), { tab: 'sms', ...filterForm }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilters = () => {
    Object.assign(filterForm, {
        sms_search: '',
        sms_from: '',
        sms_to: '',
        sms_status: '',
        sms_source: '',
    });
    router.get(route('settings.edit'), { tab: 'sms' }, { preserveScroll: true, replace: true });
};

const refreshBalance = () => balanceForm.post(route('settings.sms.balance'), { preserveScroll: true });

const statusLabels = {
    pending: 'Pendiente',
    simulated: 'Simulado',
    accepted: 'Aceptado',
    delivered: 'Entregado',
    failed: 'Fallido',
};

const statusClasses = {
    pending: 'bg-warning-50 text-warning-700 border-warning-200',
    simulated: 'bg-info-50 text-info-700 border-info-200',
    accepted: 'bg-primary-50 text-primary-700 border-primary-200',
    delivered: 'bg-success-50 text-success-700 border-success-200',
    failed: 'bg-danger-50 text-danger-700 border-danger-200',
};

const formatDateTime = (value) => value
    ? new Date(value).toLocaleString('es-DO', { dateStyle: 'short', timeStyle: 'short' })
    : '-';

const formatNumber = (value, maximumFractionDigits = 2) => new Intl.NumberFormat('es-DO', { maximumFractionDigits }).format(Number(value || 0));

const formatCost = (value, currency) => new Intl.NumberFormat('es-DO', {
    style: 'currency',
    currency: currency || 'EUR',
    maximumFractionDigits: 4,
}).format(Number(value || 0));
</script>

<template>
    <div class="space-y-8">
        <section class="rounded-2xl border border-surface-200 bg-surface-50 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-surface-800">Estado de LabsMobile</h3>
                    <p class="mt-1 text-sm text-surface-500">Credenciales, modo de operación y saldo disponible.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="provider.enabled ? 'border-success-200 bg-success-50 text-success-700' : 'border-danger-200 bg-danger-50 text-danger-700'">
                        {{ provider.enabled ? 'Proveedor habilitado' : 'Proveedor deshabilitado' }}
                    </span>
                    <span class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="provider.configured ? 'border-success-200 bg-success-50 text-success-700' : 'border-warning-200 bg-warning-50 text-warning-700'">
                        {{ provider.configured ? 'Credenciales configuradas' : 'Faltan credenciales' }}
                    </span>
                    <span class="rounded-lg border border-info-200 bg-info-50 px-3 py-1.5 text-xs font-semibold text-info-700">
                        {{ provider.test_mode ? 'Modo simulado' : 'Envíos reales' }}
                    </span>
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t border-surface-200 pt-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-surface-500">Saldo LabsMobile</p>
                    <p v-if="provider.balance" class="mt-1 text-2xl font-bold tabular-nums text-surface-800">{{ formatNumber(provider.balance.credits, 4) }} créditos</p>
                    <p v-else class="mt-1 text-sm text-surface-500">Consulta el saldo cuando necesites actualizarlo.</p>
                    <p v-if="provider.balance?.checked_at" class="mt-1 text-xs text-surface-400">Actualizado: {{ formatDateTime(provider.balance.checked_at) }}</p>
                </div>
                <Button type="button" variant="outline" :disabled="balanceForm.processing || !provider.configured" class="rounded-xl" @click="refreshBalance">
                    <i class="fa-solid fa-rotate mr-2"></i>{{ balanceForm.processing ? 'Consultando...' : 'Consultar saldo' }}
                </Button>
            </div>
        </section>

        <section>
            <div class="mb-4">
                <h3 class="text-lg font-bold text-surface-800">Resumen del periodo filtrado</h3>
                <p class="mt-1 text-sm text-surface-500">Los envíos simulados siempre registran cero créditos y cero coste.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-surface-200 bg-white p-5">
                    <p class="text-sm text-surface-500">Mensajes registrados</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-surface-800">{{ summary.total }}</p>
                    <p class="mt-1 text-xs text-surface-400">{{ summary.successful }} procesados correctamente</p>
                </div>
                <div class="rounded-2xl border border-surface-200 bg-white p-5">
                    <p class="text-sm text-surface-500">Entregados</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-success-700">{{ summary.delivered }}</p>
                    <p class="mt-1 text-xs text-surface-400">{{ summary.failed }} fallidos</p>
                </div>
                <div class="rounded-2xl border border-surface-200 bg-white p-5">
                    <p class="text-sm text-surface-500">Créditos consumidos</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-surface-800">{{ formatNumber(summary.credits_used, 4) }}</p>
                    <p class="mt-1 text-xs text-surface-400">Calculados por segmentos enviados</p>
                </div>
                <div class="rounded-2xl border border-surface-200 bg-white p-5">
                    <p class="text-sm text-surface-500">Coste estimado</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-surface-800">{{ formatCost(summary.estimated_cost, summary.currency) }}</p>
                    <p class="mt-1 text-xs text-surface-400">Según el coste por crédito configurado</p>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-surface-200 bg-white p-6">
                <div class="mb-5">
                    <h3 class="text-lg font-bold text-surface-800">Enviar mensaje individual</h3>
                    <p class="mt-1 text-sm text-surface-500">Selecciona un cliente y envía un SMS asociado a su perfil.</p>
                </div>
                <SmsComposer :clients="clients" />
            </div>

            <div class="rounded-2xl border border-surface-200 bg-white p-6">
                <div class="mb-5">
                    <h3 class="text-lg font-bold text-surface-800">Automatización de cobranza</h3>
                    <p class="mt-1 text-sm text-surface-500">Frecuencia, límites, plantilla y referencia de coste.</p>
                </div>

                <div class="space-y-5">
                    <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-surface-200 bg-surface-50 p-4">
                        <div>
                            <p class="font-semibold text-surface-800">Activar recordatorios automáticos</p>
                            <p class="mt-1 text-xs text-surface-500">También requiere LABSMOBILE_ENABLED=true.</p>
                        </div>
                        <input v-model="form.overdue_sms_enabled" type="checkbox" class="h-5 w-5 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                    </label>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="space-y-2">
                            <Label for="overdue_sms_send_time">Hora</Label>
                            <Input id="overdue_sms_send_time" v-model="form.overdue_sms_send_time" type="time" />
                        </div>
                        <div class="space-y-2">
                            <Label for="overdue_sms_interval_days">Periodicidad</Label>
                            <Input id="overdue_sms_interval_days" v-model.number="form.overdue_sms_interval_days" type="number" min="1" max="365" />
                        </div>
                        <div class="space-y-2">
                            <Label for="overdue_sms_messages_per_day">Mensajes por día</Label>
                            <Input id="overdue_sms_messages_per_day" v-model.number="form.overdue_sms_messages_per_day" type="number" min="1" max="5" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="overdue_sms_body">Plantilla del mensaje</Label>
                        <textarea id="overdue_sms_body" v-model="form.overdue_sms_body" rows="6" maxlength="1000" class="w-full rounded-xl border border-surface-200 bg-white px-3 py-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"></textarea>
                        <p class="text-xs text-surface-500">Variables: {client_first_name}, {client_name}, {amount_due}, {days_overdue}, {loan_count}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 border-t border-surface-100 pt-5 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="sms_cost_per_credit">Coste por crédito</Label>
                            <Input id="sms_cost_per_credit" v-model="form.sms_cost_per_credit" type="number" min="0" step="0.0001" />
                            <p class="text-xs text-surface-500">Usa el coste efectivo de tu paquete de LabsMobile.</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="sms_cost_currency">Moneda</Label>
                            <select id="sms_cost_currency" v-model="form.sms_cost_currency" class="h-11 w-full rounded-xl border border-surface-200 bg-white px-3 text-sm">
                                <option value="DOP">DOP</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-surface-200 bg-white">
            <div class="border-b border-surface-100 p-6">
                <h3 class="text-lg font-bold text-surface-800">Historial de mensajes</h3>
                <p class="mt-1 text-sm text-surface-500">Busca por cliente, teléfono, contenido, estado, préstamo, usuario o referencia del proveedor.</p>

                <form class="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-6" @submit.prevent="applyFilters">
                    <Input v-model="filterForm.sms_search" class="lg:col-span-2" placeholder="Buscar cualquier valor" />
                    <Input v-model="filterForm.sms_from" type="date" aria-label="Fecha inicial" />
                    <Input v-model="filterForm.sms_to" type="date" aria-label="Fecha final" />
                    <select v-model="filterForm.sms_status" class="h-10 rounded-xl border border-surface-200 bg-white px-3 text-sm">
                        <option value="">Todos los estados</option>
                        <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <select v-model="filterForm.sms_source" class="h-10 rounded-xl border border-surface-200 bg-white px-3 text-sm">
                        <option value="">Todos los orígenes</option>
                        <option value="manual">Manual</option>
                        <option value="overdue">Cobranza</option>
                    </select>
                    <div class="flex gap-2 lg:col-span-6 lg:justify-end">
                        <Button type="button" variant="outline" class="rounded-xl" @click="clearFilters">Limpiar</Button>
                        <Button type="submit" class="rounded-xl bg-primary-600 text-white hover:bg-primary-700">Aplicar filtros</Button>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <Table class="min-w-[1050px]">
                    <TableHeader class="bg-surface-50">
                        <TableRow>
                            <TableHead>Fecha</TableHead>
                            <TableHead>Cliente</TableHead>
                            <TableHead>Mensaje</TableHead>
                            <TableHead>Origen</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="text-right">Créditos</TableHead>
                            <TableHead class="text-right">Coste</TableHead>
                            <TableHead>Referencia</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in history.data" :key="item.id" class="align-top">
                            <TableCell class="whitespace-nowrap text-xs text-surface-500">{{ formatDateTime(item.sent_at || item.created_at) }}</TableCell>
                            <TableCell>
                                <p class="font-medium text-surface-800">{{ item.client?.first_name }} {{ item.client?.last_name }}</p>
                                <p class="text-xs text-surface-500">{{ item.phone }}<span v-if="item.loan"> · {{ item.loan.code }}</span></p>
                            </TableCell>
                            <TableCell class="max-w-sm">
                                <p class="line-clamp-3 whitespace-normal text-sm text-surface-700">{{ item.message }}</p>
                                <p v-if="item.sent_by" class="mt-1 text-xs text-surface-400">Por {{ item.sent_by.name }}</p>
                                <p v-if="item.error_message" class="mt-1 text-xs text-danger-600">{{ item.error_message }}</p>
                            </TableCell>
                            <TableCell class="text-sm text-surface-600">{{ item.source === 'manual' ? 'Manual' : 'Cobranza' }}</TableCell>
                            <TableCell>
                                <span class="inline-flex rounded-lg border px-2.5 py-1 text-xs font-semibold" :class="statusClasses[item.status] || 'border-surface-200 bg-surface-50 text-surface-600'">
                                    {{ statusLabels[item.status] || item.status }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right font-mono text-sm">{{ formatNumber(item.credits_used, 4) }}</TableCell>
                            <TableCell class="text-right text-sm font-semibold">{{ formatCost(item.estimated_cost, item.cost_currency) }}</TableCell>
                            <TableCell class="max-w-40 break-all font-mono text-xs text-surface-500">{{ item.provider_subid || '-' }}</TableCell>
                        </TableRow>
                        <TableRow v-if="history.data.length === 0">
                            <TableCell colspan="8" class="h-36 text-center text-surface-500">
                                No hay mensajes que coincidan con los filtros.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div class="border-t border-surface-100 p-5">
                <Pagination :links="history.links" />
            </div>
        </section>
    </div>
</template>
