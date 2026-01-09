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
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
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

                <!-- Client Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ client.first_name }} {{ client.last_name }}</CardTitle>
                        <div class="text-sm text-muted-foreground">Cédula: {{ client.national_id }}</div>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm font-medium">Contacto</div>
                            <div class="text-sm text-gray-600">{{ client.phone || 'N/A' }}</div>
                            <div class="text-sm text-gray-600">{{ client.email || 'N/A' }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Dirección</div>
                            <div class="text-sm text-gray-600">{{ client.address || 'N/A' }}</div>
                        </div>
                        <div class="col-span-2">
                            <div class="text-sm font-medium">Notas</div>
                            <div class="text-sm text-gray-600">{{ client.notes || '---' }}</div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Loans -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle>Préstamos</CardTitle>
                        <Link :href="route('loans.create', { client_id: client.id })">
                            <Button size="sm">Nuevo Préstamo</Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Código</TableHead>
                                    <TableHead>Fecha Inicio</TableHead>
                                    <TableHead>Monto Inicial</TableHead>
                                    <TableHead>Balance Actual</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead class="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="loan in client.loans" :key="loan.id">
                                    <TableCell class="font-medium">{{ loan.code }}</TableCell>
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
