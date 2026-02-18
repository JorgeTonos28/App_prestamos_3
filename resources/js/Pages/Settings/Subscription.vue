<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { loadStripe } from '@stripe/stripe-js';
import { onMounted, ref } from 'vue';

const props = defineProps({
    plans: Array,
    currency: String,
    currentPlanPriceId: String,
    status: String,
    stripeKey: String,
    invoices: Array,
});

const selectedPlan = ref(props.plans?.[0]?.key ?? 'monthly');
const cardElementContainer = ref(null);
const cardReady = ref(false);
let stripe = null;
let elements = null;
let card = null;

const paymentForm = useForm({ payment_method: '' });
const subscribeForm = useForm({ plan: selectedPlan.value });

const formatMoney = (amount) => new Intl.NumberFormat('es-DO', { style: 'currency', currency: props.currency || 'DOP' }).format((amount || 0) / 100);

onMounted(async () => {
    if (!props.stripeKey || !cardElementContainer.value) return;

    stripe = await loadStripe(props.stripeKey);
    const setup = await window.axios.post(route('settings.subscription.setup-intent'));
    elements = stripe.elements({ clientSecret: setup.data.client_secret });
    card = elements.create('payment');
    card.mount(cardElementContainer.value);
    cardReady.value = true;
});

const savePaymentMethod = async () => {
    if (!stripe || !card) return;

    const { error, setupIntent } = await stripe.confirmSetup({
        elements,
        redirect: 'if_required',
    });

    if (error) {
        paymentForm.setError('payment_method', error.message);
        return;
    }

    paymentForm.payment_method = setupIntent.payment_method;
    paymentForm.post(route('settings.subscription.payment-method'));
};

const subscribe = () => {
    subscribeForm.plan = selectedPlan.value;
    subscribeForm.post(route('settings.subscription.subscribe'));
};
</script>

<template>
    <Head title="Suscripción" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-surface-800 leading-tight">Suscripción y Facturación</h2>
        </template>

        <div class="space-y-6 py-6">
            <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                <p class="text-sm text-surface-500">Estado actual</p>
                <p class="mt-2 text-lg font-semibold" :class="status === 'active' ? 'text-green-600' : status === 'grace_period' ? 'text-amber-600' : 'text-red-600'">
                    {{ status === 'active' ? 'Activa' : status === 'grace_period' ? 'Periodo de Gracia' : 'Vencida' }}
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-4">
                <button v-for="plan in plans" :key="plan.key" @click="selectedPlan = plan.key" class="rounded-2xl border bg-white p-5 text-left shadow-sm transition" :class="selectedPlan === plan.key ? 'border-primary-500 ring-2 ring-primary-100' : 'border-surface-200 hover:border-primary-300'">
                    <p class="text-sm font-semibold text-surface-500">{{ plan.label }}</p>
                    <p class="mt-2 text-2xl font-black text-surface-900">{{ formatMoney(plan.final_price) }}</p>
                    <p class="text-xs text-surface-500">{{ plan.months }} mes(es)</p>
                    <p class="mt-3 text-xs text-green-600 font-semibold">Ahorras {{ formatMoney(plan.savings) }} ({{ plan.discount_percent }}%)</p>
                </button>
            </div>

            <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-surface-800">Método de Pago</h3>
                <div ref="cardElementContainer" class="mt-4 rounded-xl border border-surface-200 p-3"></div>
                <p v-if="paymentForm.errors.payment_method" class="mt-2 text-sm text-red-600">{{ paymentForm.errors.payment_method }}</p>
                <button :disabled="!cardReady || paymentForm.processing" @click="savePaymentMethod" class="mt-4 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Guardar tarjeta</button>
                <button :disabled="subscribeForm.processing" @click="subscribe" class="mt-4 ml-2 rounded-xl bg-surface-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Activar / Cambiar plan</button>
            </div>

            <div class="rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-surface-800">Historial de Facturas</h3>
                <table class="mt-4 w-full text-sm">
                    <thead>
                        <tr class="text-left text-surface-500">
                            <th class="py-2">Factura</th>
                            <th class="py-2">Fecha</th>
                            <th class="py-2">Estado</th>
                            <th class="py-2 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices" :key="invoice.id" class="border-t border-surface-100">
                            <td class="py-3">{{ invoice.number }}</td>
                            <td class="py-3">{{ invoice.date }}</td>
                            <td class="py-3 capitalize">{{ invoice.status }}</td>
                            <td class="py-3 text-right">
                                <a :href="route('settings.subscription.invoices.download', invoice.id)" class="text-primary-600 hover:text-primary-700 font-semibold">Descargar PDF</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
