<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { computed, ref } from 'vue';
import ClientModalForm from '@/Components/ClientModalForm.vue';

const props = defineProps({
    clients: Array,
    // Optional: Pre-select client if passed via query param
    client_id: String
});

const getTodayDatetimeString = () => {
    const d = new Date();
    // YYYY-MM-DDTHH:mm
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const form = useForm({
    client_id: props.client_id || '',
    code: 'LN-' + Math.floor(Math.random() * 100000), // Simple random code for now
    start_date: getTodayDatetimeString(),
    principal_initial: '',
    modality: 'monthly',
    monthly_rate: 5, // Default 5%
    days_in_month_convention: 30,
    interest_mode: 'simple',
    target_term_periods: '', // Optional
    notes: '',
    historical_payments: [] // Array of {date, amount, method, reference}
});

// Watch for calculation preview (simple client-side approximation)
const estimatedInstallment = computed(() => {
    if (!form.principal_initial || !form.monthly_rate) return 0;

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

    let amortization = 0;
    if (form.target_term_periods && form.target_term_periods > 0) {
        amortization = principal / parseInt(form.target_term_periods);
    }

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
    const start = new Date(form.start_date);
    const startYMD = form.start_date.split('T')[0];
    const todayYMD = getTodayString();

    return startYMD < todayYMD;
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

    // Validate date
    const today = getTodayString();
    const startYMD = form.start_date.split('T')[0];

    if (newPayment.value.date < startYMD) {
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
    form.post(route('loans.store'));
};

const showClientModal = ref(false);

const onClientCreated = (newClient) => {
    // Reload from server to ensure sync
    router.reload({
        only: ['clients'],
        onSuccess: () => {
            // Select the new client once list is updated
            form.client_id = newClient.id;
        }
    });
};

const goBack = () => {
    window.history.back();
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
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">Crear Nuevo Préstamo</h2>
            </div>
        </template>

        <div class="py-6 space-y-6">
            <!-- Main Card -->
            <div class="max-w-4xl mx-auto">
                <Card class="rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <h3 class="font-bold text-lg text-slate-800">Configuración del Préstamo</h3>
                        <p class="text-sm text-slate-500">Complete los detalles para registrar la operación.</p>
                    </div>
                    <CardContent class="p-8">
                        <form @submit.prevent="submit" class="space-y-8">

                            <!-- Client & Code -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label for="client_id">Cliente <span class="text-red-500">*</span></Label>
                                    <div class="flex gap-2">
                                        <div class="relative flex-1">
                                            <select id="client_id" v-model="form.client_id" required class="flex h-12 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm focus:border-blue-500 focus:ring-blue-500 appearance-none">
                                                <option value="" disabled>Seleccionar Cliente</option>
                                                <option v-for="client in clients" :key="client.id" :value="client.id">
                                                    {{ client.first_name }} {{ client.last_name }} ({{ client.national_id }})
                                                </option>
                                            </select>
                                            <i class="fa-solid fa-chevron-down absolute right-4 top-4 text-slate-400 pointer-events-none text-xs"></i>
                                        </div>
                                        <Button type="button" @click="showClientModal = true" class="h-12 px-4 rounded-xl flex-shrink-0 bg-blue-50 text-blue-600 border border-blue-100 hover:bg-blue-100 transition-colors shadow-sm font-medium">
                                            <i class="fa-solid fa-user-plus mr-2"></i> Nuevo
                                        </Button>
                                    </div>
                                    <span v-if="form.errors.client_id" class="text-sm text-red-500">{{ form.errors.client_id }}</span>
                                </div>
                                <div class="space-y-2">
                                    <Label for="code">Código de Préstamo <span class="text-red-500">*</span></Label>
                                    <div class="relative">
                                        <i class="fa-solid fa-barcode absolute left-4 top-4 text-slate-400"></i>
                                        <Input id="code" v-model="form.code" required class="pl-10 font-mono text-slate-700" />
                                    </div>
                                    <span v-if="form.errors.code" class="text-sm text-red-500">{{ form.errors.code }}</span>
                                </div>
                            </div>

                            <div class="h-px bg-slate-100"></div>

                            <!-- Amounts & Dates -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label for="start_date">Fecha Inicio <span class="text-red-500">*</span></Label>
                                    <Input id="start_date" type="datetime-local" v-model="form.start_date" required />
                                </div>
                                <div class="space-y-2">
                                    <Label for="principal_initial">Monto Principal <span class="text-red-500">*</span></Label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-3.5 text-slate-400 font-bold">$</span>
                                        <Input id="principal_initial" type="number" step="0.01" v-model="form.principal_initial" required class="pl-8 font-bold text-lg text-slate-800" placeholder="0.00" />
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

                            <!-- Advanced / Optional -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label for="target_term_periods">Plazo (Cuotas) <span class="text-slate-400 text-xs font-normal">(Opcional)</span></Label>
                                    <Input id="target_term_periods" type="number" v-model="form.target_term_periods" placeholder="Ej: 12" />
                                    <p class="text-xs text-slate-500">Dejar vacío para plazo indefinido (solo interés).</p>
                                </div>
                                <div class="space-y-2">
                                    <Label>Cuota Fija Estimada</Label>
                                    <div class="h-12 flex items-center px-4 bg-emerald-50 border border-emerald-100 rounded-xl text-emerald-700 font-bold text-lg">
                                        RD$ {{ estimatedInstallment }}
                                    </div>
                                    <p class="text-xs text-slate-500">Cálculo aproximado basado en la tasa y plazo.</p>
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
                                            <Input type="date" v-model="newPayment.date" :min="form.start_date.split('T')[0]" :max="getTodayString()" class="bg-white h-10 py-2" />
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
                                    <i class="fa-solid fa-check mr-2"></i> Crear Préstamo
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
