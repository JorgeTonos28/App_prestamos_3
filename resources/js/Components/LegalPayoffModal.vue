<script setup>
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { computed, ref, watch } from 'vue';

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

const pendingInterestToDate = computed(() => Number(props.summary?.interest_at_cutoff ?? 0));
const pendingInterestDays = computed(() => Number(props.summary?.interest_next_cut_days ?? 0));
const pendingLateFeesToDate = computed(() => Number(props.summary?.late_fees_pending_to_date ?? 0));
const pendingLateFeeDays = computed(() => Number(props.summary?.late_fees_pending_days ?? 0));

const summaryMode = ref('to_date');

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        summaryMode.value = 'to_date';
    }
});

const isToDateMode = computed(() => summaryMode.value === 'to_date');
const totalDueDisplay = computed(() => isToDateMode.value
    ? Number(props.summary?.total_due_to_date ?? props.summary?.total_due ?? 0)
    : Number(props.summary?.total_due ?? 0)
);
const printHref = computed(() => {
    const separator = props.printUrl?.includes('?') ? '&' : '?';
    return `${props.printUrl}${separator}mode=${summaryMode.value}`;
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="legal-payoff-modal w-[calc(100vw-1rem)] max-w-[820px] max-h-[calc(100vh-1rem)] gap-2.5 overflow-hidden bg-white p-3 sm:w-full sm:p-4">
            <DialogHeader>
                <DialogTitle class="text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-emerald-600"></i>
                    Resumen de Pago - {{ loan.code }}
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-2.5">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-sm font-medium leading-none transition-colors"
                        :class="isToDateMode ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        @click="summaryMode = 'to_date'"
                    >
                        A la fecha
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-sm font-medium leading-none transition-colors"
                        :class="!isToDateMode ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        @click="summaryMode = 'cutoff'"
                    >
                        Al corte
                    </button>
                </div>

                <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-3">
                    <p class="text-sm text-emerald-700">Cliente</p>
                    <h3 class="text-base font-bold text-emerald-900 sm:text-lg">{{ loan.client.first_name }} {{ loan.client.last_name }}</h3>
                    <p class="text-xs text-emerald-600">{{ loan.client.national_id }}</p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-100 bg-white p-3">
                        <p class="text-xs text-slate-500 uppercase">Capital</p>
                        <p class="text-base font-bold text-slate-800 sm:text-lg">{{ formatCurrency(summary.principal) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-white p-3">
                        <p class="text-xs text-slate-500 uppercase">Intereses</p>
                        <p class="text-base font-bold text-slate-800 sm:text-lg">{{ formatCurrency(summary.interest) }}</p>
                        <p v-if="isToDateMode" class="mt-1.5 text-xs font-medium leading-snug text-sky-600">
                            Interes al proximo corte:
                            <span class="font-semibold">{{ formatCurrency(pendingInterestToDate) }}</span>
                            <span v-if="pendingInterestDays > 0">({{ pendingInterestDays }} dias)</span>
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-white p-3">
                        <p class="text-xs text-slate-500 uppercase">Mora</p>
                        <p class="text-base font-bold text-slate-800 sm:text-lg">{{ formatCurrency(summary.late_fees) }}</p>
                        <p v-if="isToDateMode" class="mt-1.5 text-xs font-medium leading-snug text-amber-600">
                            Mora al proximo corte:
                            <span class="font-semibold">{{ formatCurrency(pendingLateFeesToDate) }}</span>
                            <span v-if="pendingLateFeeDays > 0">({{ pendingLateFeeDays }} dias)</span>
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-white p-3">
                        <p class="text-xs text-slate-500 uppercase">Gastos Legales</p>
                        <p class="text-base font-bold text-slate-800 sm:text-lg">{{ formatCurrency(summary.legal_fees) }}</p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-900 px-4 py-3.5 text-white">
                    <div>
                        <p class="text-xs uppercase text-slate-300">Total a Pagar</p>
                        <p class="text-xl font-extrabold leading-tight sm:text-2xl">{{ formatCurrency(totalDueDisplay) }}</p>
                        <p v-if="isToDateMode" class="mt-1 text-xs text-slate-300">Incluye interes y mora pendientes a la fecha.</p>
                    </div>
                    <i class="fa-solid fa-file-invoice-dollar text-2xl text-emerald-300 sm:text-3xl"></i>
                </div>
            </div>

            <DialogFooter class="flex-col-reverse gap-2 sm:flex-row sm:justify-between sm:gap-3">
                <Button type="button" variant="secondary" @click="emit('update:open', false)" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-800 sm:w-auto">
                    Cerrar
                </Button>
                <a :href="printHref" target="_blank" rel="noopener" class="w-full sm:w-auto">
                    <Button type="button" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm sm:w-auto">
                        <i class="fa-solid fa-print mr-2"></i> Imprimir PDF
                    </Button>
                </a>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.legal-payoff-modal {
    top: 50%;
}

@media (max-height: 900px) {
    .legal-payoff-modal {
        padding: 0.875rem;
    }

    .legal-payoff-modal :deep([data-slot="dialog-title"]) {
        font-size: 1.05rem;
        line-height: 1.3;
    }
}

@media (max-height: 820px) {
    .legal-payoff-modal {
        max-width: 780px;
        padding: 0.75rem;
    }
}

@media (max-height: 760px) {
    .legal-payoff-modal {
        width: calc(100vw - 0.75rem);
        max-width: 740px;
        max-height: calc(100vh - 0.5rem);
        padding: 0.625rem;
        gap: 0.5rem;
    }
}
</style>
