<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';

defineProps({
    active_loans_count: Number,
    portfolio_balance: Number,
    overdue_count: Number,
    monthly_interest_income: Number,
    monthly_principal_recovered: Number,
    new_loans_count: Number,
    new_loans_volume: Number,
    recent_loans: Array
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('es-DO');
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">Dashboard</h2>
        </template>

        <div class="py-6 space-y-8">
            <!-- Top Stats: Portfolio Health -->
            <div>
                <h3 class="text-lg font-semibold text-slate-700 mb-4 px-1">Estado de la Cartera</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Portfolio Balance -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md transition-all duration-300">
                        <div>
                            <p class="text-sm font-medium text-slate-500 mb-1">Capital en Riesgo (Cartera)</p>
                            <h3 class="text-3xl font-bold text-slate-800">{{ formatCurrency(portfolio_balance) }}</h3>
                        </div>
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300">
                            <i class="fa-solid fa-wallet text-xl"></i>
                        </div>
                    </div>

                    <!-- Active Loans -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md transition-all duration-300">
                        <div>
                            <p class="text-sm font-medium text-slate-500 mb-1">Préstamos Activos</p>
                            <h3 class="text-3xl font-bold text-slate-800">{{ active_loans_count }}</h3>
                        </div>
                         <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300">
                            <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
                        </div>
                    </div>

                    <!-- Overdue -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md transition-all duration-300">
                        <div>
                            <p class="text-sm font-medium text-slate-500 mb-1">En Atraso</p>
                            <h3 class="text-3xl font-bold text-red-600">{{ overdue_count }}</h3>
                        </div>
                        <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center text-red-600 group-hover:bg-red-600 group-hover:text-white transition-colors duration-300">
                             <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Performance -->
            <div>
                 <h3 class="text-lg font-semibold text-slate-700 mb-4 px-1">Rendimiento Mensual (Este Mes)</h3>
                 <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                     <Card>
                         <CardHeader class="pb-2">
                             <CardTitle class="text-sm font-medium text-slate-500">Ingresos por Interés</CardTitle>
                         </CardHeader>
                         <CardContent>
                             <div class="text-2xl font-bold text-green-600">{{ formatCurrency(monthly_interest_income) }}</div>
                         </CardContent>
                     </Card>
                     <Card>
                         <CardHeader class="pb-2">
                             <CardTitle class="text-sm font-medium text-slate-500">Capital Recuperado</CardTitle>
                         </CardHeader>
                         <CardContent>
                             <div class="text-2xl font-bold text-blue-600">{{ formatCurrency(monthly_principal_recovered) }}</div>
                         </CardContent>
                     </Card>
                     <Card>
                         <CardHeader class="pb-2">
                             <CardTitle class="text-sm font-medium text-slate-500">Nuevos Préstamos</CardTitle>
                         </CardHeader>
                         <CardContent>
                             <div class="text-2xl font-bold">{{ new_loans_count }}</div>
                             <p class="text-xs text-muted-foreground">Vol: {{ formatCurrency(new_loans_volume) }}</p>
                         </CardContent>
                     </Card>
                     <Card>
                         <CardHeader class="pb-2">
                             <CardTitle class="text-sm font-medium text-slate-500">Tasa de Mora</CardTitle>
                         </CardHeader>
                         <CardContent>
                             <div class="text-2xl font-bold" :class="overdue_count > 0 ? 'text-red-500' : 'text-slate-700'">
                                 {{ active_loans_count > 0 ? Math.round((overdue_count / active_loans_count) * 100) : 0 }}%
                             </div>
                         </CardContent>
                     </Card>
                 </div>
            </div>

            <!-- Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-slate-800">Préstamos Recientes</h3>
                        <Link :href="route('loans.index')" class="text-sm text-blue-600 hover:underline">Ver todos</Link>
                    </div>
                    <div class="p-0">
                         <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Código</TableHead>
                                    <TableHead>Cliente</TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead class="text-right">Monto</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="loan in recent_loans" :key="loan.id">
                                    <TableCell class="font-medium">
                                        <Link :href="route('loans.show', loan.id)" class="hover:underline text-blue-600">
                                            {{ loan.description }}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{{ loan.client_name }}</TableCell>
                                    <TableCell>{{ formatDate(loan.date) }}</TableCell>
                                    <TableCell class="text-right font-bold">{{ formatCurrency(loan.amount) }}</TableCell>
                                </TableRow>
                                <TableRow v-if="recent_loans.length === 0">
                                    <TableCell colspan="4" class="text-center text-muted-foreground py-8">
                                        No hay actividad reciente.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <!-- Quick Actions or Tips -->
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
