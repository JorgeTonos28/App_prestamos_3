<script setup>
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';

const props = defineProps({
    open: Boolean,
    loan: Object,
    summary: Object,
    printUrl: String,
});

const emit = defineEmits(['update:open']);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-2xl bg-white">
            <DialogHeader>
                <DialogTitle class="text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-emerald-600"></i>
                    Resumen de Pago - {{ loan.code }}
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
                <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4">
                    <p class="text-sm text-emerald-700">Cliente</p>
                    <h3 class="text-lg font-bold text-emerald-900">{{ loan.client.first_name }} {{ loan.client.last_name }}</h3>
                    <p class="text-xs text-emerald-600">{{ loan.client.national_id }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white border border-slate-100 rounded-xl p-4">
                        <p class="text-xs text-slate-500 uppercase">Capital</p>
                        <p class="text-lg font-bold text-slate-800">{{ formatCurrency(summary.principal) }}</p>
                    </div>
                    <div class="bg-white border border-slate-100 rounded-xl p-4">
                        <p class="text-xs text-slate-500 uppercase">Intereses</p>
                        <p class="text-lg font-bold text-slate-800">{{ formatCurrency(summary.interest) }}</p>
                    </div>
                    <div class="bg-white border border-slate-100 rounded-xl p-4">
                        <p class="text-xs text-slate-500 uppercase">Mora</p>
                        <p class="text-lg font-bold text-slate-800">{{ formatCurrency(summary.late_fees) }}</p>
                    </div>
                    <div class="bg-white border border-slate-100 rounded-xl p-4">
                        <p class="text-xs text-slate-500 uppercase">Gastos Legales</p>
                        <p class="text-lg font-bold text-slate-800">{{ formatCurrency(summary.legal_fees) }}</p>
                    </div>
                </div>

                <div class="bg-slate-900 text-white rounded-xl p-5 flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase text-slate-300">Total a Pagar</p>
                        <p class="text-2xl font-extrabold">{{ formatCurrency(summary.total_due) }}</p>
                    </div>
                    <i class="fa-solid fa-file-invoice-dollar text-3xl text-emerald-300"></i>
                </div>
            </div>

            <DialogFooter class="sm:justify-between gap-3">
                <Button type="button" variant="secondary" @click="emit('update:open', false)" class="bg-slate-100 hover:bg-slate-200 text-slate-800">
                    Cerrar
                </Button>
                <a :href="printUrl" target="_blank" rel="noopener">
                    <Button type="button" class="bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm">
                        <i class="fa-solid fa-print mr-2"></i> Imprimir PDF
                    </Button>
                </a>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
