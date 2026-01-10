<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    clients: Array,
    // Optional: Pre-select client if passed via query param
    client_id: String
});

const form = useForm({
    client_id: '',
    code: 'LN-' + Math.floor(Math.random() * 100000), // Simple random code for now
    start_date: new Date().toISOString().split('T')[0],
    principal_initial: '',
    modality: 'monthly',
    monthly_rate: 5, // Default 5%
    days_in_month_convention: 30,
    interest_mode: 'simple',
    target_term_periods: '', // Optional
    notes: ''
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

const submit = () => {
    form.post(route('loans.store'));
};
</script>

<template>
    <Head title="Nuevo Préstamo" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear Préstamo</h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <CardTitle>Configuración del Préstamo</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-6">

                            <!-- Client & Code -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="client_id">Cliente</Label>
                                    <select id="client_id" v-model="form.client_id" required class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                        <option value="" disabled>Seleccionar Cliente</option>
                                        <option v-for="client in clients" :key="client.id" :value="client.id">
                                            {{ client.first_name }} {{ client.last_name }} ({{ client.national_id }})
                                        </option>
                                    </select>
                                    <span v-if="form.errors.client_id" class="text-sm text-red-500">{{ form.errors.client_id }}</span>
                                </div>
                                <div class="space-y-2">
                                    <Label for="code">Código de Préstamo</Label>
                                    <Input id="code" v-model="form.code" required />
                                    <span v-if="form.errors.code" class="text-sm text-red-500">{{ form.errors.code }}</span>
                                </div>
                            </div>

                            <!-- Amounts & Dates -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="start_date">Fecha Inicio</Label>
                                    <Input id="start_date" type="date" v-model="form.start_date" required />
                                </div>
                                <div class="space-y-2">
                                    <Label for="principal_initial">Monto Principal</Label>
                                    <Input id="principal_initial" type="number" step="0.01" v-model="form.principal_initial" required />
                                </div>
                            </div>

                            <!-- Rates & Modality -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="space-y-2">
                                    <Label for="modality">Modalidad</Label>
                                    <select id="modality" v-model="form.modality" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                        <option value="daily">Diario</option>
                                        <option value="weekly">Semanal</option>
                                        <option value="biweekly">Quincenal</option>
                                        <option value="monthly">Mensual</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <Label for="monthly_rate">Tasa Mensual (%)</Label>
                                    <Input id="monthly_rate" type="number" step="0.01" v-model="form.monthly_rate" required />
                                </div>
                                <div class="space-y-2">
                                    <Label for="interest_mode">Tipo Interés</Label>
                                    <select id="interest_mode" v-model="form.interest_mode" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                        <option value="simple">Simple</option>
                                        <option value="compound">Compuesto</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Advanced / Optional -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="target_term_periods">Plazo (Cuotas) - Opcional</Label>
                                    <Input id="target_term_periods" type="number" v-model="form.target_term_periods" placeholder="Ej: 12" />
                                    <p class="text-xs text-gray-500">Dejar vacío para solo interés.</p>
                                </div>
                                <div class="space-y-2">
                                    <Label>Cuota Estimada</Label>
                                    <div class="text-2xl font-bold text-green-600">
                                        RD$ {{ estimatedInstallment }}
                                    </div>
                                    <p class="text-xs text-gray-500">Calculado automáticamente.</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="notes">Notas</Label>
                                <Input id="notes" v-model="form.notes" />
                            </div>

                            <div class="flex justify-end pt-4">
                                <Button type="submit" :disabled="form.processing">
                                    Crear Préstamo
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
