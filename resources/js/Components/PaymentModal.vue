<script setup>
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/Components/ui/dialog';
import WarningModal from '@/Components/WarningModal.vue';
import { ref, watch } from 'vue';

const props = defineProps({
  open: Boolean,
  loan: Object,
});

const emit = defineEmits(['update:open']);

const showWarningModal = ref(false);
const warningMessage = ref('');

const paymentForm = useForm({
    amount: '',
    method: 'cash',
    reference: '',
    notes: '',
    paid_at: getTodayDateString()
});

function getTodayDateString() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0);
};

// Interactive Validation Watcher
watch(() => paymentForm.amount, (newVal) => {
    if (!newVal) return;
    const amount = parseFloat(newVal);
    const maxBalance = parseFloat(props.loan.balance_total);

    if (!isNaN(amount) && amount > maxBalance) {
        warningMessage.value = `El monto ingresado (${formatCurrency(amount)}) no puede ser mayor al balance total de la deuda (${formatCurrency(maxBalance)}).`;
        showWarningModal.value = true;
        paymentForm.amount = '';
    }
});

const submitPayment = () => {
    paymentForm.post(route('loans.payments.store', props.loan.id), {
        onSuccess: () => {
            emit('update:open', false);
            paymentForm.reset();
            paymentForm.paid_at = getTodayDateString();
        }
    });
};

const handleOpenChange = (value) => {
    emit('update:open', value);
    if (!value) {
        // Optional: Reset form when closing?
        // paymentForm.reset();
    }
};
</script>

<template>
  <Dialog :open="open" @update:open="handleOpenChange">
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle>Registrar Pago</DialogTitle>
      </DialogHeader>

      <div v-if="$page.props.errors.paid_at" class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4 border border-red-100">
          {{ $page.props.errors.paid_at }}
      </div>

      <form @submit.prevent="submitPayment" class="space-y-4">
        <div class="grid gap-4 py-4">
            <div class="grid gap-2">
                <Label class="text-slate-600">Fecha Pago</Label>
                <Input type="date" v-model="paymentForm.paid_at" :max="getTodayDateString()" class="bg-slate-50" />
                <p class="text-xs text-slate-400">Puede registrar pagos pasados si no existen pagos posteriores.</p>
            </div>
            <div class="grid gap-2">
                <Label for="amount" class="text-slate-600">Monto</Label>
                <div class="relative">
                    <span class="absolute left-3 top-2 text-slate-400 font-bold">$</span>
                    <Input id="amount" type="number" step="0.01" v-model="paymentForm.amount" class="pl-7" required autofocus placeholder="0.00" />
                </div>
                <div v-if="paymentForm.errors.amount" class="text-sm text-red-500">{{ paymentForm.errors.amount }}</div>
            </div>
            <div class="grid gap-2">
                <Label for="method" class="text-slate-600">Método</Label>
                <select id="method" v-model="paymentForm.method" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                    <option value="cash">Efectivo</option>
                    <option value="transfer">Transferencia</option>
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="reference" class="text-slate-600">Referencia (Opcional)</Label>
                <Input id="reference" v-model="paymentForm.reference" placeholder="Ej: #123456" />
            </div>
        </div>

        <DialogFooter>
            <Button type="button" variant="ghost" @click="emit('update:open', false)">Cancelar</Button>
            <Button type="submit" :disabled="paymentForm.processing">
                Confirmar Pago
            </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>

  <WarningModal
        :open="showWarningModal"
        @update:open="showWarningModal = $event"
        title="Monto Excedido"
        :message="warningMessage"
    />
</template>
