<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
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

defineProps({
    client: Object,
    stats: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};

const formatDate = (dateString) => {
    return dateString ? new Date(dateString).toLocaleDateString('es-DO') : '-';
};

const goBack = () => {
    window.history.back();
};
</script>

<template>
    <Head :title="client.first_name + ' ' + client.last_name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" @click="goBack" class="p-2 h-10 w-10 rounded-full hover:bg-slate-100 text-slate-500">
                        <i class="fa-solid fa-arrow-left"></i>
                    </Button>
                    <div>
                        <h2 class="font-bold text-2xl text-slate-800 leading-tight">{{ client.first_name }} {{ client.last_name }}</h2>
                        <p class="text-sm text-slate-500 font-medium">Perfil de Cliente</p>
                    </div>
                </div>
                <div class="space-x-2">
                    <Link :href="route('clients.edit', client.id)">
                        <Button class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md px-6 transition-all hover:scale-105 !cursor-pointer">
                            <i class="fa-solid fa-pen mr-2"></i> Editar Cliente
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 space-y-8">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Borrowed -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600">
                             <i class="fa-solid fa-hand-holding-dollar"></i>
                        </div>
                        <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">HISTÓRICO</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500 mb-1">Total Prestado</p>
                        <h3 class="text-2xl font-bold text-slate-800">{{ formatCurrency(stats.total_borrowed) }}</h3>
                        <p class="text-xs text-slate-400 mt-1">{{ stats.total_loans }} préstamos en total</p>
                    </div>
                </div>

                <!-- Total Interest Paid (Profit) -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600">
                             <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">GANANCIA</span>
                    </div>
                    <div>
                         <p class="text-sm font-medium text-slate-500 mb-1">Intereses Cobrados</p>
                         <h3 class="text-2xl font-bold text-slate-800">{{ formatCurrency(stats.total_interest_paid) }}</h3>
                         <p class="text-xs text-slate-400 mt-1">Beneficio neto generado</p>
                    </div>
                </div>

                <!-- Total Paid -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                         <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center text-green-600">
                             <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                        <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full">TOTAL</span>
                    </div>
                    <div>
                         <p class="text-sm font-medium text-slate-500 mb-1">Total Recibido</p>
                         <h3 class="text-2xl font-bold text-slate-800">{{ formatCurrency(stats.total_paid) }}</h3>
                         <p class="text-xs text-slate-400 mt-1">Capital + Intereses</p>
                    </div>
                </div>

                <!-- Activity & Status -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
                     <div class="flex justify-between items-start mb-4">
                        <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-slate-600">
                             <i class="fa-solid fa-heart-pulse"></i>
                        </div>
                        <div v-if="stats.current_arrears_count > 0" class="flex items-center text-red-600 text-xs font-bold bg-red-50 px-2 py-1 rounded-full">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> ATRASO
                        </div>
                        <div v-else class="flex items-center text-green-600 text-xs font-bold bg-green-50 px-2 py-1 rounded-full">
                            <i class="fa-solid fa-check mr-1"></i> AL DÍA
                        </div>
                    </div>
                    <div>
                         <div v-if="stats.current_arrears_count > 0">
                             <p class="text-sm font-medium text-slate-500 mb-1">Monto en Atraso</p>
                             <h3 class="text-2xl font-bold text-red-600">{{ formatCurrency(stats.total_arrears_amount) }}</h3>
                             <p class="text-xs text-red-400 mt-1 font-medium">{{ stats.current_arrears_count }} préstamos con atraso</p>
                         </div>
                         <div v-else>
                             <p class="text-sm font-medium text-slate-500 mb-2">Actividad Actual</p>
                             <div class="flex items-center space-x-4">
                                 <div class="flex flex-col">
                                     <span class="text-2xl font-bold text-slate-800">{{ stats.active_loans }}</span>
                                     <span class="text-xs text-slate-400">Activos</span>
                                 </div>
                                 <div class="h-8 w-px bg-slate-100"></div>
                                 <div class="flex flex-col">
                                     <span class="text-2xl font-bold text-slate-800">{{ stats.completed_loans }}</span>
                                     <span class="text-xs text-slate-400">Cerrados</span>
                                 </div>
                             </div>
                         </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Client Info Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden h-fit">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-slate-800">Datos Personales</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="flex items-start">
                            <div class="w-8 flex-shrink-0 text-center text-slate-400 mt-1"><i class="fa-solid fa-user"></i></div>
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase">Nombre Completo</p>
                                <p class="text-slate-800 font-medium">{{ client.first_name }} {{ client.last_name }}</p>
                            </div>
                        </div>
                         <div class="flex items-start">
                            <div class="w-8 flex-shrink-0 text-center text-slate-400 mt-1"><i class="fa-solid fa-id-card"></i></div>
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase">Cédula</p>
                                <p class="text-slate-800 font-medium">{{ client.national_id }}</p>
                            </div>
                        </div>
                         <div class="flex items-start">
                            <div class="w-8 flex-shrink-0 text-center text-slate-400 mt-1"><i class="fa-solid fa-phone"></i></div>
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase">Teléfono</p>
                                <p class="text-slate-800 font-medium">{{ client.phone || 'No registrado' }}</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="w-8 flex-shrink-0 text-center text-slate-400 mt-1"><i class="fa-solid fa-envelope"></i></div>
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase">Email</p>
                                <p class="text-slate-800 font-medium">{{ client.email || 'No registrado' }}</p>
                            </div>
                        </div>
                         <div class="flex items-start">
                            <div class="w-8 flex-shrink-0 text-center text-slate-400 mt-1"><i class="fa-solid fa-location-dot"></i></div>
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase">Dirección</p>
                                <p class="text-slate-800 font-medium">{{ client.address || 'No registrada' }}</p>
                            </div>
                        </div>
                        <div v-if="client.notes" class="pt-4 border-t border-slate-100">
                             <p class="text-xs font-semibold text-slate-500 uppercase mb-2">Notas</p>
                             <div class="bg-yellow-50 text-yellow-800 p-3 rounded-lg text-sm border border-yellow-100">
                                 {{ client.notes }}
                             </div>
                        </div>
                    </div>
                </div>

                <!-- Loan History -->
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-slate-800">Historial de Préstamos</h3>
                        <Link :href="route('loans.create', { client_id: client.id })">
                            <Button size="sm" class="rounded-lg shadow-sm cursor-pointer">
                                <i class="fa-solid fa-plus mr-2"></i> Nuevo
                            </Button>
                        </Link>
                    </div>
                    <div class="p-0">
                        <Table>
                            <TableHeader class="bg-slate-50">
                                <TableRow>
                                    <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider pl-6">Código</TableHead>
                                    <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Fecha</TableHead>
                                    <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Monto</TableHead>
                                    <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Balance</TableHead>
                                    <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Estado</TableHead>
                                    <TableHead class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider pr-6">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="loan in client.loans" :key="loan.id" class="hover:bg-slate-50 transition-colors">
                                    <TableCell class="font-mono text-slate-600 font-medium pl-6">{{ loan.code }}</TableCell>
                                    <TableCell class="text-slate-600">{{ formatDate(loan.start_date) }}</TableCell>
                                    <TableCell class="font-medium text-slate-800">{{ formatCurrency(loan.principal_initial) }}</TableCell>
                                    <TableCell class="font-bold text-slate-800">{{ formatCurrency(loan.balance_total) }}</TableCell>
                                    <TableCell>
                                        <div class="flex flex-col gap-1">
                                            <Badge :variant="loan.status === 'active' ? 'default' : (loan.status === 'closed' ? 'secondary' : 'outline')" class="rounded-md capitalize w-fit">
                                                {{ loan.status === 'active' ? 'Activo' : (loan.status === 'closed' ? 'Cerrado' : loan.status) }}
                                            </Badge>
                                            <div v-if="loan.arrears_info && loan.arrears_info.amount > 0" class="text-xs font-bold text-red-600">
                                                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                                {{ loan.arrears_info.count }} Cuotas Pend.
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right pr-6">
                                        <Link :href="route('loans.show', loan.id)">
                                            <Button variant="ghost" size="sm" class="text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg cursor-pointer">
                                                Ver <i class="fa-solid fa-arrow-right ml-1 text-xs"></i>
                                            </Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="client.loans.length === 0">
                                    <TableCell colspan="6" class="text-center h-32 text-slate-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fa-regular fa-file-lines text-3xl mb-2 opacity-50"></i>
                                            <p>No hay historial de préstamos</p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
