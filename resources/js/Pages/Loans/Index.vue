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
import { computed, ref, watch } from 'vue';
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
    filters: Object,
    has_archived_loans: Boolean,
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
watch(currentTab, () => {
    selectedLoanIds.value = [];
    doSearch();
});

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


const selectedLoanIds = ref([]);

const loansInView = computed(() => props.loans?.data || []);

const archivableTabs = ['closed', 'cancelled'];
const canSelectForArchive = computed(() => archivableTabs.includes(currentTab.value));

const canArchive = computed(() => canSelectForArchive.value && selectedLoanIds.value.length > 0);

const toggleAll = (checked) => {
    selectedLoanIds.value = checked
        ? loansInView.value.map((loan) => loan.id)
        : [];
};

const isSelected = (loanId) => selectedLoanIds.value.includes(loanId);

const toggleLoanSelection = (loanId, checked) => {
    if (checked) {
        if (!selectedLoanIds.value.includes(loanId)) {
            selectedLoanIds.value.push(loanId);
        }
        return;
    }

    selectedLoanIds.value = selectedLoanIds.value.filter((id) => id !== loanId);
};

const archiveSelected = () => {
    if (!canArchive.value) {
        return;
    }

    if (!window.confirm(`¿Deseas archivar ${selectedLoanIds.value.length} préstamo(s)? Esta acción los excluirá de los cálculos del Dashboard.`)) {
        return;
    }

    router.post(route('loans.archive'), {
        loan_ids: selectedLoanIds.value,
        source_tab: currentTab.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedLoanIds.value = [];
        },
    });
};

const clearFilters = () => {
    search.value = '';
    dateFilter.value = new Date().toISOString().split('T')[0];
    currentTab.value = 'active';
    selectedLoanIds.value = [];
};


const tabLabels = {
    active: 'Préstamos Activos',
    legal: 'Préstamos en Legal',
    closed: 'Préstamos Cerrados',
    adjustment: 'Préstamos en Ajuste',
    cancelled: 'Préstamos Cancelados',
    archived: 'Préstamos Archivados',
    all: 'Todos los Préstamos',
};

const archiveHintByTab = {
    closed: 'Selecciona préstamos cerrados para archivarlos y excluirlos de métricas del Dashboard.',
    cancelled: 'Selecciona préstamos cancelados/incobrables para archivarlos y excluirlos de métricas del Dashboard.',
};

const tableTitle = () => tabLabels[currentTab.value] || tabLabels.active;

watch(
    () => props.loans?.data,
    () => {
        const validIds = new Set((props.loans?.data || []).map((loan) => loan.id));
        selectedLoanIds.value = selectedLoanIds.value.filter((id) => validIds.has(id));
    }
);

const statusLabel = (status) => {
    const labels = {
        active: 'Activo',
        closed: 'Cerrado',
        closed_refinanced: 'Consolidado',
        cancelled: 'Cancelado',
        written_off: 'Incobrable',
        under_adjustment: 'En ajuste',
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
                <Button type="button" @click="currentTab = 'closed'" :class="currentTab === 'closed' ? 'bg-primary-600 text-white' : 'bg-white text-surface-600 border border-surface-200'">Cerrados</Button>
                <Button type="button" @click="currentTab = 'adjustment'" :class="currentTab === 'adjustment' ? 'bg-info-600 text-white' : 'bg-white text-surface-600 border border-surface-200'">En ajuste</Button>
                <Button type="button" @click="currentTab = 'cancelled'" :class="currentTab === 'cancelled' ? 'bg-primary-600 text-white' : 'bg-white text-surface-600 border border-surface-200'">Cancelados</Button>
                <Button v-if="has_archived_loans || currentTab === 'archived'" type="button" @click="currentTab = 'archived'" :class="currentTab === 'archived' ? 'bg-surface-900 text-warning-200 border border-warning-500 shadow-inner' : 'bg-warning-50 text-warning-700 border border-warning-200'">
                    <i class="fa-solid fa-box-archive mr-2"></i>Archivados
                </Button>
                <Button type="button" @click="currentTab = 'all'" :class="currentTab === 'all' ? 'bg-primary-600 text-white' : 'bg-white text-surface-600 border border-surface-200'">Todos</Button>
            </div>

            <div v-if="canSelectForArchive" class="bg-warning-50 border border-warning-200 rounded-xl px-4 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <p class="text-sm text-warning-800">{{ archiveHintByTab[currentTab] }}</p>
                <Button type="button" @click="archiveSelected" :disabled="!canArchive" class="bg-warning-600 hover:bg-warning-700 text-white disabled:opacity-50">
                    <i class="fa-solid fa-box-archive mr-2"></i>Archivar seleccionados ({{ selectedLoanIds.length }})
                </Button>
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
                                <TableHead v-if="canSelectForArchive" class="w-12 pl-6">
                                    <input
                                        type="checkbox"
                                        :checked="loansInView.length > 0 && selectedLoanIds.length === loansInView.length"
                                        @change="toggleAll($event.target.checked)"
                                        class="rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                                    >
                                </TableHead>
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
                                <TableCell v-if="canSelectForArchive" class="pl-6">
                                    <input
                                        type="checkbox"
                                        :checked="isSelected(loan.id)"
                                        @change="toggleLoanSelection(loan.id, $event.target.checked)"
                                        class="rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                                    >
                                </TableCell>
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
                                <TableCell :colspan="canSelectForArchive ? 8 : 7" class="text-center h-32 text-surface-400">
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
