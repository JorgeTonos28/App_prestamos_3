<script setup>
import { computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const themeClass = computed(() =>
    page.props.settings?.theme_palette === 'rose' ? 'theme-rose' : ''
);
</script>

<template>
    <div :class="['min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-primary-50 to-primary-100', themeClass]">
        <div>
            <Link href="/">
                <ApplicationLogo class="w-24 h-24 fill-current text-primary-600 drop-shadow-lg" />
            </Link>
        </div>

        <div
            class="w-full sm:max-w-md mt-6 px-8 py-8 bg-white/80 backdrop-blur-xl shadow-2xl border border-white/20 overflow-hidden sm:rounded-2xl"
        >
            <slot />
        </div>
    </div>
</template>
