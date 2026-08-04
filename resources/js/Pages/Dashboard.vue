<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    systems: Array,
});

const page = usePage();

const cards = [
    {
        label: 'Sistemas activos',
        value: () => props.stats.systemsActive,
        icon: 'check',
        accent: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400',
    },
    {
        label: 'Sistemas pendientes',
        value: () => props.stats.systemsPending,
        icon: 'clock',
        accent: 'bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-400',
    },
    {
        label: 'Personas registradas',
        value: () => props.stats.people,
        icon: 'users',
        accent: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400',
    },
    {
        label: 'Cuentas provisionadas',
        value: () => props.stats.accounts,
        icon: 'key',
        accent: 'bg-sky-50 text-sky-600 dark:bg-sky-950 dark:text-sky-400',
    },
];

const quickLinks = [
    {
        title: 'Sistemas',
        description: 'Consulta el estado y los roles de cada sistema conectado.',
        href: () => route('systems.index'),
        cta: 'Ver sistemas',
    },
    {
        title: 'Usuarios',
        description: 'Consulta y filtra las personas provisionadas por sistema.',
        href: () => route('users.index'),
        cta: 'Ver usuarios',
    },
    {
        title: 'Crear usuario',
        description: 'Da de alta una persona en uno o varios sistemas a la vez.',
        href: () => route('users.create'),
        cta: 'Crear usuario',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">
                Dashboard
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Hola, {{ page.props.auth.user.name }} 👋
                    </p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                        Panel de identidad y accesos AGBC
                    </h3>
                </div>

                <!-- Tarjetas de estadísticas -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="card in cards"
                        :key="card.label"
                        class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div :class="['flex h-11 w-11 shrink-0 items-center justify-center rounded-lg', card.accent]">
                            <svg v-if="card.icon === 'check'" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.4 7.4a1 1 0 01-1.4 0L3.3 9.5a1 1 0 111.4-1.4l3.6 3.6 6.7-6.7a1 1 0 011.4 0z" clip-rule="evenodd" />
                            </svg>
                            <svg v-else-if="card.icon === 'clock'" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v5c0 .27.1.52.3.7l3 3a1 1 0 001.4-1.4L11 9.6V5z" clip-rule="evenodd" />
                            </svg>
                            <svg v-else-if="card.icon === 'users'" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M9 2a3 3 0 100 6 3 3 0 000-6zM4 16a5 5 0 0110 0v1H4v-1zM15 8a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM14.5 10.5c1.9 0 3.8 1 4.4 3.1.1.5-.3 1-.9 1h-2.5v-1a5.7 5.7 0 00-1.9-4.3c.3-.1.6-.1.9-.1v1.3z" />
                            </svg>
                            <svg v-else class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 7a5 5 0 118.55 3.54l3.46 3.46a1 1 0 01-1.42 1.42l-3.46-3.46A5 5 0 018 7zm5-3a3 3 0 100 6 3 3 0 000-6z" clip-rule="evenodd" />
                                <path d="M2 15a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1H3a1 1 0 01-1-1v-3z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-semibold text-slate-900 dark:text-white">{{ card.value() }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Accesos rápidos -->
                    <div class="space-y-4 lg:col-span-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Accesos rápidos
                        </h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Link
                                v-for="link in quickLinks"
                                :key="link.title"
                                :href="link.href()"
                                class="group flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-700"
                            >
                                <span class="font-medium text-slate-900 dark:text-white">{{ link.title }}</span>
                                <span class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ link.description }}</span>
                                <span class="mt-4 inline-flex items-center text-sm font-medium text-indigo-600 group-hover:text-indigo-500 dark:text-indigo-400">
                                    {{ link.cta }}
                                    <svg class="ms-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.3 14.7a1 1 0 010-1.4L10.6 10 7.3 6.7a1 1 0 111.4-1.4l4 4a1 1 0 010 1.4l-4 4a1 1 0 01-1.4 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </Link>
                        </div>
                    </div>

                    <!-- Estado de sistemas -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Sistemas conectados
                        </h3>
                        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                                <li
                                    v-for="system in systems"
                                    :key="system.id"
                                    class="flex items-center justify-between px-4 py-2.5 text-sm"
                                >
                                    <span class="text-slate-700 dark:text-slate-200">{{ system.name }}</span>
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                            system.status === 'active'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200'
                                                : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                        ]"
                                    >
                                        {{ system.status === 'active' ? 'Activo' : 'Pendiente' }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
