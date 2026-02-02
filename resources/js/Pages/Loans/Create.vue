<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent } from '@/Components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { computed, ref, watch } from 'vue';
import ClientModalForm from '@/Components/ClientModalForm.vue';
import axios from 'axios';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';

const props = defineProps({
    clients: Array,
    client_id: [String, Number],
    consolidation_data: Object
});

const getTodayDatetimeString = () => {
    const d = new Date();
    // YYYY-MM-DD - Date type only now
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const form = useForm({
    client_id: props.client_id ? Number(props.client_id) : '',
    start_date: props.consolidation_data ? props.consolidation_data.min_start_date : getTodayDatetimeString(),
    principal_initial: '',
    modality: 'monthly',
    monthly_rate: 5,
    days_in_month_convention: 30,
    interest_mode: 'simple',

    // Toggle Strategy
    calculation_strategy: 'quota', // 'quota' (Fixed Installment) or 'term' (Fixed Term)

    target_term_periods: '', // If strategy = term
    installment_amount: '', // If strategy = quota

    notes: '',
    historical_payments: [],

    // Consolidation
    consolidation_loan_ids: props.consolidation_data ? props.consolidation_data.ids : [],
    consolidation_basis: 'balance' // 'balance' (Total Balance) or 'principal' (Principal Only)
});

// Amortization Table State
const amortizationTable = ref([]);
const isCalculating = ref(false);
const calculationError = ref(null);

watch(
    () => props.client_id,
    (newVal) => {
        if (newVal) {
            form.client_id = Number(newVal);
        }
    },
    { immediate: true }
);

// Consolidation Logic
watch(() => form.consolidation_basis, (newBasis) => {
    if (props.consolidation_data) {
        if (newBasis === 'balance') {
            form.principal_initial = props.consolidation_data.total_balance;
        } else {
            form.principal_initial = props.consolidation_data.total_principal;
        }
    }
}, { immediate: true });

// Watchers to trigger calculation preview
watch(
    () => [
        form.calculation_strategy,
        form.principal_initial,
        form.monthly_rate,
        form.modality,
        form.target_term_periods,
        form.installment_amount,
        form.start_date
    ],
    async () => {
        // Debounce or simple logic
        // If strategy is Quota: Need Principal, Rate, Modality, Installment
        if (form.calculation_strategy === 'quota' &&
            form.principal_initial && form.monthly_rate && form.installment_amount) {

            await calculateSchedule();
        }
        // If strategy is Term: Need Principal, Rate, Modality, Term
        else if (form.calculation_strategy === 'term' &&
             form.principal_initial && form.monthly_rate && form.target_term_periods) {

            // Local estimation or we could add an API for this too.
            // For now, let's keep the local estimation for Term->Quota
            // But clear the table if inputs change
            amortizationTable.value = [];
        } else {
             amortizationTable.value = [];
        }
    },
    { deep: true }
);

const calculateSchedule = async () => {
    isCalculating.value = true;
    calculationError.value = null;

    try {
        const response = await axios.post(route('loans.calculate-amortization'), {
            principal: form.principal_initial,
            monthly_rate: form.monthly_rate,
            modality: form.modality,
            installment_amount: form.installment_amount,
            start_date: form.start_date,
            interest_mode: form.interest_mode,
            days_in_month_convention: form.days_in_month_convention
        });

        if (response.data.error) {
            calculationError.value = response.data.error;
            amortizationTable.value = [];
        } else {
            amortizationTable.value = response.data;
        }
    } catch (e) {
        console.error(e);
        calculationError.value = "Error al calcular tabla.";
    } finally {
        isCalculating.value = false;
    }
};

const estimatedInstallmentFromTerm = computed(() => {
    if (form.calculation_strategy !== 'term') return 0;
    if (!form.principal_initial || !form.monthly_rate || !form.target_term_periods) return 0;

    const principal = parseFloat(form.principal_initial);
    const rate = parseFloat(form.monthly_rate);
    const daysInMonth = parseInt(form.days_in_month_convention);

    let daysInPeriod = 30;
    if (form.modality === 'daily') daysInPeriod = 1;
    if (form.modality === 'weekly') daysInPeriod = 7;
    if (form.modality === 'biweekly') daysInPeriod = 15;
    if (form.modality === 'monthly') daysInPeriod = daysInMonth;

    const dailyRate = (rate / 100) / daysInMonth;
    const interest = principal * dailyRate * daysInPeriod;
    const amortization = principal / parseInt(form.target_term_periods);

    return (interest + amortization).toFixed(2);
});


// Helper to get local date string YYYY-MM-DD
const getTodayString = () => {
    const d = new Date();
    return d.getFullYear() + '-' +
        String(d.getMonth() + 1).padStart(2, '0') + '-' +
        String(d.getDate()).padStart(2, '0');
};

// Historical Payments Logic
const showHistoricalPayments = computed(() => {
    if (!form.start_date) return false;
    // Simple string comparison YYYY-MM-DD
    // If consolidating, we might restrict historical payments or treat them normally?
    // User didn't specify, but consolidation is usually "starting now" based on old loans.
    // If consolidation date is retroactive, we might allow payments.
    return form.start_date < getTodayString();
});

const newPayment = ref({
    date: '',
    amount: '',
    method: 'cash',
    reference: '',
    notes: ''
});

const addHistoricalPayment = () => {
    if (!newPayment.value.date || !newPayment.value.amount) return;

    const today = getTodayString();

    if (newPayment.value.date < form.start_date) {
        alert('La fecha del pago no puede ser anterior a la fecha de inicio del préstamo.');
        return;
    }
    if (newPayment.value.date > today) {
        alert('La fecha del pago no puede ser futura.');
        return;
    }

    form.historical_payments.push({ ...newPayment.value });

    // Sort by date
    form.historical_payments.sort((a, b) => new Date(a.date) - new Date(b.date));

    // Reset temporary form
    newPayment.value = {
        date: '',
        amount: '',
        method: 'cash',
        reference: '',
        notes: ''
    };
};

const removePayment = (index) => {
    form.historical_payments.splice(index, 1);
};

const submit = () => {
    // Clear non-relevant fields based on strategy
    if (form.calculation_strategy === 'quota') {
        form.target_term_periods = null;
    } else {
        form.installment_amount = null;
    }

    form.post(route('loans.store'));
};

const showClientModal = ref(false);

const onClientCreated = (newClient) => {
    router.reload({
        only: ['clients'],
        onSuccess: () => {
            form.client_id = newClient.id;
        }
    });
};

const goBack = () => {
    window.history.back();
};

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
</script>

<template>
    <Head title="Nuevo Préstamo" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" @click="goBack" class="p-2 h-10 w-10 rounded-full hover:bg-slate-100 text-slate-500">
                    <i class="fa-solid fa-arrow-left"></i>
                </Button>
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                    {{ consolidation_data ? 'Unificar Préstamos' : 'Crear Nuevo Préstamo' }}
                </h2>
            </div>
        </template>

        <div class="py-6 space-y-6">
            <!-- Error Banner -->
            <div v-if="Object.keys($page.props.errors).length > 0" class="max-w-4xl mx-auto bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                <div class="flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation text-red-600 mt-1"></i>
                    <div>
                        <h4 class="font-bold text-red-800">Error en el formulario</h4>
                        <ul class="text-sm text-red-600 list-disc list-inside mt-1">
                            <li v-for="(error, key) in $page.props.errors" :key="key">{{ error }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Consolidation Alert -->
            <div v-if="consolidation_data" class="max-w-4xl mx-auto bg-purple-50 border border-purple-200 rounded-xl p-6 mb-4 animate-in fade-in slide-in-from-top-4">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 flex-shrink-0">
                        <i class="fa-solid fa-link text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-purple-900">Modo de Unificación de Deuda</h3>
                        <p class="text-purple-700 text-sm mt-1 mb-3">
                            Está creando un nuevo préstamo para unificar <strong>{{ consolidation_data.loans.length }}</strong> préstamos activos.
                            Los préstamos originales serán cancelados automáticamente.
                        </p>
                        <div class="flex gap-4 flex-wrap">
                            <span v-for="l in consolidation_data.loans" :key="l.id" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                {{ l.code }} ({{ formatCurrency(l.balance_total) }})
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Card -->
            <div class="max-w-4xl mx-auto">
                <Card class="rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <h3 class="font-bold text-lg text-slate-800">Configuración del Préstamo</h3>
                        <p class="text-sm text-slate-500">Complete los detalles para registrar la operación.</p>
                    </div>
                    <CardContent class="p-8">
                        <form @submit.prevent="submit" class="space-y-8">

                            <!-- Client -->
                            <div class="grid grid-cols-1 gap-6">
                                <div class="space-y-2">
                                    <Label for="client_id">Cliente <span class="text-red-500">*</span></Label>
                                    <div class="flex gap-2">
                                        <div class="relative flex-1">
                                            <select id="client_id" v-model="form.client_id" required :disabled="!!consolidation_data" class="flex h-12 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-blue-500 appearance-none disabled:bg-slate-100">
                                                <option value="" disabled>Seleccionar Cliente</option>
                                                <option v-for="client in clients" :key="client.id" :value="client.id">
                                                    {{ client.first_name }} {{ client.last_name }} ({{ client.national_id }})
                                                </option>
                                            </select>
                                            <i v-if="!consolidation_data" class="fa-solid fa-chevron-down absolute right-4 top-4 text-slate-400 pointer-events-none text-xs"></i>
                                        </div>
                                        <Button v-if="!consolidation_data" type="button" @click="showClientModal = true" class="h-12 px-4 rounded-xl flex-shrink-0 bg-blue-50 text-blue-600 border border-blue-100 hover:bg-blue-100 transition-colors shadow-sm font-medium">
                                            <i class="fa-solid fa-user-plus mr-2"></i> Nuevo
                                        </Button>
                                    </div>
                                    <span v-if="form.errors.client_id" class="text-sm text-red-500">{{ form.errors.client_id }}</span>
                                </div>
                            </div>

                            <div class="h-px bg-slate-100"></div>

                            <!-- Consolidation Basis Selection -->
                            <div v-if="consolidation_data" class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <Label class="mb-3 block text-slate-800 font-semibold">Base del Nuevo Capital</Label>
                                <RadioGroup v-model="form.consolidation_basis" class="flex flex-col space-y-3">
                                    <div class="flex items-center space-x-3 bg-white p-3 rounded-lg border border-slate-200 hover:border-blue-300 transition-colors cursor-pointer" @click="form.consolidation_basis = 'balance'">
                                        <RadioGroupItem id="opt-balance" value="balance" />
                                        <Label for="opt-balance" class="flex-1 cursor-pointer">
                                            <div class="font-bold text-slate-700">Balance Total ({{ formatCurrency(consolidation_data.total_balance) }})</div>
                                            <div class="text-xs text-slate-500">Incluye capital pendiente, intereses acumulados y mora.</div>
                                        </Label>
                                    </div>
                                    <div class="flex items-center space-x-3 bg-white p-3 rounded-lg border border-slate-200 hover:border-blue-300 transition-colors cursor-pointer" @click="form.consolidation_basis = 'principal'">
                                        <RadioGroupItem id="opt-principal" value="principal" />
                                        <Label for="opt-principal" class="flex-1 cursor-pointer">
                                            <div class="font-bold text-slate-700">Solo Capital ({{ formatCurrency(consolidation_data.total_principal) }})</div>
                                            <div class="text-xs text-slate-500">Se condonan los intereses acumulados de los préstamos anteriores.</div>
                                        </Label>
                                    </div>
                                </RadioGroup>
                            </div>

                            <!-- Amounts & Dates -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label for="start_date">Fecha Inicio <span class="text-red-500">*</span></Label>
                                    <Input id="start_date" type="date" :min="consolidation_data?.min_start_date" :max="getTodayDatetimeString()" v-model="form.start_date" required />
                                    <p v-if="consolidation_data" class="text-xs text-slate-500">Debe ser posterior a {{ formatDate(consolidation_data.min_start_date) }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="principal_initial">Monto Principal <span class="text-red-500">*</span></Label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-3.5 text-slate-400 font-bold">$</span>
                                        <Input id="principal_initial" type="number" step="0.01" v-model="form.principal_initial" required :readonly="!!consolidation_data" :class="{'bg-slate-100': !!consolidation_data}" class="pl-8 font-bold text-lg text-slate-800" placeholder="0.00" />
                                    </div>
                                </div>
                            </div>

                            <!-- Rates & Modality -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="space-y-2">
                                    <Label for="modality">Modalidad</Label>
                                    <div class="relative">
                                        <select id="modality" v-model="form.modality" class="flex h-12 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-blue-500 appearance-none">
                                            <option value="daily">Diario</option>
                                            <option value="weekly">Semanal</option>
                                            <option value="biweekly">Quincenal</option>
                                            <option value="monthly">Mensual</option>
                                        </select>
                                        <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-slate-400 pointer-events-none text-xs"></i>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="monthly_rate">Tasa Mensual (%)</Label>
                                    <div class="relative">
                                        <Input id="monthly_rate" type="number" step="0.01" v-model="form.monthly_rate" required class="pr-8" />
                                        <span class="absolute right-4 top-3.5 text-slate-400 font-bold">%</span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <Label for="interest_mode">Tipo Interés</Label>
                                    <div class="relative">
                                        <select id="interest_mode" v-model="form.interest_mode" class="flex h-12 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-blue-500 appearance-none">
                                            <option value="simple">Simple</option>
                                            <option value="compound">Compuesto</option>
                                        </select>
                                        <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-slate-400 pointer-events-none text-xs"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="h-px bg-slate-100"></div>

                            <!-- Calculation Strategy Toggle -->
                            <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                                <Label class="mb-3 block text-blue-800 font-semibold">Método de Cálculo</Label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.calculation_strategy" value="quota" class="w-4 h-4 text-blue-600 focus:ring-blue-500" />
                                        <span class="text-sm font-medium text-slate-700">Fijar Monto Cuota (Calcular Plazo)</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" v-model="form.calculation_strategy" value="term" class="w-4 h-4 text-blue-600 focus:ring-blue-500" />
                                        <span class="text-sm font-medium text-slate-700">Fijar Cantidad Cuotas (Calcular Monto)</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Dynamic Inputs based on Strategy -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Option A: Input Quota -->
                                <div v-if="form.calculation_strategy === 'quota'" class="space-y-2">
                                    <Label for="installment_amount">Monto Cuota Fija <span class="text-red-500">*</span></Label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-3.5 text-slate-400 font-bold">$</span>
                                        <Input id="installment_amount" type="number" step="0.01" v-model="form.installment_amount" placeholder="Ej: 5000.00" class="pl-8 text-lg font-bold text-blue-700" />
                                    </div>
                                    <p class="text-xs text-slate-500">Ingrese cuánto pagará el cliente y calcularemos el tiempo.</p>
                                </div>

                                <!-- Option B: Input Term -->
                                <div v-if="form.calculation_strategy === 'term'" class="space-y-2">
                                    <Label for="target_term_periods">Cantidad de Cuotas <span class="text-red-500">*</span></Label>
                                    <Input id="target_term_periods" type="number" v-model="form.target_term_periods" placeholder="Ej: 12" />
                                </div>

                                <!-- Result Display -->
                                <div class="space-y-2">
                                    <Label>Resultado Estimado</Label>
                                    <div class="h-12 flex items-center px-4 bg-emerald-50 border border-emerald-100 rounded-xl text-emerald-700 font-bold text-lg">
                                        <span v-if="form.calculation_strategy === 'term'">
                                            Cuota: RD$ {{ estimatedInstallmentFromTerm }}
                                        </span>
                                        <span v-else-if="amortizationTable.length > 0">
                                            Plazo: {{ amortizationTable.length }} Cuotas
                                        </span>
                                        <span v-else-if="calculationError" class="text-red-500 text-sm">
                                            {{ calculationError }}
                                        </span>
                                        <span v-else class="text-gray-400 text-sm font-normal">
                                            Esperando datos...
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Amortization Table Preview -->
                            <div v-if="form.calculation_strategy === 'quota' && amortizationTable.length > 0" class="border rounded-xl overflow-hidden mt-4">
                                <div class="bg-slate-50 p-3 border-b text-xs font-bold text-slate-500 uppercase">Tabla de Amortización Proyectada</div>
                                <div class="max-h-60 overflow-y-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead class="h-8 text-xs">#</TableHead>
                                                <TableHead class="h-8 text-xs">Fecha</TableHead>
                                                <TableHead class="h-8 text-xs text-right">Cuota</TableHead>
                                                <TableHead class="h-8 text-xs text-right">Interés</TableHead>
                                                <TableHead class="h-8 text-xs text-right">Capital</TableHead>
                                                <TableHead class="h-8 text-xs text-right">Balance</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-for="row in amortizationTable" :key="row.period" class="hover:bg-slate-50">
                                                <TableCell class="py-1 text-xs">{{ row.period }}</TableCell>
                                                <TableCell class="py-1 text-xs">{{ row.date }}</TableCell>
                                                <TableCell class="py-1 text-xs text-right">{{ formatCurrency(row.installment) }}</TableCell>
                                                <TableCell class="py-1 text-xs text-right text-slate-500">{{ formatCurrency(row.interest) }}</TableCell>
                                                <TableCell class="py-1 text-xs text-right text-emerald-600 font-medium">{{ formatCurrency(row.principal) }}</TableCell>
                                                <TableCell class="py-1 text-xs text-right font-bold">{{ formatCurrency(row.balance) }}</TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="notes">Notas</Label>
                                <Input id="notes" v-model="form.notes" placeholder="Detalles adicionales del préstamo..." />
                            </div>

                            <!-- Historical Payments Section -->
                            <div v-if="showHistoricalPayments" class="border border-slate-200 rounded-2xl bg-slate-50 overflow-hidden">
                                <div class="p-4 border-b border-slate-200 bg-slate-100/50">
                                    <h3 class="font-bold text-slate-800 flex items-center">
                                        <i class="fa-solid fa-clock-rotate-left mr-2 text-slate-500"></i> Pagos Históricos (Retroactivos)
                                    </h3>
                                    <p class="text-sm text-slate-500 mt-1">
                                        La fecha de inicio es anterior a hoy. Registre pagos ocurridos antes de hoy.
                                    </p>
                                </div>

                                <div class="p-4 space-y-4">
                                    <!-- Add Payment Form -->
                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                        <div class="md:col-span-3">
                                            <Label class="text-xs mb-1 block">Fecha</Label>
                                            <Input type="date" v-model="newPayment.date" :min="form.start_date" :max="getTodayString()" class="bg-white h-10 py-2" />
                                        </div>
                                        <div class="md:col-span-3">
                                            <Label class="text-xs mb-1 block">Monto</Label>
                                            <Input type="number" step="0.01" v-model="newPayment.amount" placeholder="0.00" class="bg-white h-10 py-2" />
                                        </div>
                                        <div class="md:col-span-2">
                                            <Label class="text-xs mb-1 block">Método</Label>
                                            <select v-model="newPayment.method" class="flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                                                <option value="cash">Efectivo</option>
                                                <option value="transfer">Transferencia</option>
                                            </select>
                                        </div>
                                        <div class="md:col-span-2">
                                             <Label class="text-xs mb-1 block">Ref/Nota</Label>
                                             <Input type="text" v-model="newPayment.reference" placeholder="Ref" class="bg-white h-10 py-2" />
                                        </div>
                                        <div class="md:col-span-2">
                                            <Button type="button" @click="addHistoricalPayment" variant="secondary" class="w-full h-10 bg-slate-200 hover:bg-slate-300 text-slate-800">
                                                <i class="fa-solid fa-plus mr-1"></i> Agregar
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- List -->
                                    <div v-if="form.historical_payments.length > 0" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                                        <Table>
                                            <TableHeader class="bg-slate-50">
                                                <TableRow>
                                                    <TableHead class="py-2 h-8 text-xs">Fecha</TableHead>
                                                    <TableHead class="py-2 h-8 text-xs">Monto</TableHead>
                                                    <TableHead class="py-2 h-8 text-xs">Método</TableHead>
                                                    <TableHead class="py-2 h-8 w-10"></TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                <TableRow v-for="(payment, index) in form.historical_payments" :key="index" class="hover:bg-slate-50">
                                                    <TableCell class="py-2 h-10">{{ payment.date }}</TableCell>
                                                    <TableCell class="py-2 h-10 font-bold text-slate-700">{{ payment.amount }}</TableCell>
                                                    <TableCell class="py-2 h-10 capitalize">{{ payment.method }}</TableCell>
                                                    <TableCell class="py-2 h-10 text-right">
                                                        <button type="button" @click="removePayment(index)" class="text-red-400 hover:text-red-600">
                                                            <i class="fa-solid fa-trash-can"></i>
                                                        </button>
                                                    </TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6">
                                <Button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-200 rounded-xl px-8 h-12 text-base font-medium transition-all hover:scale-105">
                                    <i class="fa-solid fa-check mr-2"></i>
                                    {{ consolidation_data ? 'Confirmar Unificación' : 'Crear Préstamo' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>

        <ClientModalForm v-if="showClientModal" @close="showClientModal = false" @success="onClientCreated" />
    </AuthenticatedLayout>
</template>
