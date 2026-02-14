<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import Pagination from '@/Components/Pagination.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    loans: Object,
    filters: Object
});

const search = ref(props.filters?.search || '');

watch(search, (value) => {
    router.get(route('loans.legal'), { search: value }, { preserveState: true, replace: true });
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    const parts = dateString.split('T')[0].split('-');
    if (parts.length === 3) {
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        return date.toLocaleDateString('es-DO');
    }
    return dateString;
};
</script>

<template>
    <Head title="Préstamos en Legal" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-surface-800 leading-tight">Préstamos en Legal</h2>
        </template>

        <div class="py-6 space-y-6">
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-surface-100 flex flex-col md:flex-row gap-4 items-end">
                <div class="flex-1">
                    <Label for="search" class="text-xs font-semibold text-surface-500 uppercase mb-1 block pl-1">Buscar</Label>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-surface-400"></i>
                        <Input id="search" v-model="search" placeholder="Código o Cliente..." class="pl-10 h-10 rounded-xl border-surface-200 focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('loans.index')">
                        <Button variant="ghost" class="text-surface-500 hover:text-surface-700">
                            <i class="fa-solid fa-arrow-left mr-2"></i> Volver a Préstamos
                        </Button>
                    </Link>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-surface-100 overflow-hidden">
                <div class="p-6 border-b border-surface-100 bg-surface-50/50">
                    <h3 class="font-bold text-lg text-surface-800">Listado de Legal</h3>
                    <p class="text-sm text-surface-500">Clientes con préstamos en estado legal.</p>
                </div>
                <div class="p-0">
                    <Table>
                        <TableHeader class="bg-primary-600">
                            <TableRow>
                                <TableHead class="text-xs font-semibold text-white uppercase tracking-wider pl-6">Código</TableHead>
                                <TableHead class="text-xs font-semibold text-white uppercase tracking-wider">Cliente</TableHead>
                                <TableHead class="text-xs font-semibold text-white uppercase tracking-wider">Fecha Legal</TableHead>
                                <TableHead class="text-xs font-semibold text-white uppercase tracking-wider">Balance</TableHead>
                                <TableHead class="text-xs font-semibold text-white uppercase tracking-wider">Estado</TableHead>
                                <TableHead class="text-right text-xs font-semibold text-white uppercase tracking-wider pr-6">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="loan in loans.data" :key="loan.id" class="hover:bg-primary-50 transition-colors">
                                <TableCell class="font-mono font-medium text-surface-700 pl-6">{{ loan.code }}</TableCell>
                                <TableCell>
                                    <Link :href="route('clients.show', loan.client.id)" class="font-medium text-primary-600 hover:text-primary-700 hover:underline">
                                        {{ loan.client.first_name }} {{ loan.client.last_name }}
                                    </Link>
                                    <div class="text-xs text-surface-400">{{ loan.client.national_id }}</div>
                                </TableCell>
                                <TableCell class="text-surface-600 whitespace-nowrap">{{ formatDateTime(loan.legal_entered_at) }}</TableCell>
                                <TableCell class="font-bold text-surface-800">{{ formatCurrency(loan.balance_total) }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline" class="rounded-md w-fit text-amber-700 border-amber-200 bg-amber-50">
                                        Legal
                                    </Badge>
                                    <div v-if="loan.arrears_info && loan.arrears_info.amount > 0" class="text-xs font-bold text-red-600 mt-1">
                                        {{ loan.arrears_info.days }} días en mora
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
                                <TableCell colspan="6" class="text-center h-32 text-surface-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fa-solid fa-scale-balanced text-3xl mb-2 opacity-50"></i>
                                        <p>No hay préstamos en legal con estos criterios.</p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
                <div v-if="loans.links" class="p-6 border-t border-surface-100 bg-surface-50/50 flex justify-center">
                    <Pagination :links="loans.links" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
