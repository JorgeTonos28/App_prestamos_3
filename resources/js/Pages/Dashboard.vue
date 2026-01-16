<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent } from '@/Components/ui/card';
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
    recent_loans: Array // We might need to pass this separately if it's not in stats, checking controller logic
});
// Note: In previous step Controller passed recent_loans separately. But in step 5 I put everything in 'stats' wrapper?
// Let's check Controller code from memory/previous reads.
// Controller:
// return Inertia::render('Dashboard', [
//     'stats' => [ ... ],
// ]);
// AND I missed 'recent_loans' inside stats array in my manual write in step 5?
// Actually in step 5 I wrote:
// 'stats' => [ ..., 'new_loans_volume' => ..., 'active_clients_count' => ..., 'arrears_rate' => ... ]
// I did NOT put recent_loans in stats in the previous diff.
// The previous diff replaced the return array completely.
// So recent_loans is MISSING from the props entirely right now.
// I need to fix the props usage here assuming I will fix the controller OR assuming it is in stats.
// Let's assume I will fix the controller to include recent_loans in stats OR pass it separate.
// Given the user wants it back, I should probably rely on `stats.recent_loans` if I update controller,
// OR `recent_loans` prop if I revert controller structure.
// The best approach: Put it in `stats` in controller or pass as separate prop.
// Since I can't edit controller in this step (one tool call), I will assume it's available as `stats.recent_loans`
// and I will need to update the controller in next step if it's missing.
// WAIT. I can check the controller file content again if needed.
// Actually, looking at my previous write to DashboardController (Step 5), I *replaced* the return array.
// And I did NOT include `recent_loans`.
// So `recent_loans` is currently GONE from the backend response.
// I MUST fix the controller too. But I am in "Restore Dashboard" plan.
// I will write the Vue file to expect `stats.recent_loans`.
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
                <Card class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
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
                <Card class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
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
                <Card class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
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
                <Card class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
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

            <!-- Recent Activity & Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recent Loans Table (Restored) -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-slate-800">Préstamos Recientes</h3>
                        <Link :href="route('loans.index')" class="text-sm text-blue-600 hover:underline">Ver todos</Link>
                    </div>
                    <div class="p-0">
                         <Table>
                            <TableHeader class="bg-slate-50">
                                <TableRow>
                                    <TableHead class="pl-6">Código</TableHead>
                                    <TableHead>Cliente</TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead class="text-right pr-6">Monto</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="loan in stats.recent_loans" :key="loan.id" class="hover:bg-slate-50">
                                    <TableCell class="font-medium pl-6">
                                        <Link :href="route('loans.show', loan.id)" class="hover:underline text-blue-600 font-mono">
                                            {{ loan.description.replace('Préstamo ', '') }}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{{ loan.client_name }}</TableCell>
                                    <TableCell>{{ formatDate(loan.date) }}</TableCell>
                                    <TableCell class="text-right font-bold pr-6">{{ formatCurrency(loan.amount) }}</TableCell>
                                </TableRow>
                                <TableRow v-if="!stats.recent_loans || stats.recent_loans.length === 0">
                                    <TableCell colspan="4" class="text-center text-muted-foreground py-8">
                                        No hay actividad reciente.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <!-- Quick Actions (Restored) -->
                <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl shadow-lg text-white p-6 flex flex-col justify-between">
                    <div>
                        <h3 class="font-bold text-xl mb-2">Acciones Rápidas</h3>
                        <p class="text-blue-100 text-sm mb-6">Gestiona tu cartera de manera eficiente.</p>

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
                    <div class="mt-8 pt-6 border-t border-white/20 text-xs text-blue-200">
                        <p>Tip: Mantén los registros de pago al día para obtener reportes precisos.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
