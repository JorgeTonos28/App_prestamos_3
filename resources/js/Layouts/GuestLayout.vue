<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';

const page = usePage();

const isCarolinaTheme = computed(() => {
    const raw = String(page?.props?.settings?.color_theme ?? 'default').toLowerCase();
    return raw === 'carolina' || raw === 'pinky';
});
</script>

<template>
    <div
        class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 transition-colors duration-300"
        :class="isCarolinaTheme
            ? 'bg-gradient-to-br from-primary-100 via-primary-50 to-info-100'
            : 'bg-gradient-to-br from-blue-50 to-indigo-100'"
    >
        <div>
            <Link href="/">
                <ApplicationLogo
                    class="w-24 h-24 fill-current drop-shadow-lg"
                    :class="isCarolinaTheme ? 'text-primary-500' : 'text-blue-600'"
                />
            </Link>
        </div>

        <div
            id="login-card"
            class="w-full sm:max-w-md mt-6 px-8 py-8 bg-white/80 backdrop-blur-xl shadow-2xl border border-white/20 overflow-hidden sm:rounded-2xl"
            :class="isCarolinaTheme ? 'ring-1 ring-primary-200/70 shadow-primary-300/30' : ''"
        >
            <slot />
        </div>
    </div>
</template>
