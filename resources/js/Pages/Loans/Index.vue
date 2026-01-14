<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';
import { ref, watch } from 'vue';

// If lodash not available, simple debounce
function customDebounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

const props = defineProps({
    loans: Array,
    filters: Object
});

const search = ref(props.filters.search || '');
const dateFilter = ref(props.filters.date_filter || new Date().toISOString().split('T')[0]);

const doSearch = customDebounce(() => {
    router.get(route('loans.index'), {
        search: search.value,
        date_filter: dateFilter.value
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
}, 300);

watch(search, doSearch);
watch(dateFilter, doSearch);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
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

const clearFilters = () => {
    search.value = '';
    dateFilter.value = new Date().toISOString().split('T')[0];
};
</script>

<template>
    <Head title="Préstamos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">Cartera de Préstamos</h2>
                <Link :href="route('loans.create')">
                    <Button class="rounded-lg shadow-sm hover:shadow-md transition-all">
                        <i class="fa-solid fa-plus mr-2"></i> Nuevo Préstamo
                    </Button>
                </Link>
            </div>
        </template>

        <div class="py-6 space-y-6">
            <!-- Filters -->
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 flex flex-col md:flex-row gap-4 items-end">
                <div class="w-full md:w-1/3 relative">
                    <Label for="search" class="sr-only">Buscar</Label>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400"></i>
                         <Input id="search" v-model="search" placeholder="Código, Monto o Cliente..." class="pl-10 h-10 rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500" />
                    </div>
                </div>
                <div class="w-full md:w-1/4">
                     <Label for="date_filter" class="text-xs font-semibold text-slate-500 uppercase mb-1 block pl-1">Ver hasta</Label>
                     <Input id="date_filter" type="date" v-model="dateFilter" class="h-10 rounded-xl border-slate-200" />
                </div>
                 <div class="w-full md:w-auto pb-1">
                    <Button variant="ghost" @click="clearFilters" class="text-slate-500 hover:text-slate-700">
                        Limpiar
                    </Button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                     <h3 class="font-bold text-lg text-slate-800">Préstamos Registrados</h3>
                     <p class="text-sm text-slate-500">Listado completo de operaciones.</p>
                </div>
                <div class="p-0">
                    <Table>
                        <TableHeader class="bg-slate-50">
                            <TableRow>
                                <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider pl-6">Código</TableHead>
                                <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Cliente</TableHead>
                                <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Fecha</TableHead>
                                <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Monto</TableHead>
                                <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Balance</TableHead>
                                <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Estado</TableHead>
                                <TableHead class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider pr-6">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="loan in loans" :key="loan.id" class="hover:bg-slate-50 transition-colors">
                                <TableCell class="font-mono font-medium text-slate-700 pl-6">{{ loan.code }}</TableCell>
                                <TableCell>
                                    <Link :href="route('clients.show', loan.client.id)" class="font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ loan.client.first_name }} {{ loan.client.last_name }}
                                    </Link>
                                    <div class="text-xs text-slate-400">{{ loan.client.national_id }}</div>
                                </TableCell>
                                <TableCell class="text-slate-600 whitespace-nowrap">{{ formatDateTime(loan.start_date) }}</TableCell>
                                <TableCell class="text-slate-700">{{ formatCurrency(loan.principal_initial) }}</TableCell>
                                <TableCell class="font-bold text-slate-800">{{ formatCurrency(loan.balance_total) }}</TableCell>
                                <TableCell>
                                    <Badge :variant="loan.status === 'active' ? 'default' : (loan.status === 'closed' ? 'secondary' : 'outline')" class="rounded-md capitalize">
                                        {{ loan.status === 'active' ? 'Activo' : (loan.status === 'closed' ? 'Cerrado' : loan.status) }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right pr-6">
                                    <Link :href="route('loans.show', loan.id)">
                                        <Button variant="ghost" size="sm" class="text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg">
                                            Detalle <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                                        </Button>
                                    </Link>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="loans.length === 0">
                                <TableCell colspan="7" class="text-center h-32 text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fa-solid fa-magnifying-glass text-3xl mb-2 opacity-50"></i>
                                        <p>No se encontraron préstamos con estos criterios.</p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
