<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    application: { type: Object, required: true },
    agentStatus: { type: Object, required: true },
});

const showConversation = ref(false);
const decisionForm = useForm({ decision: '', review_notes: '', create_client: true });
const latestAssessment = computed(() => props.application.risk_assessments?.[0] || null);

const statusLabels = {
    collecting_data: 'Recolectando datos', collecting_documents: 'Recolectando documentos',
    ready_for_analysis: 'Lista para análisis', analyzing: 'Analizando', pending_review: 'Revisión humana',
    approved: 'Aprobada', rejected: 'Rechazada', error: 'Requiere atención', cancelled: 'Cancelada', expired: 'Vencida',
};
const riskLabels = { low: 'Bajo', medium: 'Medio', high: 'Alto' };
const riskClasses = {
    low: 'border-success-200 bg-success-50 text-success-800',
    medium: 'border-warning-200 bg-warning-50 text-warning-800',
    high: 'border-danger-200 bg-danger-50 text-danger-800',
};
const documentClasses = {
    valid: 'bg-success-100 text-success-800', invalid: 'bg-danger-100 text-danger-800',
    pending_manual_review: 'bg-warning-100 text-warning-800', pending_validation: 'bg-info-100 text-info-800',
    quarantined: 'bg-danger-100 text-danger-800',
};
const documentLabels = {
    valid: 'Válido', invalid: 'Rechazado', pending_manual_review: 'Revisión manual',
    pending_validation: 'Validando', quarantined: 'Bloqueado',
};
const fieldLabels = {
    full_name: 'Nombre completo', document_type: 'Tipo de documento', national_id: 'Identificación',
    birth_date: 'Fecha de nacimiento', email: 'Correo', address: 'Dirección', monthly_income: 'Ingreso mensual',
    monthly_expenses: 'Gastos mensuales', monthly_debt_payments: 'Pagos de otras deudas',
    employment_status: 'Situación laboral', employer_name: 'Empleador / actividad',
    employment_tenure_months: 'Antigüedad (meses)', personal_reference_name: 'Referencia personal',
    personal_reference_phone: 'Teléfono de referencia', loan_type: 'Tipo de préstamo',
    requested_amount: 'Monto solicitado', loan_purpose: 'Destino', term_count: 'Cantidad de cuotas',
    payment_frequency: 'Frecuencia', preferred_installment: 'Cuota máxima declarada',
};

const moneyFields = new Set(['monthly_income', 'monthly_expenses', 'monthly_debt_payments', 'requested_amount', 'preferred_installment']);
const formatCurrency = (value) => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(Number(value || 0));
const formatDate = (value) => value ? new Date(value).toLocaleString('es-DO', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
const displayValue = (key, value) => moneyFields.has(key) ? formatCurrency(value) : (value ?? '-');
const bytes = (value) => value >= 1048576 ? `${(value / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(value / 1024))} KB`;

const decide = (decision) => {
    if (decision === 'rejected' && !decisionForm.review_notes.trim()) {
        window.alert('Escribe la razón del rechazo en las notas de decisión.');
        return;
    }
    if (!window.confirm(`¿Confirmas que deseas ${decision === 'approved' ? 'aprobar' : 'rechazar'} esta solicitud?`)) return;
    decisionForm.decision = decision;
    if (decision !== 'approved') decisionForm.create_client = false;
    decisionForm.post(route('loan-applications.decision', props.application.id), { preserveScroll: true });
};

const reviewDocument = (document, status) => {
    const notes = status === 'invalid' ? window.prompt('Razón del rechazo documental:') : 'Validado manualmente por el administrador.';
    if (status === 'invalid' && !notes) return;
    router.post(route('applicant-documents.review', document.id), { status, notes }, { preserveScroll: true });
};

const reanalyze = () => router.post(route('loan-applications.reanalyze', props.application.id), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="application.reference" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('loan-applications.index')">
                        <Button variant="ghost" size="icon" class="rounded-full"><i class="fa-solid fa-arrow-left"></i></Button>
                    </Link>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-mono text-2xl font-bold text-surface-800">{{ application.reference }}</h2>
                            <span class="rounded-lg border border-surface-200 bg-surface-50 px-2.5 py-1 text-xs font-semibold text-surface-700">{{ statusLabels[application.status] || application.status }}</span>
                        </div>
                        <p class="text-sm text-surface-500">{{ application.applicant_data.full_name || application.whatsapp_profile_name || 'Solicitante sin identificar' }} · +{{ application.whatsapp_phone }}</p>
                    </div>
                </div>
                <Link v-if="application.client" :href="route('clients.show', application.client.id)">
                    <Button variant="outline" class="rounded-xl"><i class="fa-solid fa-user mr-2"></i>Ver cliente {{ application.client.client_code }}</Button>
                </Link>
            </div>
        </template>

        <div class="space-y-6 py-4">
            <section v-if="latestAssessment" class="overflow-hidden rounded-2xl border bg-white shadow-sm" :class="riskClasses[latestAssessment.level] || 'border-surface-200'">
                <div class="grid gap-6 p-6 lg:grid-cols-[220px_1fr]">
                    <div class="flex flex-col items-center justify-center rounded-2xl bg-white/70 p-5 text-center">
                        <p class="text-xs font-bold uppercase tracking-[0.18em]">Riesgo {{ riskLabels[latestAssessment.level] }}</p>
                        <p class="mt-2 text-6xl font-black tabular-nums">{{ Number(latestAssessment.score).toFixed(0) }}</p>
                        <p class="text-sm font-semibold">de 100</p>
                        <p class="mt-3 rounded-full bg-white px-3 py-1 text-xs font-bold">Versión {{ latestAssessment.version }}</p>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide opacity-70">Resumen ejecutivo</p>
                                <h3 class="mt-2 text-xl font-bold leading-relaxed">{{ latestAssessment.summary }}</h3>
                            </div>
                            <Button v-if="['pending_review', 'error'].includes(application.status)" variant="outline" size="sm" class="rounded-lg bg-white" @click="reanalyze">
                                <i class="fa-solid fa-rotate mr-2"></i>Reanalizar
                            </Button>
                        </div>
                        <p class="mt-4 whitespace-pre-line text-sm leading-7 opacity-90">{{ latestAssessment.report }}</p>
                        <p class="mt-4 text-xs opacity-60">Generado {{ formatDate(latestAssessment.generated_at) }} con {{ latestAssessment.model || 'reglas locales' }}. No constituye aprobación automática.</p>
                    </div>
                </div>
            </section>

            <section v-else class="rounded-2xl border border-info-200 bg-info-50 p-6 text-info-800">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-spinner text-xl"></i>
                    <div><p class="font-bold">Análisis todavía no disponible</p><p class="text-sm">Se generará cuando los datos y documentos requeridos estén completos.</p></div>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-3">
                <section class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm xl:col-span-2">
                    <h3 class="text-lg font-bold text-surface-800">Factores del análisis</h3>
                    <div v-if="latestAssessment" class="mt-5 grid gap-5 lg:grid-cols-2">
                        <div>
                            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-danger-600">Factores y alertas</p>
                            <div class="space-y-3">
                                <div v-for="factor in latestAssessment.factors" :key="factor.code" class="rounded-xl border border-surface-200 p-4">
                                    <div class="flex justify-between gap-3"><p class="font-semibold text-surface-800">{{ factor.label }}</p><span class="font-mono text-sm font-bold text-danger-600">+{{ factor.points }}</span></div>
                                    <p class="mt-1 text-sm text-surface-500">{{ factor.evidence }}</p>
                                </div>
                                <p v-if="latestAssessment.factors.length === 0" class="text-sm text-surface-400">Sin factores adversos automáticos.</p>
                            </div>
                        </div>
                        <div class="space-y-5">
                            <div>
                                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-danger-600">Banderas rojas</p>
                                <ul class="space-y-2 text-sm text-surface-700"><li v-for="flag in latestAssessment.red_flags" :key="flag" class="flex gap-2"><i class="fa-solid fa-triangle-exclamation mt-1 text-danger-500"></i><span>{{ flag }}</span></li></ul>
                                <p v-if="latestAssessment.red_flags.length === 0" class="text-sm text-surface-400">Ninguna alerta crítica automática.</p>
                            </div>
                            <div>
                                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-success-600">Mitigantes</p>
                                <ul class="space-y-2 text-sm text-surface-700"><li v-for="item in latestAssessment.mitigants" :key="item" class="flex gap-2"><i class="fa-solid fa-circle-check mt-1 text-success-500"></i><span>{{ item }}</span></li></ul>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-surface-800">Control del expediente</h3>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-surface-500">Consentimiento</dt><dd class="font-semibold text-surface-800">{{ application.consent_at ? formatDate(application.consent_at) : 'No registrado' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-surface-500">Enviado</dt><dd class="font-semibold text-surface-800">{{ formatDate(application.submitted_at) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-surface-500">Decidido</dt><dd class="font-semibold text-surface-800">{{ formatDate(application.reviewed_at) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-surface-500">Notificado</dt><dd class="font-semibold text-surface-800">{{ application.decision_notified_at ? 'Sí' : 'No' }}</dd></div>
                    </dl>
                    <div v-if="!agentStatus.whatsapp_ready || !agentStatus.openai_ready" class="mt-5 rounded-xl border border-warning-200 bg-warning-50 p-3 text-xs text-warning-800">
                        La integración está incompleta. Revisa Configuración → WhatsApp + IA.
                    </div>
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-surface-800">Datos del solicitante</h3>
                    <dl class="mt-5 grid gap-x-5 gap-y-4 sm:grid-cols-2">
                        <div v-for="(value, key) in application.applicant_data" :key="key" class="border-b border-surface-100 pb-3">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-surface-400">{{ fieldLabels[key] || key }}</dt>
                            <dd class="mt-1 break-words font-medium text-surface-800">{{ displayValue(key, value) }}</dd>
                        </div>
                    </dl>
                </section>
                <section class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-surface-800">Préstamo solicitado</h3>
                    <dl class="mt-5 grid gap-x-5 gap-y-4 sm:grid-cols-2">
                        <div v-for="(value, key) in application.loan_request" :key="key" class="border-b border-surface-100 pb-3">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-surface-400">{{ fieldLabels[key] || key }}</dt>
                            <dd class="mt-1 break-words font-medium text-surface-800">{{ displayValue(key, value) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>

            <section class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-sm">
                <div class="border-b border-surface-100 p-6"><h3 class="text-lg font-bold text-surface-800">Banco de documentos</h3><p class="text-sm text-surface-500">Descarga autenticada, estado de seguridad y validación por archivo.</p></div>
                <div class="divide-y divide-surface-100">
                    <div v-for="document in application.documents" :key="document.id" class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex min-w-0 items-start gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-surface-100 text-surface-600"><i class="fa-solid" :class="document.mime_type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file-image'"></i></div>
                            <div class="min-w-0">
                                <p class="font-semibold text-surface-800">{{ document.label }}</p>
                                <p class="truncate text-xs text-surface-500">{{ document.original_name }} · {{ bytes(document.size_bytes) }}</p>
                                <p v-if="document.rejection_reason" class="mt-1 text-xs text-danger-600">{{ document.rejection_reason }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-lg px-2.5 py-1 text-xs font-bold" :class="documentClasses[document.status] || 'bg-surface-100 text-surface-700'">{{ documentLabels[document.status] || document.status }}</span>
                            <span class="rounded-lg border border-surface-200 px-2.5 py-1 text-xs text-surface-600">Antimalware: {{ document.malware_scan_status }}</span>
                            <a v-if="document.status !== 'quarantined'" :href="document.download_url" class="inline-flex h-9 items-center rounded-lg border border-surface-200 px-3 text-xs font-semibold text-surface-700 hover:bg-surface-50"><i class="fa-solid fa-download mr-2"></i>Descargar</a>
                            <Button v-if="document.status !== 'valid' && document.status !== 'quarantined'" size="sm" class="rounded-lg bg-success-600 text-white hover:bg-success-700" @click="reviewDocument(document, 'valid')">Validar</Button>
                            <Button v-if="document.status !== 'invalid'" size="sm" variant="outline" class="rounded-lg border-danger-200 text-danger-700" @click="reviewDocument(document, 'invalid')">Rechazar</Button>
                        </div>
                    </div>
                    <p v-if="application.documents.length === 0" class="p-10 text-center text-surface-400">No se han recibido documentos.</p>
                </div>
            </section>

            <section v-if="application.status === 'pending_review'" class="rounded-2xl border border-primary-200 bg-primary-50/40 p-6 shadow-sm">
                <h3 class="text-lg font-bold text-surface-800">Decisión del administrador</h3>
                <p class="mt-1 text-sm text-surface-600">La decisión queda auditada y se intenta notificar inmediatamente por WhatsApp.</p>
                <textarea v-model="decisionForm.review_notes" rows="4" class="mt-5 w-full rounded-xl border border-surface-300 bg-white p-4 text-sm" placeholder="Notas internas y fundamento de la decisión. Son obligatorias al rechazar." />
                <p v-if="decisionForm.errors.review_notes" class="mt-1 text-xs text-danger-600">{{ decisionForm.errors.review_notes }}</p>
                <label class="mt-4 flex items-start gap-3 text-sm text-surface-700">
                    <input v-model="decisionForm.create_client" type="checkbox" class="mt-1 rounded border-surface-300 text-primary-600" />
                    Crear o vincular el cliente y transferir sus documentos si se aprueba.
                </label>
                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <Button type="button" variant="outline" class="rounded-xl border-danger-300 text-danger-700" :disabled="decisionForm.processing" @click="decide('rejected')"><i class="fa-solid fa-xmark mr-2"></i>Rechazar solicitud</Button>
                    <Button type="button" class="rounded-xl bg-success-600 px-6 text-white hover:bg-success-700" :disabled="decisionForm.processing" @click="decide('approved')"><i class="fa-solid fa-check mr-2"></i>Aprobar solicitud</Button>
                </div>
            </section>

            <section class="rounded-2xl border border-surface-200 bg-white shadow-sm">
                <button type="button" class="flex w-full items-center justify-between p-6 text-left" @click="showConversation = !showConversation">
                    <span><span class="block text-lg font-bold text-surface-800">Conversación y auditoría</span><span class="block text-sm text-surface-500">Datos sensibles; visible solo para usuarios autenticados.</span></span>
                    <i class="fa-solid fa-chevron-down transition-transform" :class="showConversation ? 'rotate-180' : ''"></i>
                </button>
                <div v-if="showConversation" class="grid gap-6 border-t border-surface-100 p-6 lg:grid-cols-2">
                    <div class="max-h-[520px] space-y-3 overflow-y-auto rounded-xl bg-surface-50 p-4">
                        <div v-for="message in application.messages" :key="message.id" class="flex" :class="message.direction === 'outbound' ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm shadow-sm" :class="message.direction === 'outbound' ? 'bg-primary-600 text-white' : 'border border-surface-200 bg-white text-surface-800'">
                                <p class="whitespace-pre-line">{{ message.body || `[${message.type}]` }}</p><p class="mt-1 text-[10px] opacity-60">{{ formatDate(message.created_at) }} · {{ message.status }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="max-h-[520px] space-y-3 overflow-y-auto">
                        <div v-for="event in application.events" :key="event.id" class="border-l-2 border-primary-200 pl-4">
                            <p class="text-sm font-semibold text-surface-800">{{ event.event }}</p>
                            <p class="text-xs text-surface-500">{{ event.user_name || event.actor_type }} · {{ formatDate(event.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
