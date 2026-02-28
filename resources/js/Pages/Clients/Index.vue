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
const includeInactive = ref(Boolean(props.filters.include_inactive));

const doSearch = customDebounce(() => {
    router.get(route('clients.index'), {
        search: search.value,
        include_inactive: includeInactive.value ? 1 : 0,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    });
}, 300);

watch(search, doSearch);
watch(includeInactive, doSearch);

const clearFilters = () => {
    search.value = '';
    includeInactive.value = false;
};

const toggleClientStatus = (client) => {
    const nextStatus = client.status === 'active' ? 'inactive' : 'active';

    router.patch(route('clients.status', client.id), {
        status: nextStatus,
        search: search.value,
        include_inactive: includeInactive.value ? 1 : 0,
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center gap-6">
                <h2 class="font-bold text-2xl text-surface-800 leading-tight">Clientes</h2>
                <Link :href="route('clients.create')">
                    <Button class="bg-primary-600 hover:bg-primary-700 text-white rounded-xl shadow-md px-6 transition-all hover:scale-105 ml-4 cursor-pointer">
                        <i class="fa-solid fa-plus mr-2"></i> Nuevo Cliente
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
                        <Input id="search" v-model="search" placeholder="Buscar por ID, nombre, identificación, teléfono o nota..." class="pl-10 h-10 rounded-xl border-surface-200 focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                </div>
                <div class="w-full md:w-auto">
                    <button
                        type="button"
                        @click="includeInactive = !includeInactive"
                        class="text-xs text-surface-500 hover:text-surface-700 underline underline-offset-4"
                    >
                        {{ includeInactive ? 'Ocultar inhabilitados' : 'Mostrar inhabilitados' }}
                    </button>
                </div>
                 <div class="w-full md:w-auto" v-if="search">
                    <Button variant="ghost" @click="clearFilters" class="text-surface-500 hover:text-surface-700">
                        Limpiar
                    </Button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-surface-100 overflow-hidden">
                <div class="p-6 border-b border-surface-100 bg-surface-50/50">
                    <h3 class="font-bold text-lg text-surface-800">Directorio de Clientes</h3>
                    <p class="text-sm text-surface-500">Gestiona la base de datos de tus clientes.</p>
                </div>
                <div class="p-0">
                    <Table>
                        <TableHeader class="bg-surface-50">
                            <TableRow>
                                <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider pl-6">ID</TableHead>
                                <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Identificación</TableHead>
                                <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Nombre</TableHead>
                                <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Teléfono</TableHead>
                                <TableHead class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Estado</TableHead>
                                <TableHead class="text-right text-xs font-semibold text-surface-500 uppercase tracking-wider pr-6">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="client in clients" :key="client.id" class="hover:bg-surface-50 transition-colors group">
                                <TableCell class="font-mono font-semibold text-surface-700 pl-6">{{ client.client_code || '-' }}</TableCell>
                                <TableCell class="font-medium text-surface-700">{{ client.national_id }}</TableCell>
                                <TableCell>
                                    <div class="font-semibold text-surface-800">{{ client.first_name }} {{ client.last_name }}</div>
                                    <div class="text-xs text-surface-400">{{ client.email }}</div>
                                </TableCell>
                                <TableCell class="text-surface-600">{{ client.phone || '-' }}</TableCell>
                                <TableCell>
                                    <Badge :variant="client.status === 'active' ? 'default' : 'secondary'" class="capitalize rounded-md">
                                        {{ client.status === 'active' ? 'Activo' : 'Inactivo' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right pr-6">
                                    <div class="flex justify-end items-center gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="rounded-lg cursor-pointer"
                                            :class="client.status === 'active' ? 'text-danger-600 hover:text-danger-700 hover:bg-danger-50' : 'text-success-600 hover:text-success-700 hover:bg-success-50'"
                                            @click="toggleClientStatus(client)"
                                        >
                                            {{ client.status === 'active' ? 'Inhabilitar' : 'Reactivar' }}
                                        </Button>
                                        <Link :href="route('clients.show', client.id)">
                                            <Button variant="ghost" size="sm" class="text-primary-600 hover:text-primary-700 hover:bg-primary-50 rounded-lg cursor-pointer">
                                                Ver Perfil <i class="fa-solid fa-arrow-right ml-2 text-xs"></i>
                                            </Button>
                                        </Link>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="clients.length === 0">
                                <TableCell colspan="6" class="text-center h-32 text-surface-400">
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
