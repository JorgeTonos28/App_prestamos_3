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
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">Dashboard</h2>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Portfolio Balance -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md transition-all duration-300">
                <div>
                    <p class="text-sm font-medium text-slate-500 mb-1">Cartera Activa</p>
                    <h3 class="text-3xl font-bold text-slate-800">{{ formatCurrency(portfolio_balance) }}</h3>
                </div>
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300">
                    <i class="fa-solid fa-wallet text-xl"></i>
                </div>
            </div>

            <!-- Active Loans -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md transition-all duration-300">
                <div>
                    <p class="text-sm font-medium text-slate-500 mb-1">Préstamos Activos</p>
                    <h3 class="text-3xl font-bold text-slate-800">{{ active_loans_count }}</h3>
                </div>
                 <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300">
                    <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
                </div>
            </div>

            <!-- Overdue -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex items-center justify-between group hover:shadow-md transition-all duration-300">
                <div>
                    <p class="text-sm font-medium text-slate-500 mb-1">En Atraso (Est.)</p>
                    <h3 class="text-3xl font-bold text-red-600">{{ overdue_count }}</h3>
                </div>
                <div class="w-12 h-12 bg-red-50 rounded-xl flex items-center justify-center text-red-600 group-hover:bg-red-600 group-hover:text-white transition-colors duration-300">
                     <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h3 class="font-bold text-lg text-slate-800">Resumen General</h3>
            </div>
            <div class="p-6">
                 <p class="text-slate-600">Bienvenido al sistema de gestión de préstamos. Utilice el menú lateral para navegar.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
