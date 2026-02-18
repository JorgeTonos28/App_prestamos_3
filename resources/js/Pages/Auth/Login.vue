<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import LoginButterflies from '@/Components/LoginButterflies.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const page = usePage();

const isCarolinaTheme = computed(() => {
    const raw = String(page?.props?.settings?.color_theme ?? 'default').toLowerCase();
    return raw === 'carolina' || raw === 'pinky';
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />
        <LoginButterflies anchor-selector="#login-card" />

        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="relative z-20">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Password" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="block mt-4">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600">Remember me</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Forgot your password?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="[
                        { 'opacity-25': form.processing },
                        isCarolinaTheme
                            ? '!bg-gradient-to-r !from-primary-500 !to-primary-700 hover:!from-primary-400 hover:!to-primary-600 focus:!ring-primary-400 !shadow-primary-400/30'
                            : '',
                    ]"
                    :disabled="form.processing"
                >
                    Log in
                </PrimaryButton>
            </div>

            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-surface-200"></div>
                    </div>
                    <div class="relative flex justify-center text-xs uppercase">
                        <span class="bg-white px-3 text-surface-500">o continúa con</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Link :href="route('social.redirect', 'google')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-surface-200 bg-white px-4 py-2 text-sm font-semibold text-surface-700 hover:bg-surface-50">
                        <i class="fa-brands fa-google"></i> Google
                    </Link>
                    <Link :href="route('social.redirect', 'facebook')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-surface-200 bg-white px-4 py-2 text-sm font-semibold text-surface-700 hover:bg-surface-50">
                        <i class="fa-brands fa-facebook"></i> Facebook
                    </Link>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
