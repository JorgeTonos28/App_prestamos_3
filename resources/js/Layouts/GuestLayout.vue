<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';

useTheme();
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-guest-start to-guest-end">
        <div>
            <Link href="/">
                <ApplicationLogo class="w-24 h-24 fill-current text-guest-text drop-shadow-lg" />
            </Link>
        </div>

        <div
            class="w-full sm:max-w-md mt-6 px-8 py-8 bg-white/80 backdrop-blur-xl shadow-2xl border border-white/20 overflow-hidden sm:rounded-2xl"
        >
            <slot />
        </div>
    </div>
</template>
