<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { computed, ref } from 'vue';
import WarningModal from '@/Components/WarningModal.vue';
import LoanCancellationModal from '@/Components/LoanCancellationModal.vue';
import PaymentModal from '@/Components/PaymentModal.vue';
import LegalPayoffModal from '@/Components/LegalPayoffModal.vue';
import AddLegalFeeModal from '@/Components/AddLegalFeeModal.vue';

const page = usePage();

const props = defineProps({
    loan: Object,
    projected_schedule: Array, // Passed from backend
    payoff_summary: Object,
    display_balance_total: Number,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    // Handle YYYY-MM-DD, YYYY-MM-DD HH:mm:ss and ISO strings.
    const normalized = String(dateString).replace(' ', 'T');
    const date = new Date(normalized);

    if (!Number.isNaN(date.getTime())) {
        return date.toLocaleDateString('es-DO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    }

    // Fallback for malformed values
    return dateString;
};

const legalEntryFeeDisplay = computed(() => {
    return Number(props.payoff_summary?.legal_entry_fees ?? 0);
});

const legalFeesTotalDisplay = computed(() => {
    return Number(props.payoff_summary?.legal_fees ?? 0);
});

const additionalLegalFeesDisplay = computed(() => {
    return Math.max(0, legalFeesTotalDisplay.value - legalEntryFeeDisplay.value);
});

const lateFeesDisplay = computed(() => {
    return Number(props.payoff_summary?.late_fees ?? 0);
});

const interestDisplay = computed(() => {
    return Number(props.payoff_summary?.interest_display ?? props.loan.interest_accrued ?? 0);
});

const interestAtCutoffDisplay = computed(() => {
    return Number(props.payoff_summary?.interest_at_cutoff ?? props.loan.interest_accrued ?? 0);
});

const interestNextCutDaysDisplay = computed(() => {
    return Number(props.payoff_summary?.interest_next_cut_days ?? 0);
});

const capitalPendingDisplay = computed(() => {
    const explicitCapital = Number(props.payoff_summary?.capital_display ?? 0);
    if (explicitCapital > 0) {
        return explicitCapital;
    }

    const principal = Number(props.payoff_summary?.principal ?? props.loan.principal_outstanding ?? 0);

    return principal + legalFeesTotalDisplay.value + lateFeesDisplay.value;
});


const parseEntryMeta = (entry) => {
    if (!entry?.meta) {
        return {};
    }

    if (typeof entry.meta === 'string') {
        try {
            return JSON.parse(entry.meta);
        } catch {
            return {};
        }
    }

    return entry.meta;
};

const paymentBreakdownRows = (entry) => {
    const breakdown = parseEntryMeta(entry)?.payment_breakdown ?? {};
    const rows = [
        { key: 'interest', label: 'Interés', paid: Number(breakdown?.interest?.paid ?? 0), remaining: Math.max(0, Number(breakdown?.interest?.remaining ?? 0)) },
        { key: 'late_fee', label: 'Mora', paid: Number(breakdown?.late_fee?.paid ?? 0), remaining: Math.max(0, Number(breakdown?.late_fee?.remaining ?? 0)) },
        { key: 'legal_entry_fee', label: 'Entrada a legal', paid: Number(breakdown?.legal_entry_fee?.paid ?? 0), remaining: Math.max(0, Number(breakdown?.legal_entry_fee?.remaining ?? 0)) },
        { key: 'legal_other_fee', label: 'Gastos legales', paid: Number(breakdown?.legal_other_fee?.paid ?? 0), remaining: Math.max(0, Number(breakdown?.legal_other_fee?.remaining ?? 0)) },
    ];

    return rows.filter((row) => row.paid > 0);
};

const isCutoffAccrualEntry = (entry) => {
    const meta = typeof entry?.meta === 'string' ? JSON.parse(entry.meta || '{}') : (entry?.meta || {});
    return meta?.accrual_context === 'cutoff';
};

const loanLateFeeModeLabel = computed(() => {
    return (props.loan.late_fee_cutoff_mode === 'fixed_cutoff') ? 'Al corte fijo' : 'Por pagos';
});

const loanAccrualModeLabel = computed(() => {
    return (props.loan.payment_accrual_mode === 'cutoff_only') ? 'Solo al corte' : 'Tiempo real';
});

const cutoffCycleLabel = computed(() => {
    if (props.loan.cutoff_cycle_mode === 'fixed_dates') {
        return 'Fechas fijas';
    }

    if (props.loan.modality === 'monthly') {
        return props.loan.month_day_count_mode === 'thirty' ? 'Calendario (30 días)' : 'Calendario (días exactos)';
    }

    return 'Calendario';
});

const legalFeeDescription = (entry) => {
    if (entry?.type !== 'legal_fee') {
        return '';
    }

    const meta = parseEntryMeta(entry);
    const reason = String(meta?.reason ?? '');
    const notes = String(meta?.notes ?? '').trim();

    if (notes.length > 0) {
        return notes;
    }

    if (reason === 'legal_entry') {
        return 'Cargo automático por entrada a legal.';
    }

    if (reason === 'opening') {
        return 'Gasto legal de apertura del préstamo.';
    }

    return '';
};

const goBack = () => {
    window.history.back();
};

const showPaymentModal = ref(false);
const showCancellationModal = ref(false);
const showLegalPayoffModal = ref(false);
const showAddLegalFeeModal = ref(false);
const showLegalDocumentInfoModal = ref(false);

const isPaymentDeletionDisabled = computed(() => {
    return ['1', 'true', 'yes', 'on'].includes(String(page.props.settings?.disable_payment_deletion ?? '0').toLowerCase());
});


const overdueInstallmentLabel = computed(() => {
    const count = Number(props.loan?.arrears_info?.count ?? 0);
    return Math.abs(count - 1) < 0.0001 ? 'cuota vencida' : 'cuotas vencidas';
});

const statusLabel = (status) => {
    const labels = {
        active: 'Activo',
        closed: 'Cerrado',
        closed_refinanced: 'Consolidado',
        cancelled: 'Cancelado',
        written_off: 'Incobrable',
        defaulted: 'En mora',
    };

    return labels[status] ?? status;
};

const modalityLabel = (modality) => {
    const labels = {
        daily: 'Diario',
        weekly: 'Semanal',
        biweekly: 'Quincenal',
        monthly: 'Mensual',
    };

    return labels[modality] ?? modality;
};

const interestModeLabel = (interestMode) => {
    const labels = {
        simple: 'Simple',
        compound: 'Compuesto',
    };

    return labels[interestMode] ?? interestMode;
};

const canDeletePayments = computed(() => {
    return !isPaymentDeletionDisabled.value
        && !props.loan.consolidated_into_loan_id
        && !['written_off', 'cancelled', 'closed', 'closed_refinanced'].includes(props.loan.status);
});

// Delete Payment Logic
const paymentToDelete = ref(null);
const showDeleteConfirm = ref(false);

const confirmDeletePayment = (ledgerEntry) => {
    paymentToDelete.value = ledgerEntry;
    showDeleteConfirm.value = true;
};

const executeDeletePayment = () => {
    if (!paymentToDelete.value) return;

    // Prioritize the direct payment_id column
    let paymentId = paymentToDelete.value.payment_id;

    // Fallback to meta for legacy compatibility
    if (!paymentId && paymentToDelete.value.meta) {
        const meta = typeof paymentToDelete.value.meta === 'string'
            ? JSON.parse(paymentToDelete.value.meta)
            : paymentToDelete.value.meta;

        paymentId = meta?.payment_id;
    }

    if (!paymentId) {
        console.error("Payment ID not found for ledger entry", paymentToDelete.value);
        alert("Error: No se encontró el ID del pago asociado a este registro. Contacte soporte.");
        return;
    }

    router.delete(route('loans.payments.destroy', [props.loan.id, paymentId]), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirm.value = false;
            paymentToDelete.value = null;
        },
        onError: (errors) => {
            console.error("Error deleting payment", errors);
            alert("Error al eliminar el pago.");
        }
    });
};


const downloadCSV = () => {
    if (!props.projected_schedule || props.projected_schedule.length === 0) return;

    const headers = ['Periodo', 'Fecha', 'Cuota', 'Interes', 'Capital', 'Balance'];
    const rows = props.projected_schedule.map(row => [
        row.period,
        row.date,
        row.installment,
        row.interest,
        row.principal,
        row.balance
    ]);

    let csvContent = "data:text/csv;charset=utf-8,"
        + headers.join(",") + "\n"
        + rows.map(e => e.join(",")).join("\n");

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `amortizacion_${props.loan.code}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <Head :title="'Préstamo ' + loan.code" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-start gap-4 min-w-0 w-full overflow-hidden">
                <Button variant="ghost" @click="goBack" class="p-2 h-10 w-10 rounded-full hover:bg-surface-100 text-surface-500 cursor-pointer shrink-0 mt-1">
                    <i class="fa-solid fa-arrow-left"></i>
                </Button>
                <div class="min-w-0 w-full">
                    <h2 class="font-bold text-xl md:text-2xl text-surface-800 leading-tight break-words">Préstamo - {{ loan.client.first_name }} {{ loan.client.last_name }}</h2>
                    <p class="text-sm text-surface-500 font-medium break-all">Detalle de Operación #{{ loan.code }}</p>
                </div>
            </div>
        </template>

        <div class="pt-2 pb-6 space-y-6">
            <div class="bg-white rounded-2xl border border-surface-100 shadow-sm p-3 md:p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        v-if="loan.status === 'active' || loan.status === 'defaulted'"
                        @click="showCancellationModal = true"
                        variant="ghost"
                        class="h-9 px-3 text-sm text-danger-500 hover:text-danger-700 hover:bg-danger-50"
                    >
                        <i class="fa-solid fa-ban mr-2"></i> {{ loan.payments_count > 0 ? 'Incobrable' : 'Cancelar' }}
                    </Button>

                    <Button v-if="loan.status === 'active' || loan.status === 'defaulted'" variant="ghost" class="h-9 px-3 text-sm text-warning-600 hover:text-warning-700 hover:bg-warning-50" @click="showAddLegalFeeModal = true">
                        <i class="fa-solid fa-scale-balanced mr-2"></i> Agregar gasto legal
                    </Button>

                    <Button variant="ghost" class="h-9 px-3 text-sm text-success-600 hover:text-success-700 hover:bg-success-50" @click="showLegalPayoffModal = true">
                        <i class="fa-solid fa-receipt mr-2"></i> Resumen Legal
                    </Button>

                    <Button
                        variant="ghost"
                        class="h-9 px-3 text-sm text-info-700 hover:text-info-800 hover:bg-info-50"
                        @click="showLegalDocumentInfoModal = true"
                    >
                        <i class="fa-solid fa-file-signature mr-2"></i> Documento Legal
                    </Button>

                    <Button v-if="loan.status === 'active' || loan.status === 'defaulted'" @click="showPaymentModal = true" class="h-9 bg-success-600 hover:bg-success-700 text-white rounded-xl shadow-md px-4 text-sm transition-all cursor-pointer whitespace-nowrap">
                        <i class="fa-solid fa-money-bill-wave mr-2"></i> Registrar Pago
                    </Button>
                </div>
            </div>

            <!-- Error Banner -->
            <div v-if="Object.keys($page.props.errors).length > 0" class="max-w-4xl mx-auto bg-danger-50 border border-danger-200 rounded-xl p-4 mb-4">
                <div class="flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation text-danger-600 mt-1"></i>
                    <div>
                        <h4 class="font-bold text-danger-800">Error</h4>
                        <ul class="text-sm text-danger-600 list-disc list-inside mt-1">
                            <li v-for="(error, key) in $page.props.errors" :key="key">{{ error }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-surface-100 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-primary-50 rounded-xl flex items-center justify-center text-primary-600">
                             <i class="fa-solid fa-scale-balanced"></i>
                        </div>
                        <span class="text-xs font-semibold text-primary-600 bg-primary-50 px-2 py-1 rounded-full">BALANCE</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-surface-500 mb-1">Balance Pendiente</p>
                        <h3 class="text-2xl font-bold text-surface-800">{{ formatCurrency(display_balance_total ?? loan.balance_total) }}</h3>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-surface-100 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-info-50 rounded-xl flex items-center justify-center text-info-600">
                             <i class="fa-solid fa-sack-dollar"></i>
                        </div>
                        <span class="text-xs font-semibold text-info-600 bg-info-50 px-2 py-1 rounded-full">CAPITAL</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-surface-500 mb-1">Capital</p>
                        <div class="flex items-center gap-2">
                            <h3 class="text-2xl font-bold text-surface-800">{{ formatCurrency(capitalPendingDisplay) }}</h3>
                            <div v-if="(legalEntryFeeDisplay + additionalLegalFeesDisplay + lateFeesDisplay) > 0" class="relative group">
                                <button type="button" class="w-5 h-5 rounded-full bg-surface-100 text-surface-500 text-xs font-bold inline-flex items-center justify-center">i</button>
                                <div class="pointer-events-none absolute left-1/2 top-full z-10 mt-2 w-72 -translate-x-1/2 rounded-lg bg-surface-900 text-white text-xs p-3 opacity-0 group-hover:opacity-100 transition-opacity shadow-lg space-y-1">
                                    <p class="font-semibold">Incluye:</p>
                                    <p>• Entrada a legal: {{ formatCurrency(legalEntryFeeDisplay) }}</p>
                                    <p>• Gastos legales: {{ formatCurrency(additionalLegalFeesDisplay) }}</p>
                                    <p>• Mora acumulada: {{ formatCurrency(lateFeesDisplay) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-surface-100 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-success-50 rounded-xl flex items-center justify-center text-success-600">
                             <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <span class="text-xs font-semibold text-success-600 bg-success-50 px-2 py-1 rounded-full">INTERÉS</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-surface-500 mb-1">Interés Acumulado</p>
                        <div class="flex items-center gap-2">
                            <h3 class="text-2xl font-bold text-surface-800">{{ formatCurrency(interestDisplay) }}</h3>
                            <div v-if="interestAtCutoffDisplay > 0" class="relative group">
                                <button type="button" class="w-5 h-5 rounded-full bg-surface-100 text-surface-500 text-xs font-bold inline-flex items-center justify-center">i</button>
                                <div class="pointer-events-none absolute left-1/2 top-full z-10 mt-2 w-72 -translate-x-1/2 rounded-lg bg-surface-900 text-white text-xs p-3 opacity-0 group-hover:opacity-100 transition-opacity shadow-lg space-y-1">
                                    <p class="font-semibold">Próximo corte:</p>
                                    <p>• Interés: {{ formatCurrency(interestAtCutoffDisplay) }}</p>
                                    <p>• Días: {{ interestNextCutDaysDisplay }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-surface-100 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center text-orange-600">
                             <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <span class="text-xs font-semibold text-orange-600 bg-orange-50 px-2 py-1 rounded-full uppercase">{{ modalityLabel(loan.modality) }}</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-surface-500 mb-1">Cuota Fija Estimada</p>
                        <h3 class="text-2xl font-bold text-surface-800">{{ formatCurrency(loan.installment_amount) }}</h3>
                    </div>
                </div>
            </div>

            <!-- Arrears Alert Section -->
            <div v-if="loan.arrears_info && loan.arrears_info.amount > 0" class="bg-danger-50 border border-danger-100 rounded-2xl p-6 shadow-sm animate-in fade-in slide-in-from-top-4">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-danger-100 rounded-full flex items-center justify-center text-danger-600 flex-shrink-0">
                        <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-danger-800">Préstamo en Atraso</h3>
                        <p class="text-danger-600 mt-1">
                            Este préstamo tiene <span class="font-bold">{{ loan.arrears_info.count }} {{ overdueInstallmentLabel }}</span>.
                            El monto total en atraso es de <span class="font-bold">{{ formatCurrency(loan.arrears_info.amount) }}</span>.
                        </p>
                        <div class="mt-4 flex gap-4 text-sm">
                            <div class="bg-white px-3 py-1.5 rounded-lg border border-danger-200 text-danger-700 font-medium shadow-sm">
                                <i class="fa-regular fa-clock mr-2"></i> {{ loan.arrears_info.days }} días de atraso
                            </div>
                            <div v-if="loan.arrears_info.late_fees_due > 0" class="bg-white px-3 py-1.5 rounded-lg border border-danger-200 text-danger-700 font-medium shadow-sm">
                                <i class="fa-solid fa-scale-balanced mr-2"></i>
                                Mora: {{ loan.arrears_info.late_fee_days }} días - {{ formatCurrency(loan.arrears_info.late_fees_due) }}
                            </div>
                            <div v-if="interestAtCutoffDisplay > 0" class="bg-white px-3 py-1.5 rounded-lg border border-danger-200 text-danger-700 font-medium shadow-sm">
                                <i class="fa-solid fa-chart-line mr-2"></i>
                                Interés al próximo corte: {{ formatCurrency(interestAtCutoffDisplay) }} ({{ interestNextCutDaysDisplay }} días)
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Loan Info Sidebar -->
                <div class="bg-white rounded-2xl shadow-sm border border-surface-100 overflow-hidden h-fit">
                    <div class="p-6 border-b border-surface-100 bg-surface-50/50">
                        <h3 class="font-bold text-lg text-surface-800">Detalles del Préstamo</h3>
                        <div class="flex items-center mt-2">
                             <Badge :variant="loan.status === 'active' ? 'default' : 'secondary'" class="rounded-md capitalize text-sm px-3 py-1">
                                {{ statusLabel(loan.status) }}
                            </Badge>
                            <Badge v-if="loan.legal_status" variant="outline" class="rounded-md text-xs px-3 py-1 ml-2 text-warning-700 border-warning-200 bg-warning-50">
                                Legal
                            </Badge>
                            <span class="ml-auto font-mono text-surface-500 text-sm">{{ loan.code }}</span>
                        </div>
                    </div>
                    <div class="p-6 space-y-6">
                         <!-- Client -->
                        <div>
                            <p class="text-xs font-semibold text-surface-500 uppercase mb-1">Cliente</p>
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-surface-100 flex items-center justify-center text-surface-500 mr-3">
                                    <i class="fa-solid fa-user text-xs"></i>
                                </div>
                                <div>
                                    <Link :href="route('clients.show', loan.client.id)" class="text-primary-600 font-medium hover:underline block">
                                        {{ loan.client.first_name }} {{ loan.client.last_name }}
                                    </Link>
                                    <span class="text-xs text-surface-400">{{ loan.client.national_id }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="h-px bg-surface-100"></div>

                        <!-- Dates -->
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs font-semibold text-surface-500 uppercase mb-1">Fecha Emisión</p>
                                <p class="text-surface-800 font-medium flex items-center">
                                    <i class="fa-regular fa-calendar mr-2 text-surface-400"></i>
                                    {{ formatDate(loan.start_date) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-surface-500 uppercase mb-1">Próxima fecha de corte</p>
                                <p class="text-surface-800 font-medium">{{ formatDate(payoff_summary?.next_cutoff_date) }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-surface-500 uppercase mb-1">Fecha Vencimiento</p>
                                <p class="font-medium flex items-center" :class="loan.maturity_date && new Date(loan.maturity_date) < new Date() && loan.status === 'active' ? 'text-danger-600' : 'text-surface-800'">
                                    <i class="fa-regular fa-calendar-xmark mr-2" :class="loan.maturity_date && new Date(loan.maturity_date) < new Date() && loan.status === 'active' ? 'text-danger-400' : 'text-surface-400'"></i>
                                    {{ formatDate(loan.maturity_date) }}
                                </p>
                            </div>
                        </div>

                         <div class="h-px bg-surface-100"></div>

                         <!-- Terms -->
                         <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs font-semibold text-surface-500 uppercase mb-1">Tasa Mensual</p>
                                    <p class="text-surface-800 font-medium">{{ loan.monthly_rate }}%</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-surface-500 uppercase mb-1">Tipo Interés</p>
                                    <p class="text-surface-800 font-medium capitalize">{{ interestModeLabel(loan.interest_mode) }}</p>
                                </div>
                            </div>
                             <div>
                                <p class="text-xs font-semibold text-surface-500 uppercase mb-1">Plazo</p>
                                <p class="text-surface-800 font-medium">{{ loan.target_term_periods ? loan.target_term_periods + ' Cuotas' : 'Indefinido' }}</p>
                            </div>
                         </div>


                        <div class="space-y-3">
                            <p class="text-xs font-semibold text-surface-500 uppercase mb-1">Configuración de corte y mora</p>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Devengo de interés</span>
                                <span class="font-medium text-surface-800">{{ loanAccrualModeLabel }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Cálculo de mora</span>
                                <span class="font-medium text-surface-800">{{ loanLateFeeModeLabel }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Ciclo de cortes</span>
                                <span class="font-medium text-surface-800">{{ cutoffCycleLabel }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Disparador mora</span>
                                <span class="font-medium text-surface-800">{{ loan.late_fee_trigger_value ?? loan.late_fee_grace_period ?? 0 }} {{ loan.late_fee_trigger_type === 'installments' ? 'cuotas' : 'días' }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Tipo de días mora</span>
                                <span class="font-medium text-surface-800">{{ loan.late_fee_day_type === 'calendar' ? 'Calendario' : 'Laborables' }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Mora por día</span>
                                <span class="font-medium text-surface-800">{{ formatCurrency(loan.late_fee_daily_amount || 0) }}</span>
                            </div>
                        </div>

                        <div class="h-px bg-surface-100"></div>

                        <div class="space-y-3">
                            <p class="text-xs font-semibold text-surface-500 uppercase mb-1">Gastos Legales</p>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Estado</span>
                                <span class="font-medium text-surface-800">
                                    {{ loan.legal_fee_enabled ? 'Aplicado' : 'No aplicado' }}
                                </span>
                            </div>
                            <div v-if="loan.legal_status" class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Entrada Legal</span>
                                <span class="font-medium text-surface-800">{{ formatDate(loan.legal_entered_at) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Monto</span>
                                <span class="font-medium text-surface-800">{{ formatCurrency(loan.legal_fee_amount) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">En la deuda</span>
                                <span class="font-medium text-surface-800">{{ loan.legal_fee_financed ? 'Sí' : 'No' }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Auto Legal</span>
                                <span class="font-medium text-surface-800">{{ loan.legal_auto_enabled ? 'Sí' : 'No' }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Días para Legal</span>
                                <span class="font-medium text-surface-800">{{ loan.legal_days_overdue_threshold ?? ($page.props.settings?.legal_days_overdue_threshold ?? 30) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-surface-600">Costo Entrada Legal</span>
                                <span class="font-medium text-surface-800">{{ formatCurrency(loan.legal_entry_fee_amount ?? ($page.props.settings?.legal_entry_fee_default ?? 4000)) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Download Schedule -->
                    <div v-if="projected_schedule && projected_schedule.length > 0" class="mt-6 bg-surface-50 rounded-2xl p-6 border border-surface-100">
                        <h4 class="font-bold text-surface-800 mb-2">Tabla de Amortización</h4>
                        <p class="text-xs text-surface-500 mb-4">Descargue la proyección de pagos actualizada.</p>
                        <Button @click="downloadCSV" variant="outline" class="w-full bg-white border-surface-200 hover:bg-surface-100 text-surface-700">
                            <i class="fa-solid fa-file-csv mr-2 text-success-600"></i> Descargar Excel (CSV)
                        </Button>
                    </div>

                </div>

                <!-- Ledger Table -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Transactions -->
                    <div class="bg-white rounded-2xl shadow-sm border border-surface-100 overflow-hidden">
                    <div class="p-6 border-b border-surface-100 bg-surface-50/50">
                        <h3 class="font-bold text-lg text-surface-800">Historial de Transacciones</h3>
                        <p class="text-sm text-surface-500">Movimientos de capital e intereses.</p>
                    </div>
                    <div class="p-0">
                        <Table>
                            <TableHeader class="bg-surface-50">
                                <TableRow>
                                    <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider pl-6">Fecha</TableHead>
                                    <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Tipo</TableHead>
                                    <TableHead class="text-right text-xs font-semibold text-surface-500 uppercase tracking-wider">Monto</TableHead>
                                    <TableHead class="text-right text-xs font-semibold text-surface-500 uppercase tracking-wider">Balance</TableHead>
                                    <TableHead v-if="canDeletePayments" class="text-right text-xs font-semibold text-surface-500 uppercase tracking-wider pr-6">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="entry in loan.ledger_entries" :key="entry.id" class="hover:bg-surface-50 transition-colors group">
                                    <TableCell class="text-surface-600 whitespace-nowrap pl-6">{{ formatDate(entry.occurred_at) }}</TableCell>
                                    <TableCell class="capitalize">
                                        <span v-if="entry.type === 'disbursement'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800">
                                            Desembolso
                                        </span>
                                        <span v-else-if="entry.type === 'payment'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-success-100 text-success-800">
                                            Pago
                                        </span>
                                        <span v-else-if="entry.type === 'interest_accrual'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-100 text-surface-800">
                                            Interés
                                        </span>
                                        <span v-else-if="entry.type === 'fee_accrual'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700">
                                            Mora
                                        </span>
                                        <span v-else-if="entry.type === 'legal_fee'" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800">
                                            Gastos legales
                                            <span v-if="legalFeeDescription(entry)" class="relative inline-flex items-center group/info">
                                                <span class="w-4 h-4 rounded-full bg-primary-200 text-primary-800 text-[10px] font-bold inline-flex items-center justify-center">i</span>
                                                <span class="pointer-events-none absolute left-1/2 -translate-x-1/2 top-full z-10 mt-2 w-64 rounded-lg bg-surface-900 text-white text-xs p-2 opacity-0 group-hover/info:opacity-100 transition-opacity shadow-lg normal-case text-left">
                                                    {{ legalFeeDescription(entry) }}
                                                </span>
                                            </span>
                                        </span>
                                        <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-100 text-surface-700">
                                            Movimiento
                                        </span>
                                        <span v-if="entry.type === 'interest_accrual' && isCutoffAccrualEntry(entry)" class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-info-100 text-info-700">Corte</span>
                                        <span v-if="entry.type === 'fee_accrual' && isCutoffAccrualEntry(entry)" class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-info-100 text-info-700">Corte</span>
                                    </TableCell>
                                    <TableCell class="text-right font-medium">
                                        <span :class="{
                                            'text-success-600': entry.principal_delta < 0 || entry.interest_delta < 0,
                                            'text-primary-700': entry.amount > 0 && (entry.type === 'disbursement' || entry.type === 'legal_fee'),
                                            'text-orange-600': entry.type === 'fee_accrual',
                                                                                        'text-surface-500': entry.type === 'interest_accrual'
                                        }">
                                            {{ formatCurrency(entry.amount) }}
                                        </span>
                                        <div v-if="entry.type === 'payment'" class="text-xs text-surface-400 flex items-center justify-end gap-2">
                                            <span>Cap: {{ formatCurrency(Math.abs(entry.principal_delta)) }}</span>
                                            <div v-if="paymentBreakdownRows(entry).length > 0" class="relative group inline-block">
                                                <button type="button" class="w-4 h-4 rounded-full bg-surface-100 text-surface-500 text-[10px] font-bold inline-flex items-center justify-center">i</button>
                                                <div class="pointer-events-none absolute right-0 top-full z-10 mt-2 w-80 rounded-lg bg-surface-900 text-white text-xs p-3 opacity-0 group-hover:opacity-100 transition-opacity shadow-lg space-y-1 text-left">
                                                    <p class="font-semibold">Detalle del pago</p>
                                                    <p v-for="row in paymentBreakdownRows(entry)" :key="row.key">
                                                        • {{ row.label }} pagado: {{ formatCurrency(row.paid) }}
                                                        <span v-if="row.remaining > 0"> | Resta: {{ formatCurrency(row.remaining) }}</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else-if="entry.type === 'interest_accrual'" class="text-xs text-surface-500">
                                            {{ entry.meta?.days ?? 0 }} días de interés
                                        </div>
                                        <div v-else-if="entry.type === 'fee_accrual'" class="text-xs text-orange-500">
                                            {{ entry.meta?.late_fee_days ?? 0 }} días de mora
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right font-bold text-surface-800">{{ formatCurrency(entry.balance_after) }}</TableCell>
                                    <TableCell v-if="canDeletePayments" class="text-right pr-6">
                                        <button v-if="entry.type === 'payment' && (entry.payment_id || entry.meta?.payment_id)"
                                            @click="confirmDeletePayment(entry)"
                                            class="text-surface-300 hover:text-danger-500 transition-colors p-1"
                                            title="Eliminar Pago">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    </div>

                    <!-- Projected Schedule Table (Collapsed by default maybe? Or just shown) -->
                    <div v-if="projected_schedule && projected_schedule.length > 0" class="bg-white rounded-2xl shadow-sm border border-surface-100 overflow-hidden">
                        <div class="p-6 border-b border-surface-100 bg-surface-50/50 flex justify-between items-center">
                            <div>
                                <h3 class="font-bold text-lg text-surface-800">Proyección de Pagos</h3>
                                <p class="text-sm text-surface-500">Basado en el balance actual y cuota fija.</p>
                            </div>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <Table>
                                <TableHeader class="bg-surface-50 sticky top-0">
                                    <TableRow>
                                        <TableHead class="text-xs">#</TableHead>
                                        <TableHead class="text-xs">Fecha</TableHead>
                                        <TableHead class="text-right text-xs">Cuota</TableHead>
                                        <TableHead class="text-right text-xs">Interés</TableHead>
                                        <TableHead class="text-right text-xs">Capital</TableHead>
                                        <TableHead class="text-right text-xs">Balance</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="row in projected_schedule" :key="row.period" class="hover:bg-surface-50">
                                        <TableCell class="py-2 text-xs text-surface-500">{{ row.period }}</TableCell>
                                        <TableCell class="py-2 text-xs text-surface-700 font-mono">{{ formatDate(row.date).split(' -')[0] }}</TableCell>
                                        <TableCell class="py-2 text-xs text-right">{{ formatCurrency(row.installment) }}</TableCell>
                                        <TableCell class="py-2 text-xs text-right text-surface-500">{{ formatCurrency(row.interest) }}</TableCell>
                                        <TableCell class="py-2 text-xs text-right text-success-600 font-medium">{{ formatCurrency(row.principal) }}</TableCell>
                                        <TableCell class="py-2 text-xs text-right font-bold text-surface-800">{{ formatCurrency(row.balance) }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <PaymentModal
            v-model:open="showPaymentModal"
            :loan="loan"
        />

        <WarningModal
            :open="showDeleteConfirm"
            @update:open="showDeleteConfirm = $event"
            title="Eliminar Pago"
            message="¿Está seguro de que desea eliminar este pago? Esta acción revertirá los efectos del pago en el balance del préstamo y recalculará los intereses si es necesario. Esta acción no se puede deshacer."
            :confirmText="'Sí, Eliminar'"
            @confirm="executeDeletePayment"
        />

        <WarningModal
            :open="showLegalDocumentInfoModal"
            @update:open="showLegalDocumentInfoModal = $event"
            title="Documento legal en proceso"
            message="Esta función está temporalmente deshabilitada mientras afinamos el generador. Próximamente este botón permitirá crear un documento legal del préstamo con datos del cliente, términos financieros y estado actualizado para impresión y respaldo."
        />

        <LoanCancellationModal
            :show="showCancellationModal"
            :loan="loan"
            @close="showCancellationModal = false"
        />

        <LegalPayoffModal
            :open="showLegalPayoffModal"
            :loan="loan"
            :summary="payoff_summary"
            :print-url="route('loans.legal-summary', loan.id)"
            @update:open="showLegalPayoffModal = $event"
        />

        <AddLegalFeeModal
            :open="showAddLegalFeeModal"
            :loan-id="loan.id"
            @update:open="showAddLegalFeeModal = $event"
        />
    </AuthenticatedLayout>
</template>
