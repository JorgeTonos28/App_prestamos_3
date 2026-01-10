<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
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
    clients: Array,
});
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Clientes</h2>
                <Link :href="route('clients.create')">
                    <Button>Nuevo Cliente</Button>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Cédula</TableHead>
                                    <TableHead>Nombre</TableHead>
                                    <TableHead>Teléfono</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead class="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="client in clients" :key="client.id">
                                    <TableCell class="font-medium">{{ client.national_id }}</TableCell>
                                    <TableCell>{{ client.first_name }} {{ client.last_name }}</TableCell>
                                    <TableCell>{{ client.phone }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="client.status === 'active' ? 'default' : 'secondary'">
                                            {{ client.status }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Link :href="route('clients.show', client.id)">
                                            <Button variant="ghost" size="sm">Ver</Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="clients.length === 0">
                                    <TableCell colspan="5" class="text-center h-24 text-muted-foreground">
                                        No hay clientes registrados.
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
