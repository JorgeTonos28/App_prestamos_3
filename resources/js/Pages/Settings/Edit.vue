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
    theme_palette: props.settings.theme_palette || 'default',
    logo: null,
    dark_logo: null,
    favicon: null,
    email_sender_name: props.settings.email_sender_name || 'LendApp Notifications',
    email_sender_address: props.settings.email_sender_address || 'noreply@lendapp.com',
    overdue_email_subject: props.settings.overdue_email_subject || 'Aviso de Atraso en Préstamo',
    overdue_email_body: props.settings.overdue_email_body || 'Estimado cliente, le recordamos que tiene cuotas vencidas en su préstamo. Por favor realice el pago lo antes posible.',
    sidebar_logo_height: props.settings.sidebar_logo_height || '40', // Default 40px
});

const submit = () => {
    form.post(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('logo', 'dark_logo', 'favicon');
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
                        <h3 class="font-bold text-lg text-slate-800">Apariencia</h3>
                        <p class="text-sm text-slate-500">Configura el nombre y la paleta visual del sistema.</p>
                    </div>
                    <CardContent class="p-8">
                        <form @submit.prevent="submit" class="space-y-8">

                            <div class="space-y-4">
                                <div>
                                    <Label for="app_name">Nombre de la Aplicación</Label>
                                    <Input id="app_name" v-model="form.app_name" class="max-w-md mt-1" />
                                </div>
                                <div>
                                    <Label for="theme_palette">Paleta de Colores</Label>
                                    <div class="mt-2 grid gap-3 md:grid-cols-2">
                                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 cursor-pointer hover:border-primary-300 transition-colors">
                                            <input
                                                type="radio"
                                                name="theme_palette"
                                                value="default"
                                                v-model="form.theme_palette"
                                                class="h-4 w-4 text-primary-600 focus:ring-primary-500"
                                            />
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-semibold text-slate-700">Default (Azul)</span>
                                                <div class="flex gap-1">
                                                    <span class="h-4 w-4 rounded-full bg-primary-500"></span>
                                                    <span class="h-4 w-4 rounded-full bg-primary-300"></span>
                                                    <span class="h-4 w-4 rounded-full bg-slate-200"></span>
                                                </div>
                                            </div>
                                        </label>
                                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 cursor-pointer hover:border-primary-300 transition-colors">
                                            <input
                                                type="radio"
                                                name="theme_palette"
                                                value="rose"
                                                v-model="form.theme_palette"
                                                class="h-4 w-4 text-primary-600 focus:ring-primary-500"
                                            />
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-semibold text-slate-700">Rosa</span>
                                                <div class="flex gap-1">
                                                    <span class="h-4 w-4 rounded-full bg-rose-500"></span>
                                                    <span class="h-4 w-4 rounded-full bg-rose-300"></span>
                                                    <span class="h-4 w-4 rounded-full bg-rose-100"></span>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2">
                                        Selecciona la paleta principal del sistema.
                                    </p>
                                </div>
                            </div>

                            <div class="h-px bg-slate-100"></div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-4">
                                    <Label for="logo">Logo Principal (Light Mode)</Label>
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
                                                Usado en Login y fondos claros.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <Label for="dark_logo">Logo Menú (Dark Mode)</Label>
                                    <div class="flex items-start gap-4">
                                        <div v-if="settings.dark_logo_path" class="w-20 h-20 bg-slate-800 rounded-xl flex items-center justify-center border border-slate-700 p-2">
                                            <img :src="settings.dark_logo_path" alt="Logo Dark" class="max-h-full max-w-full" />
                                        </div>
                                        <div v-else class="w-20 h-20 bg-slate-800 rounded-xl flex items-center justify-center border border-slate-700 text-slate-400">
                                            <i class="fa-solid fa-image text-2xl"></i>
                                        </div>
                                        <div class="flex-1">
                                            <Input id="dark_logo" type="file" @input="form.dark_logo = $event.target.files[0]" accept="image/*" class="mt-1" />
                                            <p class="text-xs text-slate-500 mt-2">
                                                Usado en la barra lateral oscura.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <Label for="sidebar_logo_height">Altura del Logo en Menú (px)</Label>
                                        <div class="flex items-center gap-2 mt-1">
                                            <Input id="sidebar_logo_height" type="number" v-model="form.sidebar_logo_height" class="w-24" min="20" max="120" />
                                            <span class="text-sm text-slate-500">px</span>
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

                            <div class="h-px bg-slate-100"></div>

                            <div class="space-y-4">
                                <h3 class="font-bold text-lg text-slate-800">Automatización de Correos</h3>
                                <p class="text-sm text-slate-500">Configura el envío automático de correos a clientes en mora.</p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="email_sender_name">Nombre del Remitente</Label>
                                        <Input id="email_sender_name" v-model="form.email_sender_name" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="email_sender_address">Correo del Remitente</Label>
                                        <Input id="email_sender_address" v-model="form.email_sender_address" type="email" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="overdue_email_subject">Asunto del Correo</Label>
                                    <Input id="overdue_email_subject" v-model="form.overdue_email_subject" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="overdue_email_body">Cuerpo del Mensaje</Label>
                                    <textarea
                                        id="overdue_email_body"
                                        v-model="form.overdue_email_body"
                                        class="flex min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    ></textarea>
                                    <p class="text-xs text-slate-500">Variables disponibles: {client_name}, {amount_due}, {days_overdue}</p>
                                </div>
                            </div>

                            <div class="flex justify-end pt-4">
                                <Button type="submit" :disabled="form.processing" class="bg-primary-600 hover:bg-primary-700 text-white rounded-xl shadow-md px-8 cursor-pointer">
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
