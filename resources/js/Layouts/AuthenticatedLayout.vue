<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const user = usePage().props.auth.user;

const sidebarOpen = ref(false); // Mobile sidebar toggle
</script>

<template>
    <div class="min-h-screen bg-surface-50 flex">
        <!-- Sidebar (Desktop) -->
        <aside class="hidden md:flex flex-col w-64 bg-surface-900 text-primary-100 min-h-screen fixed left-0 top-0 z-20 border-r border-primary-800/40">
            <div class="flex items-center justify-center h-20 bg-surface-900 border-b border-primary-800/40">
                <Link :href="route('dashboard')">
                    <ApplicationLogo
                        class="block w-auto fill-current text-primary-300 transition-all duration-300"
                        mode="dark"
                        :style="{ height: ($page.props.settings?.sidebar_logo_height || 40) + 'px' }"
                    />
                </Link>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                <Link :href="route('dashboard')" :class="{'bg-primary-800/70 text-primary-100': route().current('dashboard'), 'text-primary-200 hover:bg-primary-800/70 hover:text-white': !route().current('dashboard')}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group">
                    <i class="fa-solid fa-gauge w-5 text-center group-hover:scale-110 transition-transform"></i>
                    <span class="font-medium">Dashboard</span>
                </Link>

                <Link :href="route('clients.index')" :class="{'bg-primary-800/70 text-primary-100': route().current('clients.*'), 'text-primary-200 hover:bg-primary-800/70 hover:text-white': !route().current('clients.*')}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group">
                    <i class="fa-solid fa-users w-5 text-center group-hover:scale-110 transition-transform"></i>
                    <span class="font-medium">Clientes</span>
                </Link>

                <Link :href="route('loans.index')" :class="{'bg-primary-800/70 text-primary-100': route().current('loans.*'), 'text-primary-200 hover:bg-primary-800/70 hover:text-white': !route().current('loans.*')}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group">
                    <i class="fa-solid fa-file-invoice-dollar w-5 text-center group-hover:scale-110 transition-transform"></i>
                    <span class="font-medium">Préstamos</span>
                </Link>

                <Link :href="route('loans.legal')" :class="{'bg-primary-800/70 text-primary-100': route().current('loans.legal'), 'text-primary-200 hover:bg-primary-800/70 hover:text-white': !route().current('loans.legal')}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group">
                    <i class="fa-solid fa-scale-balanced w-5 text-center group-hover:scale-110 transition-transform"></i>
                    <span class="font-medium">Legal</span>
                </Link>
            </nav>

            <div class="p-4 border-t border-primary-800/40 space-y-2">
                <Link :href="route('settings.edit')" :class="{'bg-primary-800/70 text-primary-100': route().current('settings.*')}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-primary-200 hover:bg-primary-800/70 hover:text-white transition-all">
                    <i class="fa-solid fa-gear w-5 text-center"></i>
                    <span class="font-medium">Configuración</span>
                </Link>
                <Link :href="route('profile.edit')" class="flex items-center gap-3 px-4 py-3 rounded-xl text-primary-200 hover:bg-primary-800/70 hover:text-white transition-all">
                    <i class="fa-solid fa-user-gear w-5 text-center"></i>
                    <span class="font-medium">Mi Cuenta</span>
                </Link>
            </div>
        </aside>

        <!-- Mobile Sidebar Overlay -->
        <div v-show="sidebarOpen" class="fixed inset-0 z-30 bg-surface-900/50 backdrop-blur-sm md:hidden" @click="sidebarOpen = false"></div>

        <!-- Mobile Sidebar -->
        <aside class="fixed inset-y-0 left-0 z-40 w-64 bg-surface-900 text-primary-100 border-r border-primary-800/40 transform transition-transform duration-300 ease-in-out md:hidden"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
             <div class="flex items-center justify-between h-20 px-4 bg-surface-900 border-b border-primary-800/40">
                <Link :href="route('dashboard')">
                    <ApplicationLogo class="block h-9 w-auto fill-current text-primary-300" />
                </Link>
                <button @click="sidebarOpen = false" class="text-primary-300 hover:text-white">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
             <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                <Link :href="route('dashboard')" :class="{'bg-primary-800/70 text-primary-100': route().current('dashboard')}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-primary-200 hover:bg-primary-800/70 hover:text-white transition-all">
                    <i class="fa-solid fa-gauge w-5"></i>
                    Dashboard
                </Link>
                 <Link :href="route('clients.index')" :class="{'bg-primary-800/70 text-primary-100': route().current('clients.*')}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-primary-200 hover:bg-primary-800/70 hover:text-white transition-all">
                    <i class="fa-solid fa-users w-5"></i>
                    Clientes
                </Link>
                 <Link :href="route('loans.index')" :class="{'bg-primary-800/70 text-primary-100': route().current('loans.*')}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-primary-200 hover:bg-primary-800/70 hover:text-white transition-all">
                    <i class="fa-solid fa-file-invoice-dollar w-5"></i>
                    Préstamos
                </Link>
                 <Link :href="route('loans.legal')" :class="{'bg-primary-800/70 text-primary-100': route().current('loans.legal')}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-primary-200 hover:bg-primary-800/70 hover:text-white transition-all">
                    <i class="fa-solid fa-scale-balanced w-5"></i>
                    Legal
                </Link>
                 <Link :href="route('profile.edit')" :class="{'bg-primary-800/70 text-primary-100': route().current('profile.edit')}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-primary-200 hover:bg-primary-800/70 hover:text-white transition-all">
                    <i class="fa-solid fa-user w-5"></i>
                    Perfil
                </Link>
            </nav>
        </aside>


        <!-- Main Content -->
        <div class="flex-1 flex flex-col md:pl-64 transition-all duration-300">
            <!-- Topbar -->
            <header class="sticky top-0 z-10 bg-white/90 backdrop-blur-md border-b border-surface-200/60 shadow-sm h-20">
                <div class="px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">
                    <!-- Mobile Menu Button -->
                    <button @click="sidebarOpen = true" class="md:hidden p-2 rounded-md text-surface-500 hover:bg-surface-100 hover:text-surface-700 focus:outline-none">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>

                    <!-- Page Header (from slot) -->
                    <div class="flex-1 flex items-center pl-2">
                         <slot name="header" />
                    </div>

                    <!-- User Dropdown -->
                    <div class="ml-4 flex items-center md:ml-6">
                         <div class="relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="flex items-center gap-3 px-2 py-1.5 rounded-full border border-surface-200 bg-white shadow-sm hover:shadow-md hover:border-primary-200 transition-all focus:outline-none group cursor-pointer"
                                        >
                                            <div class="w-9 h-9 rounded-full bg-primary-50 border border-primary-100 flex items-center justify-center text-primary-600 group-hover:bg-primary-600 group-hover:text-white transition-all">
                                                <i class="fa-solid fa-user text-sm"></i>
                                            </div>
                                            <div class="text-left hidden sm:block pr-2">
                                                <div class="text-sm font-bold text-surface-700 group-hover:text-primary-600 leading-none">{{ user.name }}</div>
                                                <div class="text-[10px] uppercase tracking-wide text-surface-400 font-semibold mt-0.5">Administrador</div>
                                            </div>
                                            <i class="fa-solid fa-chevron-down text-xs text-surface-300 group-hover:text-primary-500 mr-2 transition-colors"></i>
                                        </button>
                                    </template>

                                    <template #content>
                                        <div class="py-1">
                                            <DropdownLink :href="route('profile.edit')" class="flex items-center px-4 py-2 text-sm text-surface-700 hover:bg-surface-50 hover:text-primary-600">
                                                <i class="fa-regular fa-id-card mr-3 w-4"></i> Mi Perfil
                                            </DropdownLink>
                                            <div class="border-t border-surface-100 my-1"></div>
                                            <DropdownLink :href="route('logout')" method="post" as="button" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700">
                                                <i class="fa-solid fa-arrow-right-from-bracket mr-3 w-4"></i> Cerrar Sesión
                                            </DropdownLink>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-surface-50/50 p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
