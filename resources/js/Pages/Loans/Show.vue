<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
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

const props = defineProps({
    loan: Object,
    projected_schedule: Array // Passed from backend
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    // Ensure we handle plain date strings YYYY-MM-DD correctly without TZ issues
    const parts = dateString.split('T')[0].split('-');
    if (parts.length === 3) {
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        return date.toLocaleDateString('es-DO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }
    return dateString;
};

const goBack = () => {
    window.history.back();
};

const showPaymentModal = ref(false);
const showCancellationModal = ref(false);

const canDeletePayments = computed(() => {
    return !props.loan.consolidated_into_loan_id
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
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" @click="goBack" class="p-2 h-10 w-10 rounded-full hover:bg-slate-100 text-slate-500 cursor-pointer">
                        <i class="fa-solid fa-arrow-left"></i>
                    </Button>
                    <div>
                        <h2 class="font-bold text-2xl text-slate-800 leading-tight">Préstamo - {{ loan.client.first_name }} {{ loan.client.last_name }}</h2>
                        <p class="text-sm text-slate-500 font-medium">Detalle de Operación #{{ loan.code }}</p>
                    </div>
                </div>
                <div class="space-x-2 flex items-center">
                    <Button
                        v-if="loan.status === 'active' || loan.status === 'defaulted'"
                        @click="showCancellationModal = true"
                        variant="ghost"
                        class="text-red-500 hover:text-red-700 hover:bg-red-50"
                    >
                         <i class="fa-solid fa-ban mr-2"></i> {{ loan.payments_count > 0 ? 'Incobrable' : 'Cancelar' }}
                    </Button>

                    <Button v-if="loan.status === 'active' || loan.status === 'defaulted'" @click="showPaymentModal = true" class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-md px-6 transition-all hover:scale-105 cursor-pointer">
                        <i class="fa-solid fa-money-bill-wave mr-2"></i> Registrar Pago
                    </Button>
                </div>
            </div>
        </template>

        <div class="py-6 space-y-8">
            <!-- Error Banner -->
            <div v-if="Object.keys($page.props.errors).length > 0" class="max-w-4xl mx-auto bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                <div class="flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation text-red-600 mt-1"></i>
                    <div>
                        <h4 class="font-bold text-red-800">Error</h4>
                        <ul class="text-sm text-red-600 list-disc list-inside mt-1">
                            <li v-for="(error, key) in $page.props.errors" :key="key">{{ error }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600">
                             <i class="fa-solid fa-scale-balanced"></i>
                        </div>
                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">BALANCE</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Balance Total</p>
                        <h3 class="text-2xl font-bold text-slate-800">{{ formatCurrency(loan.balance_total) }}</h3>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600">
                             <i class="fa-solid fa-sack-dollar"></i>
                        </div>
                        <span class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-full">CAPITAL</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Capital Pendiente</p>
                        <h3 class="text-2xl font-bold text-slate-800">{{ formatCurrency(loan.principal_outstanding) }}</h3>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600">
                             <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">INTERÉS</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Interés Acumulado</p>
                        <h3 class="text-2xl font-bold text-slate-800">{{ formatCurrency(loan.interest_accrued) }}</h3>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center text-orange-600">
                             <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <span class="text-xs font-semibold text-orange-600 bg-orange-50 px-2 py-1 rounded-full uppercase">{{ loan.modality }}</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Cuota Fija Estimada</p>
                        <h3 class="text-2xl font-bold text-slate-800">{{ formatCurrency(loan.installment_amount) }}</h3>
                    </div>
                </div>
            </div>

            <!-- Arrears Alert Section -->
            <div v-if="loan.arrears_info && loan.arrears_info.amount > 0" class="bg-red-50 border border-red-100 rounded-2xl p-6 shadow-sm animate-in fade-in slide-in-from-top-4">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center text-red-600 flex-shrink-0">
                        <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-red-800">Préstamo en Atraso</h3>
                        <p class="text-red-600 mt-1">
                            Este préstamo tiene <span class="font-bold">{{ loan.arrears_info.count }} cuotas vencidas</span>.
                            El monto total en atraso es de <span class="font-bold">{{ formatCurrency(loan.arrears_info.amount) }}</span>.
                        </p>
                        <div class="mt-4 flex gap-4 text-sm">
                            <div class="bg-white px-3 py-1.5 rounded-lg border border-red-200 text-red-700 font-medium shadow-sm">
                                <i class="fa-regular fa-clock mr-2"></i> {{ loan.arrears_info.days }} días de atraso
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Loan Info Sidebar -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden h-fit">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <h3 class="font-bold text-lg text-slate-800">Detalles del Préstamo</h3>
                        <div class="flex items-center mt-2">
                             <Badge :variant="loan.status === 'active' ? 'default' : 'secondary'" class="rounded-md capitalize text-sm px-3 py-1">
                                {{ loan.status === 'active' ? 'Activo' : (loan.status === 'closed' ? 'Cerrado' : loan.status) }}
                            </Badge>
                            <span class="ml-auto font-mono text-slate-500 text-sm">{{ loan.code }}</span>
                        </div>
                    </div>
                    <div class="p-6 space-y-6">
                         <!-- Client -->
                        <div>
                            <p class="text-xs font-semibold text-slate-500 uppercase mb-1">Cliente</p>
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 mr-3">
                                    <i class="fa-solid fa-user text-xs"></i>
                                </div>
                                <div>
                                    <Link :href="route('clients.show', loan.client.id)" class="text-blue-600 font-medium hover:underline block">
                                        {{ loan.client.first_name }} {{ loan.client.last_name }}
                                    </Link>
                                    <span class="text-xs text-slate-400">{{ loan.client.national_id }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="h-px bg-slate-100"></div>

                        <!-- Dates -->
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase mb-1">Fecha Emisión</p>
                                <p class="text-slate-800 font-medium flex items-center">
                                    <i class="fa-regular fa-calendar mr-2 text-slate-400"></i>
                                    {{ formatDate(loan.start_date) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase mb-1">Fecha Vencimiento</p>
                                <p class="font-medium flex items-center" :class="loan.maturity_date && new Date(loan.maturity_date) < new Date() && loan.status === 'active' ? 'text-red-600' : 'text-slate-800'">
                                    <i class="fa-regular fa-calendar-xmark mr-2" :class="loan.maturity_date && new Date(loan.maturity_date) < new Date() && loan.status === 'active' ? 'text-red-400' : 'text-slate-400'"></i>
                                    {{ formatDate(loan.maturity_date) }}
                                </p>
                            </div>
                        </div>

                         <div class="h-px bg-slate-100"></div>

                         <!-- Terms -->
                         <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase mb-1">Tasa Mensual</p>
                                    <p class="text-slate-800 font-medium">{{ loan.monthly_rate }}%</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase mb-1">Tipo Interés</p>
                                    <p class="text-slate-800 font-medium capitalize">{{ loan.interest_mode }}</p>
                                </div>
                            </div>
                             <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase mb-1">Plazo</p>
                                <p class="text-slate-800 font-medium">{{ loan.target_term_periods ? loan.target_term_periods + ' Cuotas' : 'Indefinido' }}</p>
                            </div>
                         </div>
                    </div>

                    <!-- Download Schedule -->
                    <div v-if="projected_schedule && projected_schedule.length > 0" class="mt-6 bg-slate-50 rounded-2xl p-6 border border-slate-100">
                        <h4 class="font-bold text-slate-800 mb-2">Tabla de Amortización</h4>
                        <p class="text-xs text-slate-500 mb-4">Descargue la proyección de pagos actualizada.</p>
                        <Button @click="downloadCSV" variant="outline" class="w-full bg-white border-slate-200 hover:bg-slate-100 text-slate-700">
                            <i class="fa-solid fa-file-csv mr-2 text-green-600"></i> Descargar Excel (CSV)
                        </Button>
                    </div>

                </div>

                <!-- Ledger Table -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Transactions -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <h3 class="font-bold text-lg text-slate-800">Historial de Transacciones</h3>
                        <p class="text-sm text-slate-500">Movimientos de capital e intereses.</p>
                    </div>
                    <div class="p-0">
                        <Table>
                            <TableHeader class="bg-slate-50">
                                <TableRow>
                                    <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider pl-6">Fecha</TableHead>
                                    <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tipo</TableHead>
                                    <TableHead class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Monto</TableHead>
                                    <TableHead class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Balance</TableHead>
                                    <TableHead class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider pr-6">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="entry in loan.ledger_entries" :key="entry.id" class="hover:bg-slate-50 transition-colors group">
                                    <TableCell class="text-slate-600 whitespace-nowrap pl-6">{{ formatDate(entry.occurred_at) }}</TableCell>
                                    <TableCell class="capitalize">
                                        <span v-if="entry.type === 'disbursement'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                            Desembolso
                                        </span>
                                        <span v-else-if="entry.type === 'payment'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                            Pago
                                        </span>
                                        <span v-else-if="entry.type === 'interest_accrual'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800">
                                            Interés
                                        </span>
                                        <span v-else>
                                            {{ entry.type.replace('_', ' ') }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-right font-medium">
                                        <span :class="{
                                            'text-green-600': entry.principal_delta < 0 || entry.interest_delta < 0,
                                            'text-slate-800': entry.amount > 0 && entry.type === 'disbursement',
                                            'text-slate-500': entry.type === 'interest_accrual'
                                        }">
                                            {{ formatCurrency(entry.amount) }}
                                        </span>
                                        <div v-if="entry.type === 'payment'" class="text-xs text-slate-400">
                                            Cap: {{ formatCurrency(Math.abs(entry.principal_delta)) }} | Int: {{ formatCurrency(Math.abs(entry.interest_delta)) }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right font-bold text-slate-800">{{ formatCurrency(entry.balance_after) }}</TableCell>
                                    <TableCell class="text-right pr-6">
                                        <button v-if="canDeletePayments && entry.type === 'payment' && (entry.payment_id || entry.meta?.payment_id)"
                                            @click="confirmDeletePayment(entry)"
                                            class="text-slate-300 hover:text-red-500 transition-colors p-1"
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
                    <div v-if="projected_schedule && projected_schedule.length > 0" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                            <div>
                                <h3 class="font-bold text-lg text-slate-800">Proyección de Pagos</h3>
                                <p class="text-sm text-slate-500">Basado en el balance actual y cuota fija.</p>
                            </div>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <Table>
                                <TableHeader class="bg-slate-50 sticky top-0">
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
                                    <TableRow v-for="row in projected_schedule" :key="row.period" class="hover:bg-slate-50">
                                        <TableCell class="py-2 text-xs text-slate-500">{{ row.period }}</TableCell>
                                        <TableCell class="py-2 text-xs text-slate-700 font-mono">{{ formatDate(row.date).split(' -')[0] }}</TableCell>
                                        <TableCell class="py-2 text-xs text-right">{{ formatCurrency(row.installment) }}</TableCell>
                                        <TableCell class="py-2 text-xs text-right text-slate-500">{{ formatCurrency(row.interest) }}</TableCell>
                                        <TableCell class="py-2 text-xs text-right text-emerald-600 font-medium">{{ formatCurrency(row.principal) }}</TableCell>
                                        <TableCell class="py-2 text-xs text-right font-bold text-slate-800">{{ formatCurrency(row.balance) }}</TableCell>
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

        <LoanCancellationModal
            :show="showCancellationModal"
            :loan="loan"
            @close="showCancellationModal = false"
        />
    </AuthenticatedLayout>
</template>
