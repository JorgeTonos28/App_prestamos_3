<script setup>
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { ref } from 'vue';

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

const submit = () => {
    form.post(route('clients.store'), {
        onSuccess: () => {
            form.reset();
            emit('success');
            emit('close');
        },
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label for="national_id">Cédula / ID <span class="text-red-500">*</span></Label>
                            <Input id="national_id" v-model="form.national_id" required placeholder="001-0000000-0" />
                            <p v-if="form.errors.national_id" class="text-xs text-red-500">{{ form.errors.national_id }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label for="first_name">Nombres <span class="text-red-500">*</span></Label>
                            <Input id="first_name" v-model="form.first_name" required />
                            <p v-if="form.errors.first_name" class="text-xs text-red-500">{{ form.errors.first_name }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="last_name">Apellidos <span class="text-red-500">*</span></Label>
                            <Input id="last_name" v-model="form.last_name" required />
                            <p v-if="form.errors.last_name" class="text-xs text-red-500">{{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <Label for="phone">Teléfono</Label>
                            <Input id="phone" v-model="form.phone" placeholder="(809) 000-0000" />
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
                        <Button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white shadow-md rounded-xl px-6">
                            Guardar Cliente
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
