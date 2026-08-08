<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent } from '@/Components/ui/card';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
});

const truthy = (value) => ['1', 'true', 'yes', 'on'].includes(String(value ?? '').toLowerCase());

const form = useForm({
    overdue_sms_enabled: truthy(props.settings.overdue_sms_enabled),
    overdue_sms_send_time: props.settings.overdue_sms_send_time || '08:05',
    overdue_sms_interval_days: Number(props.settings.overdue_sms_interval_days || 1),
    overdue_sms_messages_per_day: Number(props.settings.overdue_sms_messages_per_day || 1),
    overdue_sms_body: props.settings.overdue_sms_body || 'Hola {client_first_name}. Le recordamos que presenta un monto vencido de RD${amount_due} con {days_overdue} días de atraso. Favor regularizar su pago. Gracias.',
});

const submit = () => form.post(route('settings.update'), { preserveScroll: true });
</script>

<template>
    <Head title="Configuración SMS" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('settings.edit')" class="text-surface-500 hover:text-primary-600">
                    <i class="fa-solid fa-arrow-left"></i>
                </Link>
                <div>
                    <h2 class="font-bold text-2xl text-surface-800">Configuración SMS</h2>
                    <p class="text-sm text-surface-500">Recordatorios automáticos de pagos atrasados vía LabsMobile.</p>
                </div>
            </div>
        </template>

        <div class="max-w-3xl mx-auto py-6">
            <Card class="rounded-2xl shadow-sm border border-surface-100 overflow-hidden">
                <CardContent class="p-8">
                    <form @submit.prevent="submit" class="space-y-7">
                        <label class="flex items-center justify-between gap-4 rounded-2xl border border-surface-200 bg-surface-50 p-5 cursor-pointer">
                            <div>
                                <p class="font-semibold text-surface-800">Activar recordatorios SMS</p>
                                <p class="text-sm text-surface-500">El proveedor también debe estar habilitado mediante LABSMOBILE_ENABLED.</p>
                            </div>
                            <input type="checkbox" v-model="form.overdue_sms_enabled" class="h-5 w-5 rounded border-surface-300 text-primary-600 focus:ring-primary-500" />
                        </label>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="space-y-2">
                                <Label for="overdue_sms_send_time">Hora de envío</Label>
                                <Input id="overdue_sms_send_time" type="time" v-model="form.overdue_sms_send_time" />
                                <p v-if="form.errors.overdue_sms_send_time" class="text-xs text-danger-600">{{ form.errors.overdue_sms_send_time }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="overdue_sms_interval_days">Periodicidad (días)</Label>
                                <Input id="overdue_sms_interval_days" type="number" min="1" max="365" v-model.number="form.overdue_sms_interval_days" />
                                <p class="text-xs text-surface-500">1 = cada día de atraso; 2 = días 1, 3, 5, 7...</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="overdue_sms_messages_per_day">Mensajes por día</Label>
                                <Input id="overdue_sms_messages_per_day" type="number" min="1" max="5" v-model.number="form.overdue_sms_messages_per_day" />
                                <p class="text-xs text-surface-500">Máximo de SMS al cliente en un día elegible.</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-primary-100 bg-primary-50/50 p-4 text-sm text-primary-800">
                            Con <strong>Periodicidad = 1</strong> y <strong>Mensajes por día = 1</strong>, cada cliente recibe un SMS por cada día que permanezca atrasado.
                        </div>

                        <div class="space-y-2">
                            <Label for="overdue_sms_body">Texto del SMS</Label>
                            <textarea id="overdue_sms_body" v-model="form.overdue_sms_body" maxlength="1000" class="flex min-h-[150px] w-full rounded-xl border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"></textarea>
                            <p class="text-xs text-surface-500">Variables: {client_first_name}, {client_name}, {amount_due}, {days_overdue}, {loan_count}</p>
                            <p v-if="form.errors.overdue_sms_body" class="text-xs text-danger-600">{{ form.errors.overdue_sms_body }}</p>
                        </div>

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing" class="px-8">
                                <i class="fa-solid fa-save mr-2"></i> Guardar configuración SMS
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
