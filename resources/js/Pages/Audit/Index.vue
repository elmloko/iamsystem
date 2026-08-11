<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';

const props = defineProps({
    mode: String, // 'iam' | 'external'
    systems: Array, // ['IAM AGBC', 'SIOP', 'SIGEC', 'API Web']
    filters: Object,
    // mode === 'iam'
    logs: Object,
    users: Array,
    actions: Array,
    // mode === 'external'
    page: Number,
    results: Object,
});

const systemFilter = ref(props.filters.system || 'iam');
const q = ref(props.filters.q ?? '');

function onSystemSelectChange() {
    if (systemFilter.value === 'iam') {
        router.get(route('audit.index'), { system: 'iam' }, { replace: true });
        return;
    }

    actorFilter.value = '';
    loadActors(systemFilter.value);
    router.get(route('audit.index'), { system: systemFilter.value }, { replace: true });
}

// --- Filtros del modo IAM (auditoría propia: page views, logins, altas/bajas, etc.) ---
const userId = ref(props.filters.user_id ?? '');
const action = ref(props.filters.action ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

function applyIamFilters() {
    router.get(
        route('audit.index'),
        { system: systemFilter.value, q: q.value, user_id: userId.value, action: action.value, from: from.value, to: to.value },
        { preserveState: true, replace: true },
    );
}

function clearIamFilters() {
    q.value = '';
    userId.value = '';
    action.value = '';
    from.value = '';
    to.value = '';
    applyIamFilters();
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

// --- Filtros del modo externo (SIOP/SIGEC/API Web: auditoría en vivo de cada sistema) ---
const actorFilter = ref(props.filters.actor ?? '');
const actorsState = reactive({ loading: false, actors: [] });

async function loadActors(systemKey) {
    if (!systemKey || systemKey === 'iam') {
        actorsState.actors = [];
        return;
    }

    actorsState.loading = true;
    try {
        const res = await fetch(route('audit.actors', { system: systemKey }, false));
        const data = await res.json();
        actorsState.actors = data.actors ?? [];
    } catch (e) {
        actorsState.actors = [];
    } finally {
        actorsState.loading = false;
    }
}

onMounted(() => {
    if (props.mode === 'external') {
        loadActors(systemFilter.value);
    }
});

function applyExternalFilters(page = 1) {
    router.get(
        route('audit.index'),
        { system: systemFilter.value, q: q.value, actor: actorFilter.value, page },
        { preserveState: true, replace: true },
    );
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d)) return value;
    return d.toLocaleString('es-BO', { dateStyle: 'medium', timeStyle: 'medium' });
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
                    <template v-if="mode === 'iam'">
                        Todo lo que se hizo dentro del IAM: inicios de sesión, pestañas visitadas, sistemas creados o
                        editados, cuentas y roles gestionados, solicitudes de acceso y usuarios internos.
                    </template>
                    <template v-else>
                        Registros de auditoría/bitácora reales, traídos en vivo de la base de datos del sistema seleccionado.
                    </template>
                </p>

                <!-- Selector de sistema (siempre visible: cambia entre la auditoría propia del IAM y la de cada sistema externo) -->
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Sistema</label>
                    <select
                        v-model="systemFilter"
                        @change="onSystemSelectChange"
                        class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 sm:max-w-xs"
                    >
                        <option v-for="s in systems" :key="s.key" :value="s.key">{{ s.name }}</option>
                    </select>
                </div>

                <!-- ============ MODO IAM ============ -->
                <template v-if="mode === 'iam'">
                    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:flex-row lg:flex-wrap lg:items-center">
                        <input
                            v-model="q"
                            type="text"
                            placeholder="Buscar en descripción o correo..."
                            class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 lg:max-w-xs"
                            @keyup.enter="applyIamFilters"
                        />
                        <select
                            v-model="userId"
                            class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 lg:max-w-[14rem]"
                            @change="applyIamFilters"
                        >
                            <option value="">Todos los usuarios</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                        </select>
                        <select
                            v-model="action"
                            class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 lg:max-w-[14rem]"
                            @change="applyIamFilters"
                        >
                            <option value="">Todas las acciones</option>
                            <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                        </select>
                        <input
                            v-model="from"
                            type="date"
                            class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 lg:w-auto"
                            @change="applyIamFilters"
                        />
                        <span class="text-xs text-slate-400">a</span>
                        <input
                            v-model="to"
                            type="date"
                            class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 lg:w-auto"
                            @change="applyIamFilters"
                        />
                        <button
                            type="button"
                            @click="applyIamFilters"
                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                        >
                            Filtrar
                        </button>
                        <button
                            type="button"
                            @click="clearIamFilters"
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
                </template>

                <!-- ============ MODO SISTEMA EXTERNO ============ -->
                <template v-else>
                    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center">
                        <input
                            v-model="q"
                            @keyup.enter="applyExternalFilters(1)"
                            type="text"
                            placeholder="Buscar por acción o descripción..."
                            class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 sm:max-w-xs"
                        />
                        <select
                            v-model="actorFilter"
                            @change="applyExternalFilters(1)"
                            :disabled="actorsState.loading"
                            class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 disabled:cursor-not-allowed disabled:opacity-50 sm:max-w-xs"
                        >
                            <option value="">
                                {{ actorsState.loading ? 'Cargando usuarios...' : 'Todos los usuarios' }}
                            </option>
                            <option v-for="a in actorsState.actors" :key="a.value" :value="a.value">{{ a.label }}</option>
                        </select>
                        <button
                            @click="applyExternalFilters(1)"
                            class="rounded-md bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        >
                            Buscar
                        </button>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                                <thead class="bg-slate-50 dark:bg-slate-800/40">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Fecha y hora</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Usuario</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Acción</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="(item, idx) in results.items" :key="`${item.system_key}-${idx}`">
                                        <td class="whitespace-nowrap px-4 py-3 align-top text-sm text-slate-600 dark:text-slate-300">
                                            {{ formatDate(item.occurred_at) }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm">
                                            <div class="text-slate-800 dark:text-slate-100">{{ item.actor_name || '—' }}</div>
                                            <div v-if="item.actor_email" class="text-xs text-slate-400">{{ item.actor_email }}</div>
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm text-slate-600 dark:text-slate-300">
                                            <span
                                                v-if="item.category"
                                                class="mr-2 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                            >
                                                {{ item.category }}
                                            </span>
                                            <span class="align-middle">{{ item.description || '—' }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 align-top text-xs text-slate-400">
                                            {{ item.ip_address || '—' }}
                                        </td>
                                    </tr>
                                    <tr v-if="!results.items.length">
                                        <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-400">
                                            No se encontraron registros de auditoría con esos filtros.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Paginación -->
                    <div class="flex items-center justify-between">
                        <button
                            :disabled="page <= 1"
                            @click="applyExternalFilters(page - 1)"
                            class="rounded-md border border-slate-200 px-3 py-1.5 text-sm text-slate-600 disabled:opacity-40 dark:border-slate-700 dark:text-slate-300"
                        >
                            ← Anterior
                        </button>
                        <span class="text-xs text-slate-400">Página {{ page }}</span>
                        <button
                            :disabled="!results.has_more"
                            @click="applyExternalFilters(page + 1)"
                            class="rounded-md border border-slate-200 px-3 py-1.5 text-sm text-slate-600 disabled:opacity-40 dark:border-slate-700 dark:text-slate-300"
                        >
                            Siguiente →
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
