<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { ref } from 'vue';

const form = useForm({
    national_id: '',
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    address: '',
    notes: ''
});

// Masking Helpers
const formatCedula = (value) => {
    let v = value.replace(/\D/g, '');
    v = v.substring(0, 11);
    if (v.length > 3 && v.length <= 10) {
        return `${v.substring(0, 3)}-${v.substring(3)}`;
    } else if (v.length > 10) {
        return `${v.substring(0, 3)}-${v.substring(3, 10)}-${v.substring(10)}`;
    }
    return v;
};

const formatPhone = (value) => {
    let v = value.replace(/\D/g, '');
    v = v.substring(0, 10);
    if (v.length > 3 && v.length <= 6) {
        return `${v.substring(0, 3)}-${v.substring(3)}`;
    } else if (v.length > 6) {
        return `${v.substring(0, 3)}-${v.substring(3, 6)}-${v.substring(6)}`;
    }
    return v;
};

// Input Handlers
const onCedulaInput = (e) => {
    form.national_id = formatCedula(e.target.value);
};

const onPhoneInput = (e) => {
    form.phone = formatPhone(e.target.value);
};

// Local validation state
const showValidationError = ref(false);
const validationMessage = ref('');

const goBack = () => {
    window.history.back();
};

const submit = () => {
    // Client-side Validation
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
                <!-- Validation Modal -->
                <div v-if="showValidationError" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div class="bg-white rounded-xl p-6 shadow-xl max-w-sm w-full mx-4">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center text-red-600 mx-auto mb-4">
                                <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                            </div>
                            <h3 class="font-bold text-lg text-slate-800 mb-2">Error de Formato</h3>
                            <p class="text-slate-600 text-sm mb-6">{{ validationMessage }}</p>
                            <Button @click="showValidationError = false" class="w-full bg-slate-800 text-white">
                                Entendido
                            </Button>
                        </div>
                    </div>
                </div>

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
                                        :model-value="form.national_id"
                                        @input="onCedulaInput"
                                        required
                                        placeholder="000-0000000-0"
                                    />
                                    <span v-if="form.errors.national_id" class="text-sm text-red-500">{{ form.errors.national_id }}</span>
                                </div>
                                <div class="space-y-2">
                                    <Label for="phone">Teléfono</Label>
                                    <Input
                                        id="phone"
                                        :model-value="form.phone"
                                        @input="onPhoneInput"
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
                                <Button type="submit" :disabled="form.processing">
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
