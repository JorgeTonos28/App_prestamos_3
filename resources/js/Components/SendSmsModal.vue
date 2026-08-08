<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import Modal from '@/Components/Modal.vue';
import SmsComposer from '@/Components/SmsComposer.vue';

const props = defineProps({
    client: { type: Object, required: true },
    loan: { type: Object, default: null },
    buttonClass: { type: String, default: '' },
    buttonLabel: { type: String, default: 'Enviar SMS' },
    variant: { type: String, default: 'default' },
});

const page = usePage();
const open = ref(false);
const clients = computed(() => [props.client]);
const contextLabel = computed(() => props.loan
    ? `El mensaje quedará asociado al préstamo ${props.loan.code}.`
    : 'El mensaje quedará asociado al perfil del cliente.');
const smsPricing = computed(() => page.props.smsPricing || {});

const openModal = (event) => {
    event?.stopPropagation?.();
    open.value = true;
};
</script>

<template>
    <Button
        type="button"
        :variant="variant"
        :class="buttonClass"
        :disabled="!client.phone"
        :title="client.phone ? buttonLabel : 'El cliente no tiene un teléfono registrado'"
        @click.stop="openModal"
    >
        <i class="fa-solid fa-comment-sms mr-2"></i>{{ buttonLabel }}
    </Button>

    <Modal :show="open" max-width="lg" @close="open = false">
        <div class="border-b border-surface-100 px-6 py-5">
            <h3 class="text-lg font-bold text-surface-800">Enviar SMS</h3>
            <p class="mt-1 text-sm text-surface-500">{{ client.first_name }} {{ client.last_name }} - {{ client.phone }}</p>
        </div>
        <div class="p-6">
            <SmsComposer
                :clients="clients"
                :initial-client-id="client.id"
                :loan-id="loan?.id"
                :context-label="contextLabel"
                :test-mode="Boolean(smsPricing.test_mode ?? true)"
                :credit-rate="Number(smsPricing.credit_rate || 0)"
                :cost-per-credit-dop="Number(smsPricing.cost_per_credit_dop || 0)"
                @sent="open = false"
            />
        </div>
    </Modal>
</template>
