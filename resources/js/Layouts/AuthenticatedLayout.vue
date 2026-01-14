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
    <div class="min-h-screen bg-gray-50 flex">
        <!-- Sidebar (Desktop) -->
        <aside class="hidden md:flex flex-col w-64 bg-slate-900 text-white min-h-screen fixed left-0 top-0 z-20">
            <div class="flex items-center justify-center h-20 bg-slate-950/50 backdrop-blur-sm border-b border-slate-700/50">
                <Link :href="route('dashboard')">
                    <ApplicationLogo class="block h-10 w-auto fill-current text-white" />
                </Link>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                <Link :href="route('dashboard')" :class="{'bg-blue-600/20 text-blue-400': route().current('dashboard'), 'text-slate-300 hover:bg-slate-800 hover:text-white': !route().current('dashboard')}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group">
                    <i class="fa-solid fa-gauge w-5 text-center group-hover:scale-110 transition-transform"></i>
                    <span class="font-medium">Dashboard</span>
                </Link>

                <Link :href="route('clients.index')" :class="{'bg-blue-600/20 text-blue-400': route().current('clients.*'), 'text-slate-300 hover:bg-slate-800 hover:text-white': !route().current('clients.*')}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group">
                    <i class="fa-solid fa-users w-5 text-center group-hover:scale-110 transition-transform"></i>
                    <span class="font-medium">Clientes</span>
                </Link>

                <Link :href="route('loans.index')" :class="{'bg-blue-600/20 text-blue-400': route().current('loans.*'), 'text-slate-300 hover:bg-slate-800 hover:text-white': !route().current('loans.*')}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group">
                    <i class="fa-solid fa-file-invoice-dollar w-5 text-center group-hover:scale-110 transition-transform"></i>
                    <span class="font-medium">Préstamos</span>
                </Link>
            </nav>

            <div class="p-4 border-t border-slate-700/50 space-y-2">
                <Link :href="route('settings.edit')" :class="{'bg-blue-600/20 text-blue-400': route().current('settings.*')}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all">
                    <i class="fa-solid fa-gear w-5 text-center"></i>
                    <span class="font-medium">Configuración</span>
                </Link>
                <Link :href="route('profile.edit')" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all">
                    <i class="fa-solid fa-user-gear w-5 text-center"></i>
                    <span class="font-medium">Mi Cuenta</span>
                </Link>
            </div>
        </aside>

        <!-- Mobile Sidebar Overlay -->
        <div v-show="sidebarOpen" class="fixed inset-0 z-30 bg-gray-900/50 backdrop-blur-sm md:hidden" @click="sidebarOpen = false"></div>

        <!-- Mobile Sidebar -->
        <aside class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 text-white transform transition-transform duration-300 ease-in-out md:hidden"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
             <div class="flex items-center justify-between h-20 px-4 bg-slate-950/50 backdrop-blur-sm border-b border-slate-700/50">
                <Link :href="route('dashboard')">
                    <ApplicationLogo class="block h-9 w-auto fill-current text-white" />
                </Link>
                <button @click="sidebarOpen = false" class="text-slate-400 hover:text-white">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
             <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                <Link :href="route('dashboard')" :class="{'bg-blue-600/20 text-blue-400': route().current('dashboard')}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all">
                    <i class="fa-solid fa-gauge w-5"></i>
                    Dashboard
                </Link>
                 <Link :href="route('clients.index')" :class="{'bg-blue-600/20 text-blue-400': route().current('clients.*')}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all">
                    <i class="fa-solid fa-users w-5"></i>
                    Clientes
                </Link>
                 <Link :href="route('loans.index')" :class="{'bg-blue-600/20 text-blue-400': route().current('loans.*')}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all">
                    <i class="fa-solid fa-file-invoice-dollar w-5"></i>
                    Préstamos
                </Link>
                 <Link :href="route('profile.edit')" :class="{'bg-blue-600/20 text-blue-400': route().current('profile.edit')}" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-all">
                    <i class="fa-solid fa-user w-5"></i>
                    Perfil
                </Link>
            </nav>
        </aside>


        <!-- Main Content -->
        <div class="flex-1 flex flex-col md:pl-64 transition-all duration-300">
            <!-- Topbar -->
            <header class="sticky top-0 z-10 bg-white/90 backdrop-blur-md border-b border-slate-200/60 shadow-sm h-20">
                <div class="px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between">
                    <!-- Mobile Menu Button -->
                    <button @click="sidebarOpen = true" class="md:hidden p-2 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none">
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
                                            class="flex items-center gap-3 px-2 py-1.5 rounded-full border border-slate-200 bg-white shadow-sm hover:shadow-md hover:border-blue-200 transition-all focus:outline-none group"
                                        >
                                            <div class="w-9 h-9 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all">
                                                <i class="fa-solid fa-user text-sm"></i>
                                            </div>
                                            <div class="text-left hidden sm:block pr-2">
                                                <div class="text-sm font-bold text-slate-700 group-hover:text-blue-600 leading-none">{{ user.name }}</div>
                                                <div class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold mt-0.5">Administrador</div>
                                            </div>
                                            <i class="fa-solid fa-chevron-down text-xs text-slate-300 group-hover:text-blue-500 mr-2 transition-colors"></i>
                                        </button>
                                    </template>

                                    <template #content>
                                        <div class="py-1">
                                            <DropdownLink :href="route('profile.edit')" class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-blue-600">
                                                <i class="fa-regular fa-id-card mr-3 w-4"></i> Mi Perfil
                                            </DropdownLink>
                                            <div class="border-t border-slate-100 my-1"></div>
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
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-50/50 p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
