<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

const props = defineProps({
    applications: { type: Object, required: true },
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'pending_review');
const riskLevel = ref(props.filters.risk_level || 'all');
let timer;

const applyFilters = () => router.get(route('loan-applications.index'), {
    search: search.value,
    status: status.value,
    risk_level: riskLevel.value,
}, { preserveState: true, preserveScroll: true, replace: true });

watch([status, riskLevel], applyFilters);
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 350);
});

const formatCurrency = (value) => value == null ? '-' : new Intl.NumberFormat('es-DO', {
    style: 'currency', currency: 'DOP', maximumFractionDigits: 0,
}).format(Number(value));
const formatDate = (value) => value ? new Date(value).toLocaleString('es-DO', { dateStyle: 'medium', timeStyle: 'short' }) : '-';

const statusLabels = {
    collecting_data: 'Recolectando datos',
    collecting_documents: 'Recolectando documentos',
    ready_for_analysis: 'Lista para análisis',
    analyzing: 'Analizando',
    pending_review: 'Revisión humana',
    approved: 'Aprobada',
    rejected: 'Rechazada',
    error: 'Requiere atención',
};

const statusClasses = {
    pending_review: 'border-warning-200 bg-warning-50 text-warning-800',
    approved: 'border-success-200 bg-success-50 text-success-700',
    rejected: 'border-danger-200 bg-danger-50 text-danger-700',
    error: 'border-danger-200 bg-danger-50 text-danger-700',
    analyzing: 'border-info-200 bg-info-50 text-info-700',
};

const riskClasses = {
    low: 'bg-success-100 text-success-800',
    medium: 'bg-warning-100 text-warning-800',
    high: 'bg-danger-100 text-danger-800',
};
</script>

<template>
    <Head title="Solicitudes WhatsApp" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-2xl font-bold leading-tight text-surface-800">Solicitudes WhatsApp</h2>
                <p class="text-sm text-surface-500">Expedientes recolectados por el agente y pendientes de decisión humana.</p>
            </div>
        </template>

        <div class="space-y-6 py-4">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div v-for="card in [
                    { label: 'Por revisar', value: summary.pending_review, icon: 'fa-user-check', iconClass: 'bg-warning-50 text-warning-600' },
                    { label: 'En proceso', value: summary.in_progress, icon: 'fa-spinner', iconClass: 'bg-info-50 text-info-600' },
                    { label: 'Aprobadas', value: summary.approved, icon: 'fa-circle-check', iconClass: 'bg-success-50 text-success-600' },
                    { label: 'Riesgo alto', value: summary.high_risk, icon: 'fa-triangle-exclamation', iconClass: 'bg-danger-50 text-danger-600' },
                ]" :key="card.label" class="rounded-2xl border border-surface-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-surface-500">{{ card.label }}</p>
                            <p class="mt-1 text-3xl font-bold text-surface-900">{{ card.value }}</p>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl" :class="card.iconClass">
                            <i class="fa-solid" :class="card.icon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <section class="rounded-2xl border border-surface-200 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div class="relative md:col-span-2">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-4 text-surface-400"></i>
                        <Input v-model="search" class="pl-11" placeholder="Buscar teléfono, referencia o cliente..." />
                    </div>
                    <select v-model="status" class="h-12 rounded-xl border border-surface-300 bg-white px-4 text-sm">
                        <option value="all">Todos los estados</option>
                        <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <select v-model="riskLevel" class="h-12 rounded-xl border border-surface-300 bg-white px-4 text-sm">
                        <option value="all">Todos los riesgos</option>
                        <option value="low">Riesgo bajo</option>
                        <option value="medium">Riesgo medio</option>
                        <option value="high">Riesgo alto</option>
                    </select>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="border-b border-surface-200 bg-surface-50 text-xs uppercase tracking-wide text-surface-500">
                            <tr>
                                <th class="px-6 py-4">Solicitud</th>
                                <th class="px-4 py-4">Solicitante</th>
                                <th class="px-4 py-4">Monto</th>
                                <th class="px-4 py-4">Estado</th>
                                <th class="px-4 py-4">Riesgo</th>
                                <th class="px-4 py-4">Documentos</th>
                                <th class="px-6 py-4 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100">
                            <tr v-for="application in applications.data" :key="application.id" class="hover:bg-surface-50/70">
                                <td class="px-6 py-4">
                                    <p class="font-mono font-semibold text-primary-700">{{ application.reference }}</p>
                                    <p class="mt-1 text-xs text-surface-400">{{ formatDate(application.submitted_at || application.created_at) }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-surface-800">{{ application.applicant_name }}</p>
                                    <p class="text-xs text-surface-500">+{{ application.whatsapp_phone }}</p>
                                </td>
                                <td class="px-4 py-4 font-semibold text-surface-800">{{ formatCurrency(application.requested_amount) }}</td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-lg border px-2.5 py-1 text-xs font-semibold" :class="statusClasses[application.status] || 'border-surface-200 bg-surface-50 text-surface-700'">
                                        {{ statusLabels[application.status] || application.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span v-if="application.risk_level" class="inline-flex rounded-lg px-2.5 py-1 text-xs font-bold uppercase" :class="riskClasses[application.risk_level]">
                                        {{ application.risk_level }} · {{ Number(application.risk_score || 0).toFixed(0) }}
                                    </span>
                                    <span v-else class="text-xs text-surface-400">Pendiente</span>
                                </td>
                                <td class="px-4 py-4 text-surface-600"><i class="fa-regular fa-folder-open mr-2 text-surface-400"></i>{{ application.documents_count }}</td>
                                <td class="px-6 py-4 text-right">
                                    <Link :href="route('loan-applications.show', application.id)">
                                        <Button size="sm" variant="outline" class="rounded-lg">Revisar <i class="fa-solid fa-arrow-right ml-2 text-xs"></i></Button>
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="applications.data.length === 0">
                                <td colspan="7" class="px-6 py-16 text-center text-surface-400">
                                    <i class="fa-regular fa-folder-open mb-3 text-3xl"></i>
                                    <p>No hay solicitudes con estos filtros.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-surface-100 p-4">
                    <Pagination :links="applications.links" />
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
