<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
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
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

defineProps({
    loans: Array,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
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
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                                        No hay préstamos registrados.
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
