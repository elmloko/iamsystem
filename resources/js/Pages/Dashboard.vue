<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    systems: Array,
    topSystems: Array,
    mostRequestedSystems: Array,
    recentActivity: Array,
});

const page = usePage();

const cards = [
    {
        label: 'Solicitudes pendientes',
        value: () => props.stats.pendingRequests,
        icon: 'inbox',
        href: () => route('access-requests.index'),
        accent: (n) => n > 0
            ? 'bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-400'
            : 'bg-slate-50 text-slate-400 dark:bg-slate-800 dark:text-slate-500',
    },
    {
        label: 'Sistemas activos',
        value: () => props.stats.systemsActive,
        icon: 'check',
        href: () => route('systems.index'),
        accent: () => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400',
    },
    {
        label: 'Sistemas pendientes',
        value: () => props.stats.systemsPending,
        icon: 'clock',
        href: () => route('systems.index'),
        accent: () => 'bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-400',
    },
    {
        label: 'Cuentas en sistemas conectados',
        value: () => props.stats.liveAccounts,
        icon: 'key',
        href: () => route('users.index'),
        accent: () => 'bg-sky-50 text-sky-600 dark:bg-sky-950 dark:text-sky-400',
    },
    {
        label: 'Personas creadas por el IAM',
        value: () => props.stats.people,
        icon: 'users',
        href: () => route('users.index'),
        accent: () => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400',
    },
];

const maxSystemCount = props.topSystems.length ? Math.max(...props.topSystems.map((s) => s.count)) : 0;

function barWidth(count) {
    if (!maxSystemCount) return '0%';
    return `${Math.max(4, Math.round((count / maxSystemCount) * 100))}%`;
}

const maxRequestedCount = props.mostRequestedSystems.length
    ? Math.max(...props.mostRequestedSystems.map((s) => s.count))
    : 0;

function requestedBarWidth(count) {
    if (!maxRequestedCount) return '0%';
    return `${Math.max(4, Math.round((count / maxRequestedCount) * 100))}%`;
}

const trackedAccounts = props.stats.activeAccounts + props.stats.inactiveAccounts;

function accountStatusPercent(count) {
    if (!trackedAccounts) return '0%';
    return `${Math.max(0, Math.round((count / trackedAccounts) * 100))}%`;
}

function activityLabel(status) {
    return status === 'approved' ? 'Aprobado' : 'Rechazado';
}

function activityClass(status) {
    return status === 'approved'
        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200'
        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value.replace(' ', 'T'));
    if (isNaN(d)) return value;
    return d.toLocaleString('es-BO', { dateStyle: 'medium', timeStyle: 'short' });
}
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
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Link
                        v-for="card in cards"
                        :key="card.label"
                        :href="card.href()"
                        class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-700"
                    >
                        <div :class="['flex h-11 w-11 shrink-0 items-center justify-center rounded-lg', card.accent(card.value())]">
                            <svg v-if="card.icon === 'inbox'" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1l1.5 7H14a1 1 0 00-.9.55L12 14H8l-1.1-2.45A1 1 0 006 11H1.5L3 4z" />
                                <path d="M1.5 12.5V15a1 1 0 001 1h15a1 1 0 001-1v-2.5h-4.13l-.98 1.96a1 1 0 01-.9.54h-5a1 1 0 01-.9-.54l-.98-1.96H1.5z" />
                            </svg>
                            <svg v-else-if="card.icon === 'check'" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
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
                    </Link>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Sistemas con más cuentas -->
                    <div class="space-y-4 lg:col-span-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Sistemas con más cuentas
                            </h3>
                            <Link :href="route('users.index')" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                Ver usuarios
                            </Link>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div v-if="!topSystems.length" class="py-6 text-center text-sm text-slate-400">
                                Todavía no hay datos de cuentas para mostrar.
                            </div>
                            <ul v-else class="space-y-3">
                                <li v-for="system in topSystems" :key="system.key" class="flex items-center gap-3">
                                    <span class="w-36 shrink-0 truncate text-sm text-slate-600 dark:text-slate-300" :title="system.name">
                                        {{ system.name }}
                                    </span>
                                    <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div
                                            class="h-full rounded-full bg-indigo-500 dark:bg-indigo-400"
                                            :style="{ width: barWidth(system.count) }"
                                        />
                                    </div>
                                    <span class="w-14 shrink-0 text-right text-sm font-medium tabular-nums text-slate-800 dark:text-slate-100">
                                        {{ system.count }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Estado de sistemas -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Sistemas conectados
                            </h3>
                            <Link :href="route('systems.index')" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                Ver todos
                            </Link>
                        </div>
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

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Cuentas activas vs de baja -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Cuentas activas vs. de baja
                        </h3>
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div v-if="!trackedAccounts" class="py-6 text-center text-sm text-slate-400">
                                Ningún sistema conectado tiene una columna de estado mapeada todavía.
                            </div>
                            <div v-else class="space-y-4">
                                <div class="flex h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div
                                        class="h-full bg-emerald-500 dark:bg-emerald-400"
                                        :style="{ width: accountStatusPercent(stats.activeAccounts) }"
                                    />
                                    <div
                                        class="h-full bg-red-400 dark:bg-red-500"
                                        :style="{ width: accountStatusPercent(stats.inactiveAccounts) }"
                                    />
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 dark:bg-emerald-400" />
                                        <span class="text-slate-600 dark:text-slate-300">Activas</span>
                                    </div>
                                    <span class="font-medium tabular-nums text-slate-900 dark:text-white">
                                        {{ stats.activeAccounts }} ({{ accountStatusPercent(stats.activeAccounts) }})
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full bg-red-400 dark:bg-red-500" />
                                        <span class="text-slate-600 dark:text-slate-300">De baja</span>
                                    </div>
                                    <span class="font-medium tabular-nums text-slate-900 dark:text-white">
                                        {{ stats.inactiveAccounts }} ({{ accountStatusPercent(stats.inactiveAccounts) }})
                                    </span>
                                </div>
                                <p class="text-xs text-slate-400">
                                    Calculado sobre los sistemas conectados que tienen columna de estado mapeada en /sistemas.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Sistemas más solicitados -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Sistemas más solicitados
                            </h3>
                            <Link :href="route('access-requests.index')" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                Ver solicitudes
                            </Link>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div v-if="!mostRequestedSystems.length" class="py-6 text-center text-sm text-slate-400">
                                Todavía no hay solicitudes de acceso registradas.
                            </div>
                            <ul v-else class="space-y-3">
                                <li v-for="system in mostRequestedSystems" :key="system.key" class="flex items-center gap-3">
                                    <span class="w-36 shrink-0 truncate text-sm text-slate-600 dark:text-slate-300" :title="system.name">
                                        {{ system.name }}
                                    </span>
                                    <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div
                                            class="h-full rounded-full bg-fuchsia-500 dark:bg-fuchsia-400"
                                            :style="{ width: requestedBarWidth(system.count) }"
                                        />
                                    </div>
                                    <span class="w-14 shrink-0 text-right text-sm font-medium tabular-nums text-slate-800 dark:text-slate-100">
                                        {{ system.count }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Actividad reciente de solicitudes -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Actividad reciente de solicitudes
                        </h3>
                        <Link :href="route('access-requests.index')" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                            Ver solicitudes
                        </Link>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div v-if="!recentActivity.length" class="py-8 text-center text-sm text-slate-400">
                            Todavía no se aprobó ni rechazó ninguna solicitud.
                        </div>
                        <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                            <li
                                v-for="item in recentActivity"
                                :key="item.id"
                                class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                            >
                                <div class="flex items-center gap-2">
                                    <span :class="['rounded-full px-2.5 py-1 text-xs font-medium', activityClass(item.status)]">
                                        {{ activityLabel(item.status) }}
                                    </span>
                                    <span class="text-slate-700 dark:text-slate-200">{{ item.person_name }}</span>
                                    <span class="text-slate-400">→</span>
                                    <span class="text-slate-600 dark:text-slate-300">{{ item.system_name }}</span>
                                </div>
                                <span class="text-xs text-slate-400">
                                    {{ item.decided_by_name ? `${item.decided_by_name} · ` : '' }}{{ formatDate(item.decided_at) }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
