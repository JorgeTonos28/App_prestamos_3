<script setup>
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

defineProps({
    form: { type: Object, required: true },
    settings: { type: Object, required: true },
});
</script>

<template>
    <div class="space-y-8">
        <div>
            <h3 class="text-lg font-bold text-surface-800">Identidad visual</h3>
            <p class="mt-1 text-sm text-surface-500">Nombre, tema e imágenes utilizadas en la aplicación.</p>
        </div>

        <div class="space-y-2 max-w-md">
            <Label for="app_name">Nombre de la aplicación</Label>
            <Input id="app_name" v-model="form.app_name" />
        </div>

        <div class="space-y-3 max-w-2xl">
            <Label>Tema de color</Label>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label v-for="theme in [{ value: 'default', label: 'Default', help: 'Clásico azul y neutro.' }, { value: 'carolina', label: 'Carolina', help: 'Rosa y violeta con mariposas.' }]" :key="theme.value" class="cursor-pointer rounded-2xl border p-4 transition-colors" :class="form.color_theme === theme.value ? 'border-primary-400 bg-primary-50' : 'border-surface-200 bg-white hover:border-surface-300'">
                    <input v-model="form.color_theme" type="radio" name="color_theme" :value="theme.value" class="sr-only" />
                    <p class="font-semibold text-surface-800">{{ theme.label }}</p>
                    <p class="mt-1 text-xs text-surface-500">{{ theme.help }}</p>
                </label>
            </div>
        </div>

        <div v-if="form.color_theme === 'carolina'" class="space-y-4 rounded-2xl border border-primary-200 bg-primary-50/60 p-5">
            <label class="flex cursor-pointer items-center justify-between gap-4">
                <div>
                    <p class="font-semibold text-primary-800">Mariposa mascota</p>
                    <p class="text-xs text-primary-700">Aparece de forma ocasional cuando el tema Carolina está activo.</p>
                </div>
                <input v-model="form.butterfly_enabled" type="checkbox" class="h-5 w-5 rounded border-primary-300 text-primary-600 focus:ring-primary-500" />
            </label>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="butterfly_color">Color</Label>
                    <select id="butterfly_color" v-model="form.butterfly_color" :disabled="!form.butterfly_enabled" class="h-11 w-full rounded-xl border border-primary-200 bg-white px-3 text-sm disabled:opacity-60">
                        <option value="rose">Rose Bloom</option>
                        <option value="violet">Lavender Glow</option>
                        <option value="sunset">Coral Sunset</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <Label for="butterfly_interval_seconds">Frecuencia (segundos)</Label>
                    <Input id="butterfly_interval_seconds" v-model.number="form.butterfly_interval_seconds" type="number" min="10" max="120" :disabled="!form.butterfly_enabled" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 border-t border-surface-100 pt-8 md:grid-cols-2">
            <div class="space-y-2">
                <Label for="logo">Logo principal</Label>
                <img v-if="settings.logo_path" :src="settings.logo_path" alt="Logo principal actual" class="h-16 max-w-48 rounded-xl border border-surface-200 bg-surface-50 object-contain p-2" />
                <Input id="logo" type="file" accept="image/*" @input="form.logo = $event.target.files[0]" />
            </div>
            <div class="space-y-2">
                <Label for="dark_logo">Logo del menú</Label>
                <img v-if="settings.dark_logo_path" :src="settings.dark_logo_path" alt="Logo actual del menú" class="h-16 max-w-48 rounded-xl border border-surface-700 bg-surface-800 object-contain p-2" />
                <Input id="dark_logo" type="file" accept="image/*" @input="form.dark_logo = $event.target.files[0]" />
                <div class="flex items-center gap-3 pt-2">
                    <Label for="sidebar_logo_height">Altura</Label>
                    <Input id="sidebar_logo_height" v-model="form.sidebar_logo_height" type="number" min="20" max="120" class="w-28" />
                    <span class="text-sm text-surface-500">px</span>
                </div>
            </div>
            <div class="space-y-2">
                <Label for="favicon">Favicon</Label>
                <img v-if="settings.favicon_path" :src="settings.favicon_path" alt="Favicon actual" class="h-12 w-12 rounded-lg border border-surface-200 object-contain p-1" />
                <Input id="favicon" type="file" accept="image/x-icon,image/png" @input="form.favicon = $event.target.files[0]" />
            </div>
        </div>
    </div>
</template>
