<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent } from '@/Components/ui/card';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/Components/ui/tooltip';

// Helper to format currency
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value);
};

defineProps({
    stats: Object
});
</script>

<template>
    <Head title="Panel Principal" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">Panel Principal</h2>
        </template>

        <div class="py-6 space-y-6">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Active Loans -->
                <Card class="rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 shadow-sm">
                            <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button class="text-slate-400 hover:text-slate-600 transition-colors">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-slate-800 text-white border-slate-700">
                                    <p class="text-xs w-48">Total de préstamos que se encuentran activos y en curso actualmente.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Préstamos Activos</p>
                        <h3 class="text-3xl font-bold text-slate-800">{{ stats.active_loans_count }}</h3>
                    </div>
                </Card>

                <!-- Portfolio Value (Capital en Riesgo) -->
                <Card class="rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-600 shadow-sm">
                            <i class="fa-solid fa-sack-dollar text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button class="text-slate-400 hover:text-slate-600 transition-colors">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-slate-800 text-white border-slate-700">
                                    <p class="text-xs w-48">Monto total del capital pendiente de cobro en todos los préstamos activos.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Capital en Riesgo</p>
                        <h3 class="text-2xl font-bold text-slate-800">{{ formatCurrency(stats.portfolio_principal) }}</h3>
                    </div>
                </Card>

                <!-- Interest Earnings -->
                <Card class="rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 shadow-sm">
                            <i class="fa-solid fa-chart-line text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button class="text-slate-400 hover:text-slate-600 transition-colors">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-slate-800 text-white border-slate-700">
                                    <p class="text-xs w-48">Total de intereses ganados y cobrados efectivamente durante este mes.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Ingresos por Interés (Mes)</p>
                        <h3 class="text-2xl font-bold text-emerald-600">+{{ formatCurrency(stats.interest_earnings_month) }}</h3>
                    </div>
                </Card>

                <!-- Arrears -->
                <Card class="rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-red-50 rounded-2xl flex items-center justify-center text-red-600 shadow-sm">
                            <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                        </div>
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button class="text-slate-400 hover:text-slate-600 transition-colors">
                                        <i class="fa-regular fa-circle-question"></i>
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent class="bg-slate-800 text-white border-slate-700">
                                    <p class="text-xs w-48">Porcentaje de préstamos que presentan cuotas vencidas o atrasos en el pago.</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Tasa de Mora</p>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-3xl font-bold text-slate-800">{{ stats.arrears_rate }}%</h3>
                            <span class="text-xs font-medium text-red-500 bg-red-50 px-2 py-0.5 rounded-full">
                                {{ stats.loans_in_arrears_count }} préstamos
                            </span>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- Secondary Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- New Loans -->
                <Card class="p-4 flex items-center gap-4 border border-slate-100 shadow-sm rounded-xl">
                    <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                        <i class="fa-solid fa-plus"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-semibold text-slate-500 uppercase">Nuevos Préstamos</p>
                        <p class="font-bold text-slate-800 text-lg">{{ stats.new_loans_month }} <span class="text-xs font-normal text-slate-400">este mes</span></p>
                    </div>
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <i class="fa-regular fa-circle-question text-slate-300 hover:text-slate-500 text-sm cursor-help"></i>
                            </TooltipTrigger>
                            <TooltipContent class="bg-slate-800 text-white border-slate-700">
                                <p class="text-xs w-40">Cantidad de nuevos créditos desembolsados en el mes actual.</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </Card>

                <!-- Principal Recovered -->
                <Card class="p-4 flex items-center gap-4 border border-slate-100 shadow-sm rounded-xl">
                    <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center text-green-600">
                        <i class="fa-solid fa-arrow-rotate-left"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-semibold text-slate-500 uppercase">Capital Recuperado</p>
                        <p class="font-bold text-slate-800 text-lg">{{ formatCurrency(stats.principal_recovered_month) }}</p>
                    </div>
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <i class="fa-regular fa-circle-question text-slate-300 hover:text-slate-500 text-sm cursor-help"></i>
                            </TooltipTrigger>
                            <TooltipContent class="bg-slate-800 text-white border-slate-700">
                                <p class="text-xs w-40">Monto de capital que ha retornado a la caja mediante pagos en este mes.</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </Card>

                <!-- Example Metric -->
                <Card class="p-4 flex items-center gap-4 border border-slate-100 shadow-sm rounded-xl">
                    <div class="w-10 h-10 rounded-full bg-orange-50 flex items-center justify-center text-orange-600">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-xs font-semibold text-slate-500 uppercase">Clientes Activos</p>
                        <p class="font-bold text-slate-800 text-lg">{{ stats.active_clients_count }}</p>
                    </div>
                     <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <i class="fa-regular fa-circle-question text-slate-300 hover:text-slate-500 text-sm cursor-help"></i>
                            </TooltipTrigger>
                            <TooltipContent class="bg-slate-800 text-white border-slate-700">
                                <p class="text-xs w-40">Número total de clientes que tienen al menos un préstamo activo.</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
