<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

defineProps({
    active_loans_count: Number,
    portfolio_balance: Number,
    overdue_count: Number,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm font-medium text-muted-foreground">Cartera Activa</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">{{ formatCurrency(portfolio_balance) }}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm font-medium text-muted-foreground">Préstamos Activos</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold">{{ active_loans_count }}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm font-medium text-muted-foreground text-red-600">En Atraso (Est.)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-bold text-red-600">{{ overdue_count }}</div>
                        </CardContent>
                    </Card>
                </div>

                <div class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        Bienvenido al sistema de gestión de préstamos.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
