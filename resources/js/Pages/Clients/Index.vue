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

// Simple debounce
function customDebounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

const props = defineProps({
    clients: Array,
    filters: Object
});

const search = ref(props.filters.search || '');

const doSearch = customDebounce(() => {
    router.get(route('clients.index'), {
        search: search.value
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
}, 300);

watch(search, doSearch);

const clearFilters = () => {
    search.value = '';
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center gap-6">
                <h2 class="font-bold text-2xl text-slate-800 leading-tight">Clientes</h2>
                <Link :href="route('clients.create')">
                    <Button class="bg-primary-600 hover:bg-primary-700 text-white rounded-xl shadow-md px-6 transition-all hover:scale-105 ml-4 cursor-pointer">
                        <i class="fa-solid fa-plus mr-2"></i> Nuevo Cliente
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
                        <Input id="search" v-model="search" placeholder="Buscar por nombre, cédula o teléfono..." class="pl-10 h-10 rounded-xl border-slate-200 focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                </div>
                 <div class="w-full md:w-auto" v-if="search">
                    <Button variant="ghost" @click="clearFilters" class="text-slate-500 hover:text-slate-700">
                        Limpiar
                    </Button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="font-bold text-lg text-slate-800">Directorio de Clientes</h3>
                    <p class="text-sm text-slate-500">Gestiona la base de datos de tus clientes.</p>
                </div>
                <div class="p-0">
                    <Table>
                        <TableHeader class="bg-slate-50">
                            <TableRow>
                                <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider pl-6">Cédula</TableHead>
                                <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Nombre</TableHead>
                                <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Teléfono</TableHead>
                                <TableHead class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Estado</TableHead>
                                <TableHead class="text-right text-xs font-semibold text-slate-500 uppercase tracking-wider pr-6">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="client in clients" :key="client.id" class="hover:bg-slate-50 transition-colors group">
                                <TableCell class="font-medium text-slate-700 pl-6">{{ client.national_id }}</TableCell>
                                <TableCell>
                                    <div class="font-semibold text-slate-800">{{ client.first_name }} {{ client.last_name }}</div>
                                    <div class="text-xs text-slate-400">{{ client.email }}</div>
                                </TableCell>
                                <TableCell class="text-slate-600">{{ client.phone || '-' }}</TableCell>
                                <TableCell>
                                    <Badge :variant="client.status === 'active' ? 'default' : 'secondary'" class="capitalize rounded-md">
                                        {{ client.status === 'active' ? 'Activo' : 'Inactivo' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right pr-6">
                                    <Link :href="route('clients.show', client.id)">
                                        <Button variant="ghost" size="sm" class="text-primary-600 hover:text-primary-700 hover:bg-primary-50 rounded-lg cursor-pointer">
                                            Ver Perfil <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                                        </Button>
                                    </Link>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="clients.length === 0">
                                <TableCell colspan="5" class="text-center h-32 text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fa-regular fa-folder-open text-3xl mb-2 opacity-50"></i>
                                        <p>No se encontraron clientes</p>
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
