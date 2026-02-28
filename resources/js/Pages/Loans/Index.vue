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
import Pagination from '@/Components/Pagination.vue';

// If lodash not available, simple debounce
function customDebounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

const props = defineProps({
    loans: Object, // Changed from Array to Object to support Pagination data
    filters: Object
});

const search = ref(props.filters.search || '');
const dateFilter = ref(props.filters.date_filter || new Date().toISOString().split('T')[0]);
const currentTab = ref(props.filters.tab || 'active');

const doSearch = customDebounce(() => {
    router.get(route('loans.index'), {
        search: search.value,
        date_filter: dateFilter.value,
        tab: currentTab.value
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
}, 300);

watch(search, doSearch);
watch(dateFilter, doSearch);
watch(currentTab, doSearch);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    // Ensure we handle plain date strings YYYY-MM-DD
    const parts = dateString.split('T')[0].split('-');
    if (parts.length === 3) {
        // Create date from parts using local time construction to avoid timezone shifts
        // (YYYY, MM-1, DD)
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        return date.toLocaleDateString('es-DO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }
    return dateString; // Fallback
};

const clearFilters = () => {
    search.value = '';
    dateFilter.value = new Date().toISOString().split('T')[0];
    currentTab.value = 'active';
};


const tabLabels = {
    active: 'Préstamos Activos',
    legal: 'Préstamos en Legal',
    cancelled: 'Préstamos Cancelados',
    all: 'Todos los Préstamos',
};

const tableTitle = () => tabLabels[currentTab.value] || tabLabels.active;

const statusLabel = (status) => {
    const labels = {
        active: 'Activo',
        closed: 'Cerrado',
        closed_refinanced: 'Consolidado',
        cancelled: 'Cancelado',
        written_off: 'Incobrable',
        defaulted: 'En mora',
    };

    return labels[status] ?? status;
};
</script>

<template>
    <Head title="Préstamos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center gap-6">
                <h2 class="font-bold text-2xl text-surface-800 leading-tight">Cartera de Préstamos</h2>
                <Link :href="route('loans.create')">
                    <Button class="bg-primary-600 hover:bg-primary-700 text-white rounded-xl shadow-md px-6 transition-all hover:scale-105 ml-4 cursor-pointer">
                        <i class="fa-solid fa-plus mr-2"></i> Nuevo Préstamo
                    </Button>
                </Link>
            </div>
        </template>

        <div class="py-6 space-y-6">
            <!-- Filters -->
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-surface-100 flex flex-col md:flex-row gap-4 items-end">
                <div class="w-full md:w-1/3 relative">
                    <Label for="search" class="sr-only">Buscar</Label>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-surface-400"></i>
                         <Input id="search" v-model="search" placeholder="Código, Monto, Cliente o Nota..." class="pl-10 h-10 rounded-xl border-surface-200 focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                </div>
                <div class="w-full md:w-1/4">
                     <Label for="date_filter" class="text-xs font-semibold text-surface-500 uppercase mb-1 block pl-1">Ver hasta</Label>
                     <Input id="date_filter" type="date" v-model="dateFilter" class="h-10 rounded-xl border-surface-200" />
                </div>
                <div class="w-full md:w-auto pb-1">
                    <Button type="button" variant="ghost" @click="clearFilters" class="text-surface-500 hover:text-surface-700">
                        Limpiar
                    </Button>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-2 shadow-sm border border-surface-100 flex flex-wrap gap-2">
                <Button type="button" @click="currentTab = 'active'" :class="currentTab === 'active' ? 'bg-primary-600 text-white' : 'bg-white text-surface-600 border border-surface-200'">Activos</Button>
                <Button type="button" @click="currentTab = 'legal'" :class="currentTab === 'legal' ? 'bg-primary-600 text-white' : 'bg-white text-surface-600 border border-surface-200'">Legal</Button>
                <Button type="button" @click="currentTab = 'cancelled'" :class="currentTab === 'cancelled' ? 'bg-primary-600 text-white' : 'bg-white text-surface-600 border border-surface-200'">Cancelados</Button>
                <Button type="button" @click="currentTab = 'all'" :class="currentTab === 'all' ? 'bg-primary-600 text-white' : 'bg-white text-surface-600 border border-surface-200'">Todos</Button>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-surface-100 overflow-hidden">
                <div class="p-6 border-b border-surface-100 bg-surface-50/50">
                     <h3 class="font-bold text-lg text-surface-800">{{ tableTitle() }}</h3>
                     <p class="text-sm text-surface-500">Listado completo de operaciones.</p>
                </div>
                <div class="p-0">
                    <Table>
                        <TableHeader class="bg-surface-50">
                            <TableRow>
                                <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider pl-6">Código</TableHead>
                                <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Cliente</TableHead>
                                <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Fecha</TableHead>
                                <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Monto</TableHead>
                                <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Balance</TableHead>
                                <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Estado</TableHead>
                                <TableHead class="text-right text-xs font-semibold text-surface-500 uppercase tracking-wider pr-6">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="loan in loans.data" :key="loan.id" class="hover:bg-surface-50 transition-colors">
                                <TableCell class="font-mono font-medium text-surface-700 pl-6">{{ loan.code }}</TableCell>
                                <TableCell>
                                    <Link :href="route('clients.show', loan.client.id)" class="font-medium text-primary-600 hover:text-primary-800 hover:underline">
                                        {{ loan.client.first_name }} {{ loan.client.last_name }}
                                    </Link>
                                    <div class="text-xs text-surface-400">{{ loan.client.national_id }}</div>
                                </TableCell>
                                <TableCell class="text-surface-600 whitespace-nowrap">{{ formatDateTime(loan.start_date) }}</TableCell>
                                <TableCell class="text-surface-700">{{ formatCurrency(loan.principal_initial) }}</TableCell>
                                <TableCell class="font-bold text-surface-800">{{ formatCurrency(loan.balance_total) }}</TableCell>
                                <TableCell>
                                    <div class="flex flex-col gap-1">
                                        <Badge :variant="loan.status === 'active' ? 'default' : (loan.status === 'closed' ? 'secondary' : 'outline')" class="rounded-md capitalize w-fit">
                                            {{ statusLabel(loan.status) }}
                                        </Badge>
                                        <Badge v-if="loan.legal_status" variant="outline" class="rounded-md w-fit text-warning-700 border-warning-200 bg-warning-50">
                                            Legal
                                        </Badge>
                                        <div v-if="loan.arrears_info && loan.arrears_info.amount > 0" class="text-xs font-bold text-danger-600">
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                            {{ loan.arrears_info.count }} Cuotas Pend.
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right pr-6">
                                    <Link :href="route('loans.show', loan.id)">
                                        <Button variant="ghost" size="sm" class="text-primary-600 hover:text-primary-700 hover:bg-primary-50 rounded-lg cursor-pointer">
                                            Detalle <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                                        </Button>
                                    </Link>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="loans.data.length === 0">
                                <TableCell colspan="7" class="text-center h-32 text-surface-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fa-solid fa-magnifying-glass text-3xl mb-2 opacity-50"></i>
                                        <p>No se encontraron préstamos con estos criterios.</p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Pagination -->
                <div v-if="loans.links" class="p-6 border-t border-surface-100 bg-surface-50/50 flex justify-center">
                    <Pagination :links="loans.links" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
