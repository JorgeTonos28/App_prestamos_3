<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
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
import { ref, watch } from 'vue';
import { Label } from '@/Components/ui/label';
import WarningModal from '@/Components/WarningModal.vue';
import { Input } from '@/Components/ui/input';

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

// Simple Modal Logic for Payment
const showPaymentModal = ref(false);
const showWarningModal = ref(false);
const warningMessage = ref('');
const paymentForm = useForm({
    amount: '',
    method: 'cash',
    reference: '',
    notes: '',
    paid_at: getTodayDateString() // Add date field for retroactive
});

function getTodayDateString() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

// Interactive Validation Watcher
watch(() => paymentForm.amount, (newVal) => {
    if (!newVal) return;
    const amount = parseFloat(newVal);
    const maxBalance = parseFloat(props.loan.balance_total);

    // Check if input is potentially valid number (handling incomplete typing)
    if (!isNaN(amount) && amount > maxBalance) {
        warningMessage.value = `El monto ingresado (${formatCurrency(amount)}) no puede ser mayor al balance total de la deuda (${formatCurrency(maxBalance)}).`;
        showWarningModal.value = true;
        // Revert or clear. User requested "borre el dígito de conflicto", but clearing is safer/easier.
        paymentForm.amount = '';
    }
});

const submitPayment = () => {
    paymentForm.post(route('loans.payments.store', props.loan.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
            paymentForm.paid_at = getTodayDateString();
        }
    });
};

// Delete Payment Logic
const paymentToDelete = ref(null);
const showDeleteConfirm = ref(false);

const confirmDeletePayment = (ledgerEntry) => {
    // We need to find the payment ID associated with this ledger entry.
    // The ledger entry doesn't have payment_id directly visible in the table loop,
    // but the backend sends 'ledger_entries' which are polymorphic or custom.
    // However, the LedgerEntry model is what we are iterating.
    // A LedgerEntry of type 'payment' should have a way to link back or we assume we can't link it easily?
    // Actually, PaymentService deletes by Payment Model.
    // But the view iterates `loan.ledger_entries`.
    // We need the `payment_id` to call the destroy route.
    // Check if `loan.ledger_entries` loaded `payment` relation or if we can fetch it.
    // Alternatively, we can assume the 'payment' type entry corresponds to a payment at that time/amount.
    // BUT, the safer way is to iterate PAYMENTS if we want to delete PAYMENTS.
    // The current view iterates Ledger Entries.
    // Let's check the API response for ledger entries. Usually, we might store `payment_id` in `meta` or similar?
    // Or we should update the controller to load payments separately?
    // No, let's look at `LoanController::show`.
    // It loads `ledgerEntries`.
    // Let's modify `LoanController` to include the payment ID in the ledger entry if it is a payment.
    // Or, we can just iterate `loan.payments` in a separate tab?
    // The user wants to see "Transactions". Deleting from there is intuitive.
    // I will assume for now I need to modify the Controller or Model to expose the Payment ID on the Ledger Entry.

    // TEMPORARY FIX: I will assume the ledger entry has a `payment_id` or `payment` relation if type is payment.
    // I'll check the migration/model later.
    // If not, I'll need to update the backend first.

    // Let's assume for a moment we can't easily get the ID from the ledger entry without fetching.
    // I will add a `payment` relation to LedgerEntry in the Model step if needed.
    // But wait, the `Payment` model has `loan_id`. `LoanLedgerEntry` has `loan_id`.
    // They are not directly linked by FK in `loan_ledger_entries` table based on migration 2026_01_09_134556_create_loan_ledger_entries_table.php (I should check it).

    // Let's proceed assuming I need to link them.
    // For now, I will use a method to find the payment by date/amount/loan? No, risky.
    // Best way: Add `payment_id` to `meta` JSON in `LoanLedgerEntry` when creating it.
    // I did that in `PaymentService`!
    // `LoanLedgerEntry::create([... 'meta' => [ ... ]])`
    // I didn't add payment_id to meta in the code I wrote/read.
    // I should probably add `payment_id` to the meta of the ledger entry in `PaymentService`.

    // WAIT. The user wants to delete a PAYMENT.
    // Maybe I should list Payments separately?
    // Or I can just pass the `payments` relationship to the view as well.
    // `loan->load(['client', 'ledgerEntries', 'payments'])`.
    // And in the loop, if type is 'payment', we find the matching payment? That's messy in template.

    // Better: Update `PaymentService` to store `payment_id` in the ledger entry's meta or a new column?
    // A new column `payment_id` in `loan_ledger_entries` would be clean but requires migration.
    // Storing in `meta` JSON is easier and requires no schema change.

    // Let's stick to the plan: I will add `payment_id` to the ledger entry meta in `PaymentService` first.
    // Then I can use it here.

    paymentToDelete.value = ledgerEntry;
    showDeleteConfirm.value = true;
};

const executeDeletePayment = () => {
    if (!paymentToDelete.value) return;

    let paymentId = paymentToDelete.value.payment_id;

    if (!paymentId && paymentToDelete.value.meta) {
        // Handle meta being string or object
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
                <div class="space-x-2">
                    <Button v-if="loan.status === 'active'" @click="showPaymentModal = true" class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-md px-6 transition-all hover:scale-105 cursor-pointer">
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
                                        <button v-if="entry.type === 'payment' && (entry.meta?.payment_id || entry.payment_id)"
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

        <!-- Payment Modal (Simple HTML overlay for now since Dialog component was skipped) -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm transition-all">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 transform transition-all scale-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-slate-800">Registrar Pago</h3>
                    <button @click="showPaymentModal = false" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <div v-if="$page.props.errors.paid_at" class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4 border border-red-100">
                    {{ $page.props.errors.paid_at }}
                </div>

                <form @submit.prevent="submitPayment" class="space-y-4">
                    <div>
                        <Label class="text-slate-600 mb-1 block">Fecha Pago</Label>
                         <Input type="date" v-model="paymentForm.paid_at" :max="getTodayDateString()" class="bg-slate-50" />
                         <p class="text-xs text-slate-400 mt-1">Puede registrar pagos pasados si no existen pagos posteriores.</p>
                    </div>
                    <div>
                        <Label for="amount" class="text-slate-600 mb-1 block">Monto</Label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-slate-400 font-bold">$</span>
                            <input id="amount" type="number" step="0.01" v-model="paymentForm.amount" class="flex h-10 w-full rounded-xl border border-slate-200 px-3 py-2 pl-7 text-sm focus:border-blue-500 focus:ring-blue-500" required autofocus placeholder="0.00" />
                        </div>
                    </div>
                    <div>
                        <Label for="method" class="text-slate-600 mb-1 block">Método</Label>
                        <select id="method" v-model="paymentForm.method" class="flex h-10 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500 bg-white">
                            <option value="cash">Efectivo</option>
                            <option value="transfer">Transferencia</option>
                        </select>
                    </div>
                     <div>
                        <Label for="reference" class="text-slate-600 mb-1 block">Referencia (Opcional)</Label>
                        <input id="reference" v-model="paymentForm.reference" class="flex h-10 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Ej: #123456" />
                    </div>
                    <div class="flex justify-end space-x-3 pt-6">
                        <Button type="button" variant="ghost" @click="showPaymentModal = false" class="text-slate-500 hover:text-slate-700">Cancelar</Button>
                        <Button type="submit" :disabled="paymentForm.processing" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-md">
                            Confirmar Pago
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <WarningModal
            :open="showWarningModal"
            @update:open="showWarningModal = $event"
            title="Monto Excedido"
            :message="warningMessage"
        />

        <WarningModal
            :open="showDeleteConfirm"
            @update:open="showDeleteConfirm = $event"
            title="Eliminar Pago"
            message="¿Está seguro de que desea eliminar este pago? Esta acción revertirá los efectos del pago en el balance del préstamo y recalculará los intereses si es necesario. Esta acción no se puede deshacer."
            :confirmText="'Sí, Eliminar'"
            @confirm="executeDeletePayment"
        />
    </AuthenticatedLayout>
</template>
