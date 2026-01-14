<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
import { ref } from 'vue';
import { Label } from '@/Components/ui/label';

const props = defineProps({
    loan: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    // Format: dd/mm/yyyy - hh:mm a
    const date = new Date(dateString);
    return date.toLocaleString('es-DO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    }).replace(',', ' -');
};

// Simple Modal Logic for Payment
const showPaymentModal = ref(false);
const paymentForm = useForm({
    amount: '',
    method: 'cash',
    reference: '',
    notes: ''
});

const submitPayment = () => {
    paymentForm.post(route('loans.payments.store', props.loan.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
        }
    });
};
</script>

<template>
    <Head :title="'Préstamo ' + loan.code" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" @click="() => window.history.back()" class="p-2 h-10 w-10 rounded-full hover:bg-slate-100 text-slate-500">
                        <i class="fa-solid fa-arrow-left"></i>
                    </Button>
                    <div>
                        <h2 class="font-bold text-2xl text-slate-800 leading-tight">Préstamo - {{ loan.client.first_name }} {{ loan.client.last_name }}</h2>
                        <p class="text-sm text-slate-500 font-medium">Detalle de Operación #{{ loan.code }}</p>
                    </div>
                </div>
                <div class="space-x-2">
                    <Button v-if="loan.status === 'active'" @click="showPaymentModal = true" class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow-md px-6 transition-all hover:scale-105">
                        <i class="fa-solid fa-money-bill-wave mr-2"></i> Registrar Pago
                    </Button>
                </div>
            </div>
        </template>

        <div class="py-6 space-y-8">
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
                </div>

                <!-- Ledger Table -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
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
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="entry in loan.ledger_entries" :key="entry.id" class="hover:bg-slate-50 transition-colors">
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
                                    <TableCell class="text-right font-bold text-slate-800 pr-6">{{ formatCurrency(entry.balance_after) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
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
                <form @submit.prevent="submitPayment" class="space-y-4">
                    <div>
                        <Label class="text-slate-600 mb-1 block">Fecha Pago</Label>
                        <div class="text-sm font-medium text-slate-800 bg-slate-50 p-2 rounded-lg border border-slate-200">
                            {{ new Date().toLocaleDateString('es-DO') }}
                        </div>
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
    </AuthenticatedLayout>
</template>
