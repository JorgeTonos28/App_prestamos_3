<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';

const props = defineProps({
    clients: { type: Array, default: () => [] },
    initialClientId: { type: [Number, String], default: null },
    loanId: { type: [Number, String], default: null },
    contextLabel: { type: String, default: '' },
});

const emit = defineEmits(['sent']);

const form = useForm({
    client_id: props.initialClientId || '',
    loan_id: props.loanId || null,
    message: '',
});

watch(() => props.initialClientId, (value) => {
    form.client_id = value || '';
});

watch(() => props.loanId, (value) => {
    form.loan_id = value || null;
});

const selectedClient = computed(() => props.clients.find((client) => Number(client.id) === Number(form.client_id)));
const characterCount = computed(() => [...form.message].length);

const submit = () => {
    form.post(route('sms.send'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('message');
            emit('sent');
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-5">
        <div v-if="contextLabel" class="rounded-xl border border-primary-100 bg-primary-50/60 px-4 py-3 text-sm text-primary-800">
            {{ contextLabel }}
        </div>

        <div class="space-y-2">
            <Label for="sms_client_id">Cliente</Label>
            <select
                id="sms_client_id"
                v-model="form.client_id"
                :disabled="clients.length === 1"
                class="flex h-11 w-full rounded-xl border border-surface-200 bg-white px-3 py-2 text-sm text-surface-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:bg-surface-50 disabled:text-surface-600"
            >
                <option value="" disabled>Selecciona un cliente</option>
                <option v-for="client in clients" :key="client.id" :value="client.id">
                    {{ client.client_code ? `${client.client_code} - ` : '' }}{{ client.first_name }} {{ client.last_name }} ({{ client.phone || 'sin teléfono' }})
                </option>
            </select>
            <p v-if="form.errors.client_id" class="text-xs text-danger-600">{{ form.errors.client_id }}</p>
            <p v-else-if="selectedClient" class="text-xs text-surface-500">Destino: {{ selectedClient.phone }}</p>
        </div>

        <div class="space-y-2">
            <div class="flex items-center justify-between gap-4">
                <Label for="sms_message">Mensaje</Label>
                <span class="text-xs tabular-nums text-surface-500">{{ characterCount }}/1000</span>
            </div>
            <textarea
                id="sms_message"
                v-model="form.message"
                rows="5"
                maxlength="1000"
                required
                class="flex w-full rounded-xl border border-surface-200 bg-white px-3 py-3 text-sm text-surface-800 placeholder:text-surface-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                placeholder="Escribe un mensaje claro y directo para el cliente."
            ></textarea>
            <p v-if="form.errors.message" class="text-xs text-danger-600">{{ form.errors.message }}</p>
            <p v-else class="text-xs text-surface-500">Los mensajes largos o con caracteres Unicode pueden consumir más de un crédito en modo real.</p>
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing || !form.client_id || !form.message.trim()" class="rounded-xl bg-primary-600 px-6 text-white hover:bg-primary-700">
                <i class="fa-solid fa-paper-plane mr-2"></i>
                {{ form.processing ? 'Enviando...' : 'Enviar SMS' }}
            </Button>
        </div>
    </form>
</template>
