<script setup>
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { ref } from 'vue';
import axios from 'axios';

const emit = defineEmits(['close', 'success']);

const form = useForm({
    national_id: '',
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
});

const processing = ref(false);
const errors = ref({});

// Masking Helpers
const formatCedula = (value) => {
    // Remove non-digits
    let v = value.replace(/\D/g, '');
    // Limit length
    v = v.substring(0, 11);
    // Format: 000-0000000-0
    if (v.length > 3 && v.length <= 10) {
        return `${v.substring(0, 3)}-${v.substring(3)}`;
    } else if (v.length > 10) {
        return `${v.substring(0, 3)}-${v.substring(3, 10)}-${v.substring(10)}`;
    }
    return v;
};

const formatPhone = (value) => {
    // Remove non-digits
    let v = value.replace(/\D/g, '');
    // Limit length
    v = v.substring(0, 10);
    // Format: 809-000-0000
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

const submit = () => {
    processing.value = true;
    errors.value = {};
    form.clearErrors();

    // Custom Validation before submit
    if (form.national_id.replace(/\D/g, '').length !== 11) {
        errors.value.national_id = ['La cédula debe tener 11 dígitos.'];
        processing.value = false;
        return;
    }
    if (form.phone && form.phone.replace(/\D/g, '').length !== 10) {
        errors.value.phone = ['El teléfono debe tener 10 dígitos.'];
        processing.value = false;
        return;
    }

    axios.post(route('clients.store'), form.data(), {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => {
        form.reset();
        emit('success', response.data);
        emit('close');
    })
    .catch(error => {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
            Object.keys(error.response.data.errors).forEach(key => {
                form.setError(key, error.response.data.errors[key][0]);
            });
        } else {
            console.error(error);
        }
    })
    .finally(() => {
        processing.value = false;
    });
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="font-bold text-lg text-slate-800">Registrar Nuevo Cliente</h3>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="p-6 max-h-[80vh] overflow-y-auto">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Global Error Alert -->
                    <div v-if="Object.keys(errors).length > 0" class="bg-red-50 border border-red-100 rounded-xl p-4 mb-4">
                        <div class="flex items-start">
                             <i class="fa-solid fa-circle-exclamation text-red-500 mt-0.5 mr-2"></i>
                             <div>
                                 <h4 class="text-sm font-bold text-red-800">Por favor corrija los errores:</h4>
                                 <ul class="list-disc list-inside text-xs text-red-600 mt-1">
                                     <li v-for="(errs, field) in errors" :key="field">{{ Array.isArray(errs) ? errs[0] : errs }}</li>
                                 </ul>
                             </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label for="national_id">Cédula / ID <span class="text-red-500">*</span></Label>
                            <Input
                                id="national_id"
                                :model-value="form.national_id"
                                @input="onCedulaInput"
                                required
                                placeholder="001-0000000-0"
                                maxlength="13"
                            />
                            <p class="text-xs text-slate-500">Formato: 000-0000000-0</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label for="first_name">Nombres <span class="text-red-500">*</span></Label>
                            <Input id="first_name" v-model="form.first_name" required pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ ]+" title="Solo letras" />
                        </div>
                        <div class="space-y-2">
                            <Label for="last_name">Apellidos <span class="text-red-500">*</span></Label>
                            <Input id="last_name" v-model="form.last_name" required pattern="[A-Za-zÁÉÍÓÚáéíóúñÑ ]+" title="Solo letras" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label for="phone">Teléfono</Label>
                            <Input
                                id="phone"
                                :model-value="form.phone"
                                @input="onPhoneInput"
                                placeholder="809-000-0000"
                                maxlength="12"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="email">Email</Label>
                            <Input id="email" type="email" v-model="form.email" placeholder="cliente@ejemplo.com" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="address">Dirección</Label>
                        <Input id="address" v-model="form.address" placeholder="Calle Principal #123..." />
                    </div>

                    <div class="space-y-2">
                        <Label for="notes">Notas</Label>
                        <Input id="notes" v-model="form.notes" placeholder="Información adicional..." />
                    </div>

                    <div class="flex justify-end space-x-3 pt-4 border-t border-slate-100">
                        <Button type="button" variant="ghost" @click="$emit('close')" class="text-slate-500 hover:text-slate-700">
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="processing" class="bg-blue-600 hover:bg-blue-700 text-white shadow-md rounded-xl px-6">
                            Guardar Cliente
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
