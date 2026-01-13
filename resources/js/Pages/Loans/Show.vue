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
    return new Date(dateString).toLocaleDateString('es-DO');
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
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle de Préstamo</h2>
                <div class="space-x-2">
                    <Button v-if="loan.status === 'active'" @click="showPaymentModal = true">Registrar Pago</Button>
                    <!-- Add Refinance Button Here later -->
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">Balance Total</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ formatCurrency(loan.balance_total) }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">Capital Pendiente</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ formatCurrency(loan.principal_outstanding) }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">Interés Acumulado</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ formatCurrency(loan.interest_accrued) }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm font-medium">Cuota Fija</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ formatCurrency(loan.installment_amount) }}</div>
                            <p class="text-xs text-muted-foreground capitalize">{{ loan.modality }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Loan Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Información General</CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <div class="text-sm font-medium">Cliente</div>
                            <div class="text-lg">
                                <Link :href="route('clients.show', loan.client.id)" class="text-blue-600 hover:underline">
                                    {{ loan.client.first_name }} {{ loan.client.last_name }}
                                </Link>
                            </div>
                            <div class="text-sm text-gray-500">{{ loan.client.national_id }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Estado</div>
                            <Badge :variant="loan.status === 'active' ? 'default' : 'secondary'">
                                {{ loan.status }}
                            </Badge>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Código</div>
                            <div>{{ loan.code }}</div>
                        </div>

                        <div>
                            <div class="text-sm font-medium">Fecha Emisión</div>
                            <div>{{ formatDate(loan.start_date) }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Fecha Vencimiento</div>
                            <div :class="{'text-red-500': loan.maturity_date && new Date(loan.maturity_date) < new Date() && loan.status === 'active'}">
                                {{ formatDate(loan.maturity_date) }}
                            </div>
                        </div>
                         <div>
                            <div class="text-sm font-medium">Cantidad de Cuotas</div>
                            <div>{{ loan.target_term_periods ? loan.target_term_periods : 'Indefinido' }}</div>
                        </div>

                        <div>
                            <div class="text-sm font-medium">Tasa Mensual</div>
                            <div>{{ loan.monthly_rate }}% ({{ loan.interest_mode }})</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Modalidad</div>
                            <div class="capitalize">{{ loan.modality }}</div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Ledger -->
                <Card>
                    <CardHeader>
                        <CardTitle>Historial de Transacciones (Ledger)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead>Monto</TableHead>
                                    <TableHead>Capital Δ</TableHead>
                                    <TableHead>Interés Δ</TableHead>
                                    <TableHead>Balance</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="entry in loan.ledger_entries" :key="entry.id">
                                    <TableCell>{{ formatDate(entry.occurred_at) }}</TableCell>
                                    <TableCell class="capitalize">{{ entry.type.replace('_', ' ') }}</TableCell>
                                    <TableCell>{{ formatCurrency(entry.amount) }}</TableCell>
                                    <TableCell :class="entry.principal_delta < 0 ? 'text-green-600' : ''">
                                        {{ formatCurrency(entry.principal_delta) }}
                                    </TableCell>
                                    <TableCell :class="entry.interest_delta < 0 ? 'text-green-600' : ''">
                                        {{ formatCurrency(entry.interest_delta) }}
                                    </TableCell>
                                    <TableCell class="font-bold">{{ formatCurrency(entry.balance_after) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Payment Modal (Simple HTML overlay for now since Dialog component was skipped) -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
                <h3 class="text-lg font-bold mb-4">Registrar Pago</h3>
                <form @submit.prevent="submitPayment" class="space-y-4">
                    <div>
                        <Label>Fecha Pago</Label>
                        <div class="text-sm">{{ new Date().toLocaleDateString('es-DO') }}</div>
                    </div>
                    <div>
                        <Label for="amount">Monto</Label>
                        <input id="amount" type="number" step="0.01" v-model="paymentForm.amount" class="flex h-10 w-full rounded-md border border-input px-3 py-2 text-sm" required autofocus />
                    </div>
                    <div>
                        <Label for="method">Método</Label>
                        <select id="method" v-model="paymentForm.method" class="flex h-10 w-full rounded-md border border-input px-3 py-2 text-sm">
                            <option value="cash">Efectivo</option>
                            <option value="transfer">Transferencia</option>
                        </select>
                    </div>
                     <div>
                        <Label for="reference">Referencia</Label>
                        <input id="reference" v-model="paymentForm.reference" class="flex h-10 w-full rounded-md border border-input px-3 py-2 text-sm" />
                    </div>
                    <div class="flex justify-end space-x-2 pt-4">
                        <Button type="button" variant="ghost" @click="showPaymentModal = false">Cancelar</Button>
                        <Button type="submit" :disabled="paymentForm.processing">Confirmar</Button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
