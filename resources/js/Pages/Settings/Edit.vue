<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent } from '@/Components/ui/card';

const props = defineProps({
    settings: Object
});

const form = useForm({
    app_name: props.settings.app_name || 'LendApp',
    logo: null,
    favicon: null,
});

const submit = () => {
    form.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('logo', 'favicon');
        },
    });
};
</script>

<template>
    <Head title="Configuración" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">Configuración del Sistema</h2>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto space-y-6">

                <Card class="rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                        <h3 class="font-bold text-lg text-slate-800">Identidad Visual</h3>
                        <p class="text-sm text-slate-500">Personaliza la apariencia de la aplicación.</p>
                    </div>
                    <CardContent class="p-8">
                        <form @submit.prevent="submit" class="space-y-8">

                            <div class="space-y-4">
                                <div>
                                    <Label for="app_name">Nombre de la Aplicación</Label>
                                    <Input id="app_name" v-model="form.app_name" class="max-w-md mt-1" />
                                </div>
                            </div>

                            <div class="h-px bg-slate-100"></div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-4">
                                    <Label for="logo">Logo Principal</Label>
                                    <div class="flex items-start gap-4">
                                        <div v-if="settings.logo_path" class="w-20 h-20 bg-slate-100 rounded-xl flex items-center justify-center border border-slate-200 p-2">
                                            <img :src="settings.logo_path" alt="Logo Actual" class="max-h-full max-w-full" />
                                        </div>
                                        <div v-else class="w-20 h-20 bg-slate-100 rounded-xl flex items-center justify-center border border-slate-200 text-slate-400">
                                            <i class="fa-solid fa-image text-2xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <Input id="logo" type="file" @input="form.logo = $event.target.files[0]" accept="image/*" class="mt-1" />
                                            <p class="text-xs text-slate-500 mt-2">
                                                Recomendado: 500x500px o formato cuadrado (PNG/SVG). Max 1MB.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <Label for="favicon">Favicon</Label>
                                    <div class="flex items-start gap-4">
                                        <div v-if="settings.favicon_path" class="w-12 h-12 bg-slate-100 rounded-lg flex items-center justify-center border border-slate-200 p-2">
                                            <img :src="settings.favicon_path" alt="Favicon Actual" class="max-h-full max-w-full" />
                                        </div>
                                        <div v-else class="w-12 h-12 bg-slate-100 rounded-lg flex items-center justify-center border border-slate-200 text-slate-400">
                                            <i class="fa-solid fa-globe"></i>
                                        </div>
                                        <div class="flex-1">
                                            <Input id="favicon" type="file" @input="form.favicon = $event.target.files[0]" accept="image/x-icon,image/png" class="mt-1" />
                                            <p class="text-xs text-slate-500 mt-2">
                                                Recomendado: 32x32px o 64x64px (ICO/PNG). Max 512KB.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-4">
                                <Button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md px-8">
                                    <i class="fa-solid fa-save mr-2"></i> Guardar Cambios
                                </Button>
                            </div>

                        </form>
                    </CardContent>
                </Card>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
