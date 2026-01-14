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
import { debounce } from 'lodash'; // Typically available, or use custom debounce

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

const clearFilters = () => {
    search.value = '';
    dateFilter.value = new Date().toISOString().split('T')[0];
    // Trigger update immediately or let watchers do it
};
</script>

<template>
    <Head title="Préstamos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cartera de Préstamos</h2>
                <Link :href="route('loans.create')">
                    <Button>Nuevo Préstamo</Button>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- Filters -->
                <Card class="bg-white">
                    <CardContent class="p-4 flex flex-col md:flex-row gap-4 items-end">
                        <div class="w-full md:w-1/3">
                            <Label for="search">Buscar</Label>
                            <Input id="search" v-model="search" placeholder="Código, Monto o Cliente..." />
                        </div>
                        <div class="w-full md:w-1/4">
                             <Label for="date_filter">Ver Registros Hasta</Label>
                             <Input id="date_filter" type="date" v-model="dateFilter" />
                        </div>
                         <div class="w-full md:w-auto">
                            <Button variant="ghost" @click="clearFilters">Limpiar</Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Código</TableHead>
                                    <TableHead>Cliente</TableHead>
                                    <TableHead>Fecha Inicio</TableHead>
                                    <TableHead>Monto Inicial</TableHead>
                                    <TableHead>Balance Actual</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead class="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="loan in loans" :key="loan.id">
                                    <TableCell class="font-medium">{{ loan.code }}</TableCell>
                                    <TableCell>
                                        <Link :href="route('clients.show', loan.client.id)" class="text-blue-600 hover:underline">
                                            {{ loan.client.first_name }} {{ loan.client.last_name }}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{{ loan.start_date }}</TableCell>
                                    <TableCell>{{ formatCurrency(loan.principal_initial) }}</TableCell>
                                    <TableCell class="font-bold">{{ formatCurrency(loan.balance_total) }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="loan.status === 'active' ? 'default' : (loan.status === 'closed' ? 'secondary' : 'outline')">
                                            {{ loan.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('loans.show', loan.id)">
                                            <Button variant="ghost" size="sm">Detalle</Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="loans.length === 0">
                                    <TableCell colspan="7" class="text-center h-24 text-muted-foreground">
                                        No se encontraron préstamos con estos criterios.
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
