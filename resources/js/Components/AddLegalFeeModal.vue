<script setup>
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    open: Boolean,
    loanId: Number,
});

const emit = defineEmits(['update:open']);

const form = useForm({
    amount: '',
    occurred_at: '',
    notes: '',
});

const submit = () => {
    form.post(route('loans.legal-fees.store', props.loanId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('update:open', false);
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg bg-white">
            <DialogHeader>
                <DialogTitle class="text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-scale-balanced text-amber-600"></i>
                    Agregar gasto legal
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="legal_fee_amount_modal">Monto (RD$)</Label>
                    <Input id="legal_fee_amount_modal" type="number" step="0.01" min="0" v-model="form.amount" required />
                    <p v-if="form.errors.amount" class="text-xs text-red-500">{{ form.errors.amount }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="legal_fee_date_modal">Fecha</Label>
                    <Input id="legal_fee_date_modal" type="date" v-model="form.occurred_at" />
                </div>
                <div class="space-y-2">
                    <Label for="legal_fee_notes_modal">Descripción</Label>
                    <Input id="legal_fee_notes_modal" type="text" v-model="form.notes" required placeholder="Describe el motivo del gasto legal" />
                    <p v-if="form.errors.notes" class="text-xs text-red-500">{{ form.errors.notes }}</p>
                </div>

                <DialogFooter class="sm:justify-end gap-3">
                    <Button type="button" variant="secondary" @click="emit('update:open', false)" class="bg-slate-100 hover:bg-slate-200 text-slate-800">
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="bg-amber-600 hover:bg-amber-700 text-white shadow-sm">
                        <i class="fa-solid fa-plus mr-2"></i> Agregar
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
