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
    testMode: { type: Boolean, default: true },
    creditRate: { type: Number, default: 0 },
    costPerCreditDop: { type: Number, default: 0 },
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

const messageProfile = computed(() => {
    const message = form.message || '';
    const unicode = /[^\u000A\u000D\u0020-\u007E£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¤¡ÄÖÑÜ§¿äöñüà€]/u.test(message);
    const characters = [...message].length;

    if (unicode) {
        return {
            unicode,
            characters,
            segments: characters <= 70 ? 1 : Math.ceil(characters / 67),
        };
    }

    const extended = (message.match(/[\^{}\[\]~|€\\]/gu) || []).length;
    const septets = characters + extended;

    return {
        unicode,
        characters,
        segments: septets <= 160 ? 1 : Math.ceil(septets / 153),
    };
});

const estimatedCredits = computed(() => props.testMode
    ? 0
    : messageProfile.value.segments * Number(props.creditRate || 0));
const estimatedCostDop = computed(() => estimatedCredits.value * Number(props.costPerCreditDop || 0));

const formatNumber = (value, digits = 3) => new Intl.NumberFormat('es-DO', {
    minimumFractionDigits: 0,
    maximumFractionDigits: digits,
}).format(Number(value || 0));

const formatDop = (value) => new Intl.NumberFormat('es-DO', {
    style: 'currency',
    currency: 'DOP',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value || 0));

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
        </div>

        <div class="rounded-xl border border-surface-200 bg-surface-50 p-4">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                <span class="font-semibold text-surface-800">
                    <i class="fa-solid fa-message mr-1.5 text-primary-600"></i>
                    {{ messageProfile.segments }} SMS
                </span>
                <span class="text-surface-600">{{ messageProfile.characters }} caracteres</span>
                <span class="text-surface-600">{{ messageProfile.unicode ? 'Unicode' : 'GSM' }}</span>
                <span v-if="testMode" class="font-semibold text-info-700">Modo prueba: 0 créditos</span>
                <template v-else>
                    <span v-if="creditRate > 0" class="font-semibold text-surface-800">≈ {{ formatNumber(estimatedCredits, 4) }} créditos</span>
                    <span v-if="creditRate > 0 && costPerCreditDop > 0" class="font-semibold text-success-700">≈ {{ formatDop(estimatedCostDop) }}</span>
                </template>
            </div>
            <p v-if="messageProfile.unicode" class="mt-2 text-xs text-warning-700">
                Este texto usa Unicode. Un SMS Unicode admite menos caracteres y puede dividirse en más segmentos.
            </p>
            <p v-else-if="messageProfile.segments > 1" class="mt-2 text-xs text-warning-700">
                El texto se enviará concatenado en {{ messageProfile.segments }} segmentos SMS.
            </p>
            <p v-if="!testMode && creditRate <= 0" class="mt-2 text-xs text-surface-500">
                La tarifa de créditos de República Dominicana aún no está disponible; el número de SMS sí es exacto.
            </p>
            <p v-else-if="!testMode && costPerCreditDop <= 0" class="mt-2 text-xs text-surface-500">
                Configura el costo de un crédito LabsMobile en RD$ para ver el costo monetario estimado.
            </p>
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing || !form.client_id || !form.message.trim()" class="rounded-xl bg-primary-600 px-6 text-white hover:bg-primary-700">
                <i class="fa-solid fa-paper-plane mr-2"></i>
                {{ form.processing ? 'Enviando...' : `Enviar ${messageProfile.segments} SMS` }}
            </Button>
        </div>
    </form>
</template>
