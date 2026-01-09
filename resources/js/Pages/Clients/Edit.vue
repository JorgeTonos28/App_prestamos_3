<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

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

const submit = () => {
    form.patch(route('clients.update', props.client.id));
};
</script>

<template>
    <Head title="Editar Cliente" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar Cliente</h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <CardTitle>Información del Cliente</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="national_id">Cédula</Label>
                                    <Input id="national_id" v-model="form.national_id" required />
                                    <span v-if="form.errors.national_id" class="text-sm text-red-500">{{ form.errors.national_id }}</span>
                                </div>
                                <div class="space-y-2">
                                    <Label for="phone">Teléfono</Label>
                                    <Input id="phone" v-model="form.phone" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="first_name">Nombre</Label>
                                    <Input id="first_name" v-model="form.first_name" required />
                                </div>
                                <div class="space-y-2">
                                    <Label for="last_name">Apellido</Label>
                                    <Input id="last_name" v-model="form.last_name" required />
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
                                <select id="status" v-model="form.status" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <Label for="notes">Notas</Label>
                                <Input id="notes" v-model="form.notes" />
                            </div>

                            <div class="flex justify-end pt-4">
                                <Button type="submit" :disabled="form.processing">
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
