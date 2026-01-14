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
</script>

<template>
    <Head :title="client.first_name + ' ' + client.last_name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Perfil de Cliente</h2>
                <div class="space-x-2">
                    <Link :href="route('clients.edit', client.id)">
                        <Button variant="outline">Editar</Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Insights / Stats Row -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card class="bg-blue-50 border-blue-100">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-xs font-medium text-blue-600 uppercase">Total Prestado</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-blue-900">{{ formatCurrency(stats.total_borrowed) }}</div>
                            <div class="text-xs text-blue-600 mt-1">{{ stats.total_loans }} préstamos históricos</div>
                        </CardContent>
                    </Card>
                    <Card class="bg-emerald-50 border-emerald-100">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-xs font-medium text-emerald-600 uppercase">Ganancia Total</CardTitle>
                        </CardHeader>
                        <CardContent>
                             <div class="text-2xl font-bold text-emerald-900">{{ formatCurrency(stats.total_interest_paid) }}</div>
                             <div class="text-xs text-emerald-600 mt-1">Intereses Cobrados</div>
                        </CardContent>
                    </Card>
                    <Card class="bg-green-50 border-green-100">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-xs font-medium text-green-600 uppercase">Total Pagado</CardTitle>
                        </CardHeader>
                        <CardContent>
                             <div class="text-2xl font-bold text-green-900">{{ formatCurrency(stats.total_paid) }}</div>
                             <div class="text-xs text-green-600 mt-1">Capital + Interés</div>
                        </CardContent>
                    </Card>
                    <Card :class="stats.current_arrears_count > 0 ? 'bg-red-50 border-red-100' : 'bg-white'">
                        <CardHeader class="pb-2">
                             <CardTitle class="text-xs font-medium uppercase" :class="stats.current_arrears_count > 0 ? 'text-red-600' : 'text-slate-500'">Estado y Actividad</CardTitle>
                        </CardHeader>
                        <CardContent>
                             <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium">Activos:</span>
                                <Badge variant="outline">{{ stats.active_loans }}</Badge>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium">Cerrados:</span>
                                <Badge variant="secondary">{{ stats.completed_loans }}</Badge>
                            </div>
                            <div class="pt-2 border-t border-slate-100 mt-2">
                                <div v-if="stats.current_arrears_count > 0" class="flex items-center text-red-600 font-bold">
                                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> {{ stats.current_arrears_count }} En Atraso
                                </div>
                                <div v-else class="flex items-center text-green-600 font-bold">
                                    <i class="fa-solid fa-check-circle mr-2"></i> Al día
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Client Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Información Personal</CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm font-medium text-slate-500">Nombre Completo</div>
                            <div class="text-lg">{{ client.first_name }} {{ client.last_name }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-slate-500">Cédula (ID)</div>
                            <div class="text-lg">{{ client.national_id }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-slate-500">Teléfono</div>
                            <div>{{ client.phone || 'N/A' }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-slate-500">Email</div>
                            <div>{{ client.email || 'N/A' }}</div>
                        </div>
                        <div class="col-span-2">
                            <div class="text-sm font-medium text-slate-500">Dirección</div>
                            <div>{{ client.address || 'N/A' }}</div>
                        </div>
                        <div class="col-span-2" v-if="client.notes">
                            <div class="text-sm font-medium text-slate-500">Notas</div>
                            <div class="text-sm bg-slate-50 p-3 rounded-md border">{{ client.notes }}</div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Loans -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle>Historial de Préstamos</CardTitle>
                        <Link :href="route('loans.create', { client_id: client.id })">
                            <Button size="sm">Nuevo Préstamo</Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Código</TableHead>
                                    <TableHead>Fecha Emisión</TableHead>
                                    <TableHead>Monto Inicial</TableHead>
                                    <TableHead>Balance Actual</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead class="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="loan in client.loans" :key="loan.id">
                                    <TableCell class="font-medium font-mono">{{ loan.code }}</TableCell>
                                    <TableCell>{{ formatDate(loan.start_date) }}</TableCell>
                                    <TableCell>{{ formatCurrency(loan.principal_initial) }}</TableCell>
                                    <TableCell class="font-bold">{{ formatCurrency(loan.balance_total) }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="loan.status === 'active' ? 'default' : (loan.status === 'closed' ? 'secondary' : 'outline')">
                                            {{ loan.status === 'active' ? 'Activo' : (loan.status === 'closed' ? 'Cerrado' : loan.status) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('loans.show', loan.id)">
                                            <Button variant="ghost" size="sm">
                                                Ver <i class="fa-solid fa-chevron-right ml-1 text-xs"></i>
                                            </Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="client.loans.length === 0">
                                    <TableCell colspan="6" class="text-center h-24 text-muted-foreground">
                                        Este cliente no tiene préstamos registrados.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
