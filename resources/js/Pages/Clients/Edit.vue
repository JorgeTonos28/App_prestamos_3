<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { ref, watch, nextTick } from 'vue';
import WarningModal from '@/Components/WarningModal.vue';

const props = defineProps({
    client: Object,
});

const form = useForm({
    national_id: props.client.national_id,
    first_name: props.client.first_name,
    last_name: props.client.last_name,
    phone: props.client.phone,
    email: props.client.email,
    address: props.client.address,
    notes: props.client.notes,
    status: props.client.status,
});

// Local validation state
const showValidationError = ref(false);
const validationMessage = ref('');

// Watchers for Interactive Validation (Same as Create.vue)
watch(() => form.national_id, (newVal) => {
    const raw = newVal.replace(/\D/g, '');
    if (raw.length > 11) {
        validationMessage.value = 'La cédula no puede tener más de 11 dígitos.';
        showValidationError.value = true;

        const truncated = raw.substring(0, 11);
        const formatted = `${truncated.substring(0, 3)}-${truncated.substring(3, 10)}-${truncated.substring(10)}`;

        nextTick(() => {
            form.national_id = formatted;
        });
        return;
    }
    // Normal Formatting
    let v = raw;
    if (v.length > 3 && v.length <= 10) {
        form.national_id = `${v.substring(0, 3)}-${v.substring(3)}`;
    } else if (v.length > 10) {
        form.national_id = `${v.substring(0, 3)}-${v.substring(3, 10)}-${v.substring(10)}`;
    } else {
        if (form.national_id !== v) {
             form.national_id = v;
        }
    }
});

watch(() => form.phone, (newVal) => {
    if (!newVal) return;
    const raw = newVal.replace(/\D/g, '');
    if (raw.length > 10) {
        validationMessage.value = 'El teléfono no puede tener más de 10 dígitos.';
        showValidationError.value = true;

        const truncated = raw.substring(0, 10);
        const formatted = `${truncated.substring(0, 3)}-${truncated.substring(3, 6)}-${truncated.substring(6)}`;

        nextTick(() => {
            form.phone = formatted;
        });
        return;
    }
    // Normal Formatting
    let v = raw;
    if (v.length > 3 && v.length <= 6) {
        form.phone = `${v.substring(0, 3)}-${v.substring(3)}`;
    } else if (v.length > 6) {
        form.phone = `${v.substring(0, 3)}-${v.substring(3, 6)}-${v.substring(6)}`;
    } else {
        if (form.phone !== v) {
            form.phone = v;
        }
    }
});

const goBack = () => {
    window.history.back();
};

const submit = () => {
    // Client-side Validation (On Submit)
    if (form.national_id.replace(/\D/g, '').length !== 11) {
        validationMessage.value = 'La cédula debe tener exactamente 11 números.';
        showValidationError.value = true;
        return;
    }
    if (form.phone && form.phone.replace(/\D/g, '').length !== 10) {
        validationMessage.value = 'El teléfono debe tener exactamente 10 números.';
        showValidationError.value = true;
        return;
    }

    form.patch(route('clients.update', props.client.id));
};
</script>

<template>
    <Head title="Editar Cliente" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" @click="goBack" class="p-2 h-10 w-10 rounded-full hover:bg-slate-100 text-slate-500 cursor-pointer">
                    <i class="fa-solid fa-arrow-left"></i>
                </Button>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Cliente</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <WarningModal
                    :open="showValidationError"
                    @update:open="showValidationError = $event"
                    title="Error de Validación"
                    :message="validationMessage"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Información del Cliente</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="national_id">Cédula</Label>
                                    <Input
                                        id="national_id"
                                        v-model="form.national_id"
                                        required
                                        placeholder="000-0000000-0"
                                    />
                                    <span v-if="form.errors.national_id" class="text-sm text-red-500">{{ form.errors.national_id }}</span>
                                </div>
                                <div class="space-y-2">
                                    <Label for="phone">Teléfono</Label>
                                    <Input
                                        id="phone"
                                        v-model="form.phone"
                                        placeholder="809-000-0000"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="first_name">Nombre</Label>
                                    <Input id="first_name" v-model="form.first_name" required pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ ]+" title="Solo letras" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="last_name">Apellido</Label>
                                    <Input id="last_name" v-model="form.last_name" required pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ ]+" title="Solo letras" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="email">Email</Label>
                                <Input id="email" type="email" v-model="form.email" />
                            </div>

                            <div class="space-y-2">
                                <Label for="address">Dirección</Label>
                                <Input id="address" v-model="form.address" />
                            </div>

                             <div class="space-y-2">
                                <Label for="status">Estado</Label>
                                <select id="status" v-model="form.status" class="flex h-12 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <Label for="notes">Notas</Label>
                                <Input id="notes" v-model="form.notes" />
                            </div>

                            <div class="flex justify-end pt-4">
                                <Button type="submit" :disabled="form.processing" class="cursor-pointer">
                                    Actualizar Cliente
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
