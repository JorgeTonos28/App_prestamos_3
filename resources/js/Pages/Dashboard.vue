<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Card } from '@/Components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/Components/ui/tooltip';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { ref, watch } from 'vue';

// Helper to format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const parts = dateString.split('T')[0].split('-');
    if (parts.length === 3) {
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        return date.toLocaleDateString('es-DO');
    }
    return dateString;
};

const props = defineProps({
    stats: Object,
    recent_loans: Array,
    filters: Object,
});

const startDate = ref(props.filters?.start_date || '');
const endDate = ref(props.filters?.end_date || '');
const syncingFromServer = ref(false);

const toIsoDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const applyFilters = () => {
    if (syncingFromServer.value) return;

    router.get(route('dashboard'), {
        start_date: startDate.value,
        end_date: endDate.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

watch(
    () => [props.filters?.start_date, props.filters?.end_date],
    ([nextStart, nextEnd]) => {
        const normalizedStart = nextStart || '';
        const normalizedEnd = nextEnd || '';

        if (startDate.value === normalizedStart && endDate.value === normalizedEnd) {
            return;
        }

        syncingFromServer.value = true;
        startDate.value = normalizedStart;
        endDate.value = normalizedEnd;
        queueMicrotask(() => {
            syncingFromServer.value = false;
        });
    }
);

watch(startDate, applyFilters);
watch(endDate, applyFilters);

const setRangeByPreset = (preset) => {
    const end = new Date();
    const start = new Date(end);

    if (preset === 'month') {
        start.setMonth(start.getMonth() - 1);
    } else if (preset === 'quarter') {
        start.setMonth(start.getMonth() - 3);
    } else if (preset === 'semester') {
        start.setMonth(start.getMonth() - 6);
    } else if (preset === 'year') {
        start.setFullYear(start.getFullYear() - 1);
    }

    startDate.value = toIsoDate(start);
    endDate.value = toIsoDate(end);
};
</script>

<template>
    <Head title="Panel Principal" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-surface-800 leading-tight">Panel Principal</h2>
        </template>

        <div class="py-6 space-y-6">
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-surface-100 flex flex-col md:flex-row gap-4 md:items-end">
                <div class="w-full md:w-56">
                    <label for="start_date" class="text-xs font-semibold text-surface-500 uppercase mb-1 block pl-1">Fecha inicio</label>
                    <Input id="start_date" type="date" v-model="startDate" class="h-10 rounded-xl border-surface-200" />
                </div>
                <div class="w-full md:w-56">
                    <label for="end_date" class="text-xs font-semibold text-surface-500 uppercase mb-1 block pl-1">Fecha término</label>
                    <Input id="end_date" type="date" v-model="endDate" class="h-10 rounded-xl border-surface-200" />
                </div>
                <div class="w-full md:flex-1 md:max-w-xl">
                    <label class="text-xs font-semibold text-surface-500 uppercase mb-2 block text-center">Filtrar por último:</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <Button type="button" variant="outline" class="rounded-xl" @click="setRangeByPreset('month')">Mes</Button>
                        <Button type="button" variant="outline" class="rounded-xl" @click="setRangeByPreset('quarter')">Trimestre</Button>
                        <Button type="button" variant="outline" class="rounded-xl" @click="setRangeByPreset('semester')">Semestre</Button>
                        <Button type="button" variant="outline" class="rounded-xl" @click="setRangeByPreset('year')">Año</Button>
                    </div>
                </div>
            </div>
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6 gap-6">
                <!-- Active Loans -->
                <Card class="relative bg-white/70 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6 flex flex-col justify-between group hover:scale-[1.02] transition-transform duration-300 min-w-[200px]">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-primary-100/50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-primary-200/50 transition-colors pointer-events-none"></div>
                    <div class="relative flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-primary-500/30">
                            <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip :delay-duration="0">
                                <TooltipTrigger asChild>
                                    <button class="text-surface-400 hover:text-primary-600 transition-colors z-20 relative">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-surface-800 text-white border-surface-700 z-50">
                                    <p class="text-xs w-64">Total de préstamos que se encuentran activos y en curso actualmente.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div class="relative">
                        <p class="text-sm font-medium text-surface-500 mb-1">Préstamos Activos</p>
                        <h3 class="text-3xl font-extrabold text-surface-800 tracking-tight">{{ stats.active_loans_count }}</h3>
                    </div>
                </Card>

                <!-- Portfolio Value (Capital en Riesgo) -->
                <Card class="relative bg-white/70 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6 flex flex-col justify-between group hover:scale-[1.02] transition-transform duration-300 min-w-[200px]">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-info-100/50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-info-200/50 transition-colors pointer-events-none"></div>
                    <div class="relative flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-info-500 to-info-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-info-500/30">
                            <i class="fa-solid fa-sack-dollar text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip :delay-duration="0">
                                <TooltipTrigger asChild>
                                    <button class="text-surface-400 hover:text-info-600 transition-colors z-20 relative">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-surface-800 text-white border-surface-700 z-50">
                                    <p class="text-xs w-64">Monto total del capital pendiente de cobro en todos los préstamos activos.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div class="relative">
                        <p class="text-sm font-medium text-surface-500 mb-1">Capital en Riesgo</p>
                        <h3 class="text-2xl font-extrabold text-surface-800 tracking-tight">{{ formatCurrency(stats.portfolio_principal) }}</h3>
                    </div>
                </Card>

                <!-- Interest Earnings -->
                <Card class="relative bg-white/70 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6 flex flex-col justify-between group hover:scale-[1.02] transition-transform duration-300 min-w-[200px]">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-success-100/50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-success-200/50 transition-colors pointer-events-none"></div>
                    <div class="relative flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-success-500 to-success-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-success-500/30">
                            <i class="fa-solid fa-chart-line text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip :delay-duration="0">
                                <TooltipTrigger asChild>
                                    <button class="text-surface-400 hover:text-success-600 transition-colors z-20 relative">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-surface-800 text-white border-surface-700 z-50">
                                    <p class="text-xs w-64">Total de intereses ganados y cobrados efectivamente dentro del rango de fechas seleccionado.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div class="relative">
                        <p class="text-sm font-medium text-surface-500 mb-1">Ingresos por Interés (Mes)</p>
                        <h3 class="text-2xl font-extrabold text-success-600 tracking-tight">+{{ formatCurrency(stats.interest_earnings_month) }}</h3>
                    </div>
                </Card>

                <!-- Principal Recovered -->
                <Card class="relative bg-white/70 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6 flex flex-col justify-between group hover:scale-[1.02] transition-transform duration-300 min-w-[200px]">
                     <div class="absolute top-0 right-0 w-32 h-32 bg-success-100/50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-success-200/50 transition-colors pointer-events-none"></div>
                    <div class="relative flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-success-500 to-success-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-success-500/30">
                            <i class="fa-solid fa-arrow-rotate-left text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip :delay-duration="0">
                                <TooltipTrigger asChild>
                                    <button class="text-surface-400 hover:text-success-600 transition-colors z-20 relative">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-surface-800 text-white border-surface-700 z-50">
                                    <p class="text-xs w-64">Monto de capital que ha retornado a la caja mediante pagos dentro del rango de fechas seleccionado.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div class="relative">
                        <p class="text-sm font-medium text-surface-500 mb-1">Capital Recuperado</p>
                        <h3 class="text-2xl font-extrabold text-success-600 tracking-tight">+{{ formatCurrency(stats.principal_recovered_month) }}</h3>
                    </div>
                </Card>

                <!-- Arrears -->
                <Card class="relative bg-white/70 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6 flex flex-col justify-between group hover:scale-[1.02] transition-transform duration-300 min-w-[200px]">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-danger-100/50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-danger-200/50 transition-colors pointer-events-none"></div>
                    <div class="relative flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-danger-500 to-danger-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-danger-500/30">
                            <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip :delay-duration="0">
                                <TooltipTrigger asChild>
                                    <button class="text-surface-400 hover:text-danger-600 transition-colors z-20 relative">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-surface-800 text-white border-surface-700 z-50">
                                    <p class="text-xs w-64">Porcentaje de préstamos que presentan cuotas vencidas o atrasos en el pago.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div class="relative">
                        <p class="text-sm font-medium text-surface-500 mb-1">Tasa de Mora</p>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-3xl font-extrabold text-surface-800 tracking-tight">{{ stats.arrears_rate }}%</h3>
                            <span class="text-xs font-bold text-danger-600 bg-danger-100 px-2 py-0.5 rounded-full">
                                {{ stats.loans_in_arrears_count }}
                            </span>
                        </div>
                    </div>
                </Card>

                <!-- Legal Fees Month -->
                <Card class="relative bg-white/70 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6 flex flex-col justify-between group hover:scale-[1.02] transition-transform duration-300 min-w-[200px]">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-success-100/50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-success-200/50 transition-colors pointer-events-none"></div>
                    <div class="relative flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-success-500 to-success-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-success-500/30">
                            <i class="fa-solid fa-file-signature text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip :delay-duration="0">
                                <TooltipTrigger asChild>
                                    <button class="text-surface-400 hover:text-success-600 transition-colors z-20 relative">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-surface-800 text-white border-surface-700 z-50">
                                    <p class="text-xs w-64">Total generado por gastos legales en préstamos creados dentro del rango de fechas seleccionado.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div class="relative">
                        <p class="text-sm font-medium text-surface-500 mb-1">Gastos Legales (Rango)</p>
                        <h3 class="text-2xl font-extrabold text-success-600 tracking-tight">+{{ formatCurrency(stats.legal_fees_month) }}</h3>
                    </div>
                </Card>

                <!-- Cash Income Month -->
                <Card class="relative bg-white/70 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6 flex flex-col justify-between group hover:scale-[1.02] transition-transform duration-300 min-w-[200px]">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-warning-100/50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-warning-200/50 transition-colors pointer-events-none"></div>
                    <div class="relative flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-warning-500 to-warning-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-warning-500/30">
                            <i class="fa-solid fa-money-bill-wave text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip :delay-duration="0">
                                <TooltipTrigger asChild>
                                    <button class="text-surface-400 hover:text-warning-600 transition-colors z-20 relative">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-surface-800 text-white border-surface-700 z-50">
                                    <p class="text-xs w-64">Monto total recibido en efectivo dentro del rango de fechas seleccionado.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div class="relative">
                        <p class="text-sm font-medium text-surface-500 mb-1">Ingresos en Efectivo (Rango)</p>
                        <h3 class="text-2xl font-extrabold text-warning-600 tracking-tight">+{{ formatCurrency(stats.cash_income_month) }}</h3>
                    </div>
                </Card>

                <!-- Bank Income Month -->
                <Card class="relative bg-white/70 backdrop-blur-xl rounded-2xl shadow-lg border border-white/20 p-6 flex flex-col justify-between group hover:scale-[1.02] transition-transform duration-300 min-w-[200px]">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-cyan-100/50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-cyan-200/50 transition-colors pointer-events-none"></div>
                    <div class="relative flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-cyan-500/30">
                            <i class="fa-solid fa-building-columns text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip :delay-duration="0">
                                <TooltipTrigger asChild>
                                    <button class="text-surface-400 hover:text-cyan-600 transition-colors z-20 relative">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-surface-800 text-white border-surface-700 z-50">
                                    <p class="text-xs w-64">Monto total recibido por banco (transferencias/tarjeta) dentro del rango de fechas seleccionado.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div class="relative">
                        <p class="text-sm font-medium text-surface-500 mb-1">Ingresos en Banco (Rango)</p>
                        <h3 class="text-2xl font-extrabold text-cyan-600 tracking-tight">+{{ formatCurrency(stats.bank_income_month) }}</h3>
                    </div>
                </Card>
            </div>

            <!-- Recent Activity & Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recent Loans Table (Restored) -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-surface-100 overflow-hidden">
                    <div class="p-6 border-b border-surface-100 bg-surface-50/50 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-surface-800">Préstamos Recientes</h3>
                        <Link :href="route('loans.index')" class="text-sm text-primary-600 hover:underline">Ver todos</Link>
                    </div>
                    <div class="p-0">
                         <Table>
                            <TableHeader class="bg-surface-50">
                                <TableRow>
                                    <TableHead class="pl-6">Código</TableHead>
                                    <TableHead>Cliente</TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead class="text-right pr-6">Monto</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="loan in recent_loans" :key="loan.id" class="hover:bg-surface-50">
                                    <TableCell class="font-medium pl-6">
                                        <Link :href="route('loans.show', loan.id)" class="hover:underline text-primary-600 font-mono">
                                            {{ loan.description.replace('Préstamo ', '') }}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{{ loan.client_name }}</TableCell>
                                    <TableCell>{{ formatDate(loan.date) }}</TableCell>
                                    <TableCell class="text-right font-bold pr-6">{{ formatCurrency(loan.amount) }}</TableCell>
                                </TableRow>
                                <TableRow v-if="!recent_loans || recent_loans.length === 0">
                                    <TableCell colspan="4" class="text-center text-muted-foreground py-8">
                                        No hay actividad reciente.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <!-- Quick Actions (Restored) -->
                <div class="bg-gradient-to-br from-primary-600 to-info-700 rounded-2xl shadow-lg text-white p-6 flex flex-col justify-between">
                    <div>
                        <h3 class="font-bold text-xl mb-2">Acciones Rápidas</h3>
                        <p class="text-primary-100 text-sm mb-6">Gestiona tu cartera de manera eficiente.</p>

                        <div class="space-y-3">
                            <Link :href="route('loans.create')" class="block w-full bg-white/10 hover:bg-white/20 transition-colors rounded-lg p-3 text-sm font-medium flex items-center">
                                <i class="fa-solid fa-plus mr-3"></i> Nuevo Préstamo
                            </Link>
                            <Link :href="route('clients.create')" class="block w-full bg-white/10 hover:bg-white/20 transition-colors rounded-lg p-3 text-sm font-medium flex items-center">
                                <i class="fa-solid fa-user-plus mr-3"></i> Registrar Cliente
                            </Link>
                             <Link :href="route('loans.index')" class="block w-full bg-white/10 hover:bg-white/20 transition-colors rounded-lg p-3 text-sm font-medium flex items-center">
                                <i class="fa-solid fa-list mr-3"></i> Ver Todos los Préstamos
                            </Link>
                        </div>
                    </div>
                    <div class="mt-8 pt-6 border-t border-white/20 text-xs text-primary-200">
                        <p>Tip: Mantén los registros de pago al día para obtener reportes precisos.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
