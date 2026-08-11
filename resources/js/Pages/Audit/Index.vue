<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    users: Array,
    actions: Array,
});

const q = ref(props.filters.q ?? '');
const userId = ref(props.filters.user_id ?? '');
const action = ref(props.filters.action ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

function applyFilters() {
    router.get(
        route('audit.index'),
        { q: q.value, user_id: userId.value, action: action.value, from: from.value, to: to.value },
        { preserveState: true, replace: true },
    );
}

function clearFilters() {
    q.value = '';
    userId.value = '';
    action.value = '';
    from.value = '';
    to.value = '';
    applyFilters();
}

const categoryColors = {
    systems: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
    accounts: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    access_requests: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    admins: 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200',
    auth: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
    page_view: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

function badgeClass(actionName) {
    const category = actionName.split('.')[0];
    return categoryColors[category] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
}

function formatDate(value) {
    return new Date(value).toLocaleString('es-BO', { dateStyle: 'short', timeStyle: 'medium' });
}
</script>

<template>
    <Head title="Auditoría" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">
                Auditoría
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Todo lo que se hizo dentro del IAM: inicios de sesión, pestañas visitadas, sistemas creados o
                    editados, cuentas y roles gestionados, solicitudes de acceso y usuarios internos.
                </p>

                <!-- Filtros -->
                <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:flex-row lg:flex-wrap lg:items-center">
                    <input
                        v-model="q"
                        type="text"
                        placeholder="Buscar en descripción o correo..."
                        class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 lg:max-w-xs"
                        @keyup.enter="applyFilters"
                    />
                    <select
                        v-model="userId"
                        class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 lg:max-w-[14rem]"
                        @change="applyFilters"
                    >
                        <option value="">Todos los usuarios</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                    </select>
                    <select
                        v-model="action"
                        class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 lg:max-w-[14rem]"
                        @change="applyFilters"
                    >
                        <option value="">Todas las acciones</option>
                        <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                    </select>
                    <input
                        v-model="from"
                        type="date"
                        class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 lg:w-auto"
                        @change="applyFilters"
                    />
                    <span class="text-xs text-slate-400">a</span>
                    <input
                        v-model="to"
                        type="date"
                        class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 lg:w-auto"
                        @change="applyFilters"
                    />
                    <button
                        type="button"
                        @click="applyFilters"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                    >
                        Filtrar
                    </button>
                    <button
                        type="button"
                        @click="clearFilters"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        Limpiar
                    </button>
                    <span class="text-xs text-slate-400 lg:ml-auto">{{ logs.total }} evento(s)</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <table class="w-full min-w-[64rem] divide-y divide-slate-200 dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Fecha</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Usuario</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Acción</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Descripción</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="log in logs.data" :key="log.id">
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                    {{ formatDate(log.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300">
                                    <div class="font-medium text-slate-800 dark:text-slate-100">
                                        {{ log.user?.name ?? log.actor_name ?? 'Público' }}
                                    </div>
                                    <div class="text-xs text-slate-400">{{ log.user?.email ?? log.actor_email ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', badgeClass(log.action)]">
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300">
                                    {{ log.description }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-400">
                                    {{ log.ip_address ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="!logs.data.length">
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-400">
                                    No hay eventos que coincidan con el filtro.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="logs.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, i) in logs.links"
                        :key="i"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        preserve-scroll
                        :class="[
                            'rounded-md px-3 py-1.5 text-sm',
                            link.active ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300',
                            !link.url && 'pointer-events-none opacity-40',
                        ]"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
