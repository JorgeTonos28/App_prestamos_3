<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import Modal from '@/Components/Modal.vue';
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

const showManualModal = ref(false);
const showAutomationModal = ref(false);
const selectedDetail = ref(null);

const filterForm = reactive({
    sms_search: props.filters.sms_search || '',
    sms_from: props.filters.sms_from || '',
    sms_to: props.filters.sms_to || '',
    sms_status: props.filters.sms_status || '',
    sms_source: props.filters.sms_source || '',
});

const statusLabels = {
    pending: 'Pendiente',
    simulated: 'Simulado',
    accepted: 'Aceptado por LabsMobile',
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

const saveAutomation = () => {
    props.form.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            showAutomationModal.value = false;
        },
    });
};

const formatDateTime = (value) => value
    ? new Date(value).toLocaleString('es-DO', { dateStyle: 'short', timeStyle: 'short' })
    : '-';

const formatNumber = (value, maximumFractionDigits = 2) => new Intl.NumberFormat('es-DO', {
    maximumFractionDigits,
}).format(Number(value || 0));

const formatDop = (value) => new Intl.NumberFormat('es-DO', {
    style: 'currency',
    currency: 'DOP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value || 0));

const templateProfile = computed(() => {
    const message = props.form.overdue_sms_body || '';
    const unicode = /[^\u000A\u000D\u0020-\u007E£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¤¡ÄÖÑÜ§¿äöñüà€]/u.test(message);
    const characters = [...message].length;
    if (unicode) {
        return { unicode, characters, segments: characters <= 70 ? 1 : Math.ceil(characters / 67) };
    }
    const extended = (message.match(/[\^{}\[\]~|€\\]/gu) || []).length;
    const septets = characters + extended;
    return { unicode, characters, segments: septets <= 160 ? 1 : Math.ceil(septets / 153) };
});

const templateCredits = computed(() => props.provider.test_mode
    ? 0
    : templateProfile.value.segments * Number(props.provider.credit_rate || 0));
const templateCost = computed(() => templateCredits.value * Number(props.form.sms_cost_per_credit || 0));

const ackDescription = (item) => item.delivery_diagnostic
    || item.delivery_details?.diagnostic
    || item.error_message
    || (item.status === 'accepted' ? 'LabsMobile aceptó el mensaje; todavía no existe confirmación final del dispositivo.' : null);
</script>

<template>
    <div class="space-y-8">
        <section class="rounded-2xl border border-surface-200 bg-surface-50 p-5">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-surface-800">Estado de LabsMobile</h3>
                    <p class="mt-1 text-sm text-surface-500">El saldo se consulta automáticamente al abrir esta pestaña y después de cada envío real.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="provider.enabled ? 'border-success-200 bg-success-50 text-success-700' : 'border-danger-200 bg-danger-50 text-danger-700'">
                            {{ provider.enabled ? 'Proveedor habilitado' : 'Proveedor deshabilitado' }}
                        </span>
                        <span class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="provider.configured ? 'border-success-200 bg-success-50 text-success-700' : 'border-warning-200 bg-warning-50 text-warning-700'">
                            {{ provider.configured ? 'Credenciales configuradas' : 'Faltan credenciales' }}
                        </span>
                        <span class="rounded-lg border border-info-200 bg-info-50 px-3 py-1.5 text-xs font-semibold text-info-700">
                            {{ provider.test_mode ? 'Modo simulado' : 'Envíos reales' }}
                        </span>
                        <span class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="provider.ack_configured && provider.ack_https ? 'border-success-200 bg-success-50 text-success-700' : 'border-warning-200 bg-warning-50 text-warning-700'">
                            {{ provider.ack_configured && provider.ack_https ? 'ACK de entrega activo' : 'ACK de entrega pendiente' }}
                        </span>
                    </div>
                </div>

                <div class="grid min-w-[290px] grid-cols-2 gap-3">
                    <div class="rounded-xl border border-surface-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-surface-500">Saldo</p>
                        <p v-if="provider.balance" class="mt-1 text-xl font-bold tabular-nums text-surface-800">{{ formatNumber(provider.balance.credits, 4) }}</p>
                        <p v-else class="mt-1 text-sm font-semibold text-surface-500">No disponible</p>
                        <p class="text-xs text-surface-400">créditos</p>
                    </div>
                    <div class="rounded-xl border border-surface-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-surface-500">Tarifa RD</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-surface-800">{{ provider.credit_rate > 0 ? formatNumber(provider.credit_rate, 4) : '-' }}</p>
                        <p class="text-xs text-surface-400">créditos / SMS</p>
                    </div>
                </div>
            </div>

            <p v-if="provider.balance?.checked_at" class="mt-3 text-xs text-surface-400">Saldo actualizado: {{ formatDateTime(provider.balance.checked_at) }}</p>
            <p v-if="provider.ack_configured && !provider.ack_https" class="mt-3 text-xs font-medium text-warning-700">La URL ACK está configurada, pero debe ser HTTPS en producción.</p>
        </section>

        <section class="flex flex-wrap gap-3">
            <Button type="button" class="rounded-xl bg-primary-600 text-white hover:bg-primary-700" @click="showManualModal = true">
                <i class="fa-solid fa-paper-plane mr-2"></i>Enviar mensaje individual
            </Button>
            <Button type="button" variant="outline" class="rounded-xl" @click="showAutomationModal = true">
                <i class="fa-solid fa-clock-rotate-left mr-2"></i>Configurar recordatorios automáticos
            </Button>
        </section>

        <section class="rounded-2xl border border-surface-200 bg-white p-5">
            <div class="mb-4">
                <h3 class="text-lg font-bold text-surface-800">Filtrar historial</h3>
                <p class="mt-1 text-sm text-surface-500">El resumen y la tabla de abajo usan exactamente estos filtros.</p>
            </div>
            <form class="grid grid-cols-1 gap-3 lg:grid-cols-6" @submit.prevent="applyFilters">
                <Input v-model="filterForm.sms_search" class="lg:col-span-2" placeholder="Cliente, teléfono, mensaje, referencia..." />
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
        </section>

        <section>
            <div class="mb-4">
                <h3 class="text-lg font-bold text-surface-800">Resumen del período filtrado</h3>
                <p class="mt-1 text-sm text-surface-500">“SMS” representa segmentos facturables; un texto largo puede equivaler a varios SMS.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-2xl border border-surface-200 bg-white p-5">
                    <p class="text-sm text-surface-500">Envíos registrados</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-surface-800">{{ summary.total }}</p>
                    <p class="mt-1 text-xs text-surface-400">{{ summary.successful }} procesados</p>
                </div>
                <div class="rounded-2xl border border-surface-200 bg-white p-5">
                    <p class="text-sm text-surface-500">Cantidad de SMS</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-primary-700">{{ summary.sms_count }}</p>
                    <p class="mt-1 text-xs text-surface-400">segmentos del período</p>
                </div>
                <div class="rounded-2xl border border-surface-200 bg-white p-5">
                    <p class="text-sm text-surface-500">Entregados</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-success-700">{{ summary.delivered }}</p>
                    <p class="mt-1 text-xs text-surface-400">{{ summary.failed }} fallidos</p>
                </div>
                <div class="rounded-2xl border border-surface-200 bg-white p-5">
                    <p class="text-sm text-surface-500">Créditos consumidos</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-surface-800">{{ formatNumber(summary.credits_used, 4) }}</p>
                    <p class="mt-1 text-xs text-surface-400">tarifa RD de LabsMobile</p>
                </div>
                <div class="rounded-2xl border border-surface-200 bg-white p-5">
                    <p class="text-sm text-surface-500">Costo estimado</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-surface-800">{{ formatDop(summary.estimated_cost) }}</p>
                    <p class="mt-1 text-xs text-surface-400">en pesos dominicanos</p>
                </div>
            </div>
            <p v-if="provider.cost_per_credit_dop <= 0" class="mt-3 text-xs text-warning-700">Para calcular RD$, configura el costo efectivo de 1 crédito de tu paquete LabsMobile.</p>
        </section>

        <section class="rounded-2xl border border-surface-200 bg-white">
            <div class="border-b border-surface-100 p-6">
                <h3 class="text-lg font-bold text-surface-800">Historial de mensajes</h3>
                <p class="mt-1 text-sm text-surface-500">El estado “Aceptado” no equivale a entrega; abre Detalles para ver el ACK del operador/dispositivo.</p>
            </div>

            <div class="overflow-x-auto">
                <Table class="min-w-[1180px]">
                    <TableHeader class="bg-surface-50">
                        <TableRow>
                            <TableHead>Fecha</TableHead>
                            <TableHead>Cliente</TableHead>
                            <TableHead>Mensaje</TableHead>
                            <TableHead>Origen</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead class="text-right">SMS</TableHead>
                            <TableHead class="text-right">Créditos</TableHead>
                            <TableHead class="text-right">Costo RD$</TableHead>
                            <TableHead class="text-right">Diagnóstico</TableHead>
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
                            </TableCell>
                            <TableCell class="text-sm text-surface-600">{{ item.source === 'manual' ? 'Manual' : 'Cobranza' }}</TableCell>
                            <TableCell>
                                <span class="inline-flex rounded-lg border px-2.5 py-1 text-xs font-semibold" :class="statusClasses[item.status] || 'border-surface-200 bg-surface-50 text-surface-600'">
                                    {{ statusLabels[item.status] || item.status }}
                                </span>
                                <p v-if="ackDescription(item)" class="mt-1 max-w-52 text-xs" :class="item.status === 'failed' ? 'text-danger-600' : 'text-surface-500'">{{ ackDescription(item) }}</p>
                            </TableCell>
                            <TableCell class="text-right font-mono text-sm font-semibold">{{ item.sms_count || item.segment_count || 1 }}</TableCell>
                            <TableCell class="text-right font-mono text-sm">{{ formatNumber(item.display_credits ?? item.credits_used, 4) }}</TableCell>
                            <TableCell class="text-right text-sm font-semibold">{{ formatDop(item.display_cost_dop ?? item.estimated_cost) }}</TableCell>
                            <TableCell class="text-right">
                                <Button type="button" variant="outline" class="h-8 rounded-lg px-3 text-xs" @click="selectedDetail = item">Detalles</Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="history.data.length === 0">
                            <TableCell colspan="9" class="h-36 text-center text-surface-500">No hay mensajes que coincidan con los filtros.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div class="border-t border-surface-100 p-5">
                <Pagination :links="history.links" />
            </div>
        </section>

        <Modal :show="showManualModal" max-width="lg" @close="showManualModal = false">
            <div class="border-b border-surface-100 px-6 py-5">
                <h3 class="text-lg font-bold text-surface-800">Enviar mensaje individual</h3>
                <p class="mt-1 text-sm text-surface-500">PRESTO calcula el número de SMS, créditos y costo estimado antes de enviar.</p>
            </div>
            <div class="p-6">
                <SmsComposer
                    :clients="clients"
                    :test-mode="Boolean(provider.test_mode)"
                    :credit-rate="Number(provider.credit_rate || 0)"
                    :cost-per-credit-dop="Number(provider.cost_per_credit_dop || 0)"
                    @sent="showManualModal = false"
                />
            </div>
        </Modal>

        <Modal :show="showAutomationModal" max-width="2xl" @close="showAutomationModal = false">
            <div class="border-b border-surface-100 px-6 py-5">
                <h3 class="text-lg font-bold text-surface-800">Recordatorios automáticos de cobranza</h3>
                <p class="mt-1 text-sm text-surface-500">Configura cuándo se envían y controla el consumo esperado.</p>
            </div>
            <div class="max-h-[75vh] space-y-5 overflow-y-auto p-6">
                <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-surface-200 bg-surface-50 p-4">
                    <div>
                        <p class="font-semibold text-surface-800">Activar recordatorios automáticos</p>
                        <p class="mt-1 text-xs text-surface-500">También requiere LABSMOBILE_ENABLED=true en producción.</p>
                    </div>
                    <input v-model="form.overdue_sms_enabled" type="checkbox" class="h-5 w-5 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                </label>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="overdue_sms_send_time">Hora</Label>
                        <Input id="overdue_sms_send_time" v-model="form.overdue_sms_send_time" type="time" />
                    </div>
                    <div class="space-y-2">
                        <Label for="overdue_sms_interval_days">Periodicidad (días)</Label>
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

                <div class="rounded-xl border border-primary-100 bg-primary-50/50 p-4">
                    <p class="font-semibold text-primary-900">Consumo de la plantilla actual</p>
                    <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2 text-sm text-primary-800">
                        <span>{{ templateProfile.segments }} SMS</span>
                        <span>{{ templateProfile.characters }} caracteres</span>
                        <span>{{ templateProfile.unicode ? 'Unicode' : 'GSM' }}</span>
                        <span v-if="!provider.test_mode && provider.credit_rate > 0">≈ {{ formatNumber(templateCredits, 4) }} créditos</span>
                        <span v-if="!provider.test_mode && provider.credit_rate > 0 && Number(form.sms_cost_per_credit || 0) > 0">≈ {{ formatDop(templateCost) }}</span>
                    </div>
                    <p class="mt-2 text-xs text-primary-700">Es una referencia: la longitud final puede variar al sustituir nombre, monto y días de atraso.</p>
                </div>

                <div class="space-y-2 border-t border-surface-100 pt-5">
                    <Label for="sms_cost_per_credit">Costo efectivo de 1 crédito LabsMobile (RD$)</Label>
                    <Input id="sms_cost_per_credit" v-model="form.sms_cost_per_credit" type="number" min="0" step="0.0001" class="max-w-xs" />
                    <p class="text-xs text-surface-500">LabsMobile informa cuántos créditos consume un SMS a RD; este valor convierte esos créditos al costo real de tu paquete en pesos dominicanos.</p>
                </div>

                <div class="flex justify-end gap-2 border-t border-surface-100 pt-5">
                    <Button type="button" variant="outline" class="rounded-xl" @click="showAutomationModal = false">Cancelar</Button>
                    <Button type="button" :disabled="form.processing" class="rounded-xl bg-primary-600 text-white hover:bg-primary-700" @click="saveAutomation">
                        {{ form.processing ? 'Guardando...' : 'Guardar configuración SMS' }}
                    </Button>
                </div>
            </div>
        </Modal>

        <Modal :show="Boolean(selectedDetail)" max-width="lg" @close="selectedDetail = null">
            <div v-if="selectedDetail" class="space-y-5 p-6">
                <div>
                    <h3 class="text-lg font-bold text-surface-800">Diagnóstico del envío</h3>
                    <p class="mt-1 text-sm text-surface-500">Información local, respuesta de LabsMobile y última confirmación ACK.</p>
                </div>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-surface-50 p-3"><p class="text-xs text-surface-500">Referencia LabsMobile</p><p class="mt-1 break-all font-mono">{{ selectedDetail.provider_subid || '-' }}</p></div>
                    <div class="rounded-xl bg-surface-50 p-3"><p class="text-xs text-surface-500">Código API</p><p class="mt-1 font-mono">{{ selectedDetail.api_code || '-' }}</p></div>
                    <div class="rounded-xl bg-surface-50 p-3"><p class="text-xs text-surface-500">ACK level</p><p class="mt-1 font-mono">{{ selectedDetail.delivery_details?.acklevel || '-' }}</p></div>
                    <div class="rounded-xl bg-surface-50 p-3"><p class="text-xs text-surface-500">ACK estado</p><p class="mt-1 font-mono">{{ selectedDetail.delivery_details?.desc || '-' }}</p></div>
                    <div class="rounded-xl bg-surface-50 p-3"><p class="text-xs text-surface-500">Aceptado/enviado</p><p class="mt-1">{{ formatDateTime(selectedDetail.sent_at) }}</p></div>
                    <div class="rounded-xl bg-surface-50 p-3"><p class="text-xs text-surface-500">Entregado</p><p class="mt-1">{{ formatDateTime(selectedDetail.delivered_at) }}</p></div>
                </div>
                <div class="rounded-xl border p-4" :class="selectedDetail.status === 'failed' ? 'border-danger-200 bg-danger-50' : 'border-surface-200 bg-surface-50'">
                    <p class="text-xs font-semibold uppercase tracking-wide text-surface-500">Diagnóstico</p>
                    <p class="mt-1 text-sm" :class="selectedDetail.status === 'failed' ? 'text-danger-700' : 'text-surface-700'">{{ ackDescription(selectedDetail) || 'Todavía no hay confirmación ACK.' }}</p>
                </div>
                <details class="rounded-xl border border-surface-200 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-surface-700">Ver datos técnicos</summary>
                    <pre class="mt-3 max-h-56 overflow-auto whitespace-pre-wrap break-all text-xs text-surface-600">{{ JSON.stringify({ provider_response: selectedDetail.provider_response, delivery_details: selectedDetail.delivery_details }, null, 2) }}</pre>
                </details>
                <div class="flex justify-end"><Button type="button" variant="outline" class="rounded-xl" @click="selectedDetail = null">Cerrar</Button></div>
            </div>
        </Modal>
    </div>
</template>
