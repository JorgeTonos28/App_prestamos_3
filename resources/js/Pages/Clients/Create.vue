<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { ref, watch, nextTick } from 'vue';
import WarningModal from '@/Components/WarningModal.vue';

const form = useForm({
    national_id: '',
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    address: '',
    notes: ''
});

// Local validation state
const showValidationError = ref(false);
const validationMessage = ref('');

// Watchers for Interactive Validation
watch(() => form.phone, (newVal) => {
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
    if (!form.national_id?.trim()) {
        validationMessage.value = 'La identificación es obligatoria.';
        showValidationError.value = true;
        return;
    }
    if (form.phone && form.phone.replace(/\D/g, '').length !== 10) {
        validationMessage.value = 'El teléfono debe tener exactamente 10 números.';
        showValidationError.value = true;
        return;
    }

    form.post(route('clients.store'));
};
</script>

<template>
    <Head title="Crear Cliente" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" @click="goBack" class="p-2 h-10 w-10 rounded-full hover:bg-slate-100 text-slate-500">
                    <i class="fa-solid fa-arrow-left"></i>
                </Button>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar Cliente</h2>
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
                                    <Label for="national_id">Identificación (Cédula o Pasaporte)</Label>
                                    <Input
                                        id="national_id"
                                        v-model="form.national_id"
                                        required
                                        placeholder="Ej: 00000000000 o A1234567"
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
                                <Label for="email">Email (Opcional)</Label>
                                <Input id="email" type="email" v-model="form.email" />
                            </div>

                            <div class="space-y-2">
                                <Label for="address">Dirección</Label>
                                <Input id="address" v-model="form.address" />
                            </div>

                            <div class="space-y-2">
                                <Label for="notes">Notas</Label>
                                <Input id="notes" v-model="form.notes" />
                            </div>

                            <div class="flex justify-end pt-4">
                                <Button type="submit" :disabled="form.processing" class="!cursor-pointer">
                                    Guardar Cliente
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
