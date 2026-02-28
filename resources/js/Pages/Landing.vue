<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="App Presto" />

    <div class="min-h-screen bg-gradient-to-br from-primary-50 via-white to-surface-100 text-surface-800">
        <main class="mx-auto flex min-h-screen max-w-6xl flex-col justify-center px-6 py-16">
            <div class="rounded-3xl border border-white/60 bg-white/85 p-10 shadow-2xl backdrop-blur">
                <p class="mb-4 inline-flex rounded-full bg-primary-100 px-4 py-1 text-xs font-semibold text-primary-700">SaaS para gestión de microcréditos</p>
                <h1 class="text-4xl font-black leading-tight text-surface-900 md:text-5xl">Controla tu cartera de préstamos con disciplina contable y cobro inteligente.</h1>
                <p class="mt-6 max-w-3xl text-lg text-surface-600">App Presto centraliza clientes, préstamos y pagos con libro mayor inmutable, automatización de mora y controles SaaS de suscripción para operar con seguridad.</p>

                <div class="mt-10 flex flex-wrap gap-4">
                    <Link :href="route('login')" class="inline-flex items-center rounded-xl bg-primary-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary-600/30 hover:bg-primary-700">Iniciar Sesión</Link>
                    <Link :href="route('social.redirect', 'google')" class="inline-flex items-center rounded-xl border border-surface-200 bg-white px-6 py-3 text-sm font-semibold text-surface-700 hover:bg-surface-50">Registrarse con Google</Link>
                    <Link :href="route('social.redirect', 'facebook')" class="inline-flex items-center rounded-xl border border-surface-200 bg-white px-6 py-3 text-sm font-semibold text-surface-700 hover:bg-surface-50">Registrarse con Facebook</Link>
                </div>

                <div class="mt-12 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-surface-100 bg-surface-50 p-5">
                        <h3 class="font-bold">Ledger inmutable</h3>
                        <p class="mt-2 text-sm text-surface-600">Cada movimiento queda registrado para auditoría y trazabilidad completa.</p>
                    </div>
                    <div class="rounded-2xl border border-surface-100 bg-surface-50 p-5">
                        <h3 class="font-bold">Cobro y seguimiento</h3>
                        <p class="mt-2 text-sm text-surface-600">Controla mora, pagos y contratos legales en un flujo operativo unificado.</p>
                    </div>
                    <div class="rounded-2xl border border-surface-100 bg-surface-50 p-5">
                        <h3 class="font-bold">Suscripción empresarial</h3>
                        <p class="mt-2 text-sm text-surface-600">Activa tu plan, gestiona facturas y mantén la operación siempre al día.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
