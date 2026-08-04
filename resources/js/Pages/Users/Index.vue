<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    people: Object,
    systems: Array,
    filters: Object,
});

const page = usePage();

const q = ref(props.filters.q ?? '');
const systemFilter = ref(props.filters.system ?? '');
const statusFilter = ref(props.filters.status ?? '');

function applyFilters() {
    router.get(
        route('users.index'),
        { q: q.value, system: systemFilter.value, status: statusFilter.value },
        { preserveState: true, replace: true },
    );
}

const badgeColors = [
    'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200',
    'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200',
    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200',
    'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
];

// color estable por sistema (mismo sistema = mismo color siempre, sin depender del orden de carga)
function colorFor(systemKey) {
    let hash = 0;
    for (let i = 0; i < systemKey.length; i++) hash = (hash * 31 + systemKey.charCodeAt(i)) >>> 0;
    return badgeColors[hash % badgeColors.length];
}

const provisionStatusStyles = {
    created: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    exists: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

function provisionStatusLabel(status) {
    return { created: 'Creado', exists: 'Ya existía', failed: 'Falló' }[status] ?? status;
}

function accountStatusLabel(active) {
    if (active === true) return 'Activo';
    if (active === false) return 'De baja';
    return 'Sin datos';
}

function accountStatusClass(active) {
    if (active === true) return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200';
    if (active === false) return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    return 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
}

// Modal "Ver detalle"
const showDetail = ref(false);
const detailLoading = ref(false);
const detailName = ref('');
const detailAccounts = ref([]);

async function openDetail(person) {
    detailName.value = person.name;
    detailAccounts.value = [];
    detailLoading.value = true;
    showDetail.value = true;

    try {
        const res = await fetch(route('users.detail', { name: person.name }, false));
        const data = await res.json();
        detailAccounts.value = data.accounts ?? [];
    } finally {
        detailLoading.value = false;
    }
}

function closeDetail() {
    showDetail.value = false;
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value.replace(' ', 'T'));
    if (isNaN(d)) return value;
    return d.toLocaleString('es-BO', { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<template>
    <Head title="Usuarios" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Usuarios
                </h2>
                <Link
                    :href="route('users.create')"
                    class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-gray-200 dark:text-gray-800"
                >
                    + Crear usuario
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">

                <!-- Resultado de la última creación -->
                <div
                    v-if="page.props.flash?.provisionResults?.length"
                    class="rounded-md border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Resultado de la creación:
                    </p>
                    <ul class="space-y-1 text-sm">
                        <li v-for="(r, i) in page.props.flash.provisionResults" :key="i" class="flex items-center gap-2">
                            <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', provisionStatusStyles[r.status] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300']">
                                {{ provisionStatusLabel(r.status) }}
                            </span>
                            <span class="text-gray-600 dark:text-gray-300">{{ r.system }}</span>
                            <span v-if="r.message" class="text-xs text-gray-400">— {{ r.message }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Filtros -->
                <div class="flex flex-col gap-3 rounded-md border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-center">
                    <input
                        v-model="q"
                        @keyup.enter="applyFilters"
                        type="text"
                        placeholder="Buscar por nombre o email..."
                        class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 sm:max-w-xs"
                    />
                    <select
                        v-model="systemFilter"
                        @change="applyFilters"
                        class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 sm:max-w-xs"
                    >
                        <option value="">Todos los sistemas</option>
                        <option v-for="s in systems.filter(s => s.status === 'active')" :key="s.id" :value="s.key">{{ s.name }}</option>
                    </select>
                    <select
                        v-model="statusFilter"
                        @change="applyFilters"
                        class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 sm:max-w-xs"
                    >
                        <option value="">Activos/Baja</option>
                        <option value="active">Solo activos</option>
                        <option value="inactive">Solo de baja</option>
                    </select>
                    <button
                        @click="applyFilters"
                        class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200"
                    >
                        Filtrar
                    </button>
                    <span class="text-xs text-gray-400 sm:ml-auto">{{ people.total }} persona(s)</span>
                </div>

                <!-- Tabla -->
                <div class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sistemas</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="person in people.data" :key="person.name">
                                <td class="whitespace-nowrap px-4 py-3 align-top text-sm font-medium text-gray-800 dark:text-gray-100">
                                    {{ person.name }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="account in person.accounts"
                                            :key="account.system_key"
                                            :title="`${account.email} — ${accountStatusLabel(account.active)}`"
                                            :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium', colorFor(account.system_key)]"
                                        >
                                            <span
                                                v-if="account.active !== null"
                                                :class="['h-1.5 w-1.5 rounded-full', account.active ? 'bg-emerald-500' : 'bg-red-500']"
                                            />
                                            {{ account.system_name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right align-top">
                                    <button
                                        @click="openDetail(person)"
                                        class="rounded-md border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        Ver
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!people.data.length">
                                <td colspan="3" class="px-4 py-10 text-center text-sm text-gray-400">
                                    No hay usuarios que coincidan con el filtro.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="people.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, i) in people.links"
                        :key="i"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        preserve-scroll
                        :class="[
                            'rounded-md px-3 py-1.5 text-sm',
                            link.active ? 'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-800' : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300',
                            !link.url && 'pointer-events-none opacity-40',
                        ]"
                    />
                </div>
            </div>
        </div>

        <Modal :show="showDetail" @close="closeDetail" max-width="xl">
            <div class="p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ detailName }}</h3>
                    <button @click="closeDetail" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
                </div>

                <div v-if="detailLoading" class="py-8 text-center text-sm text-gray-400">
                    Cargando...
                </div>

                <div v-else-if="!detailAccounts.length" class="py-8 text-center text-sm text-gray-400">
                    No se encontraron cuentas activas para esta persona.
                </div>

                <div v-else class="max-h-[60vh] space-y-3 overflow-y-auto">
                    <div
                        v-for="account in detailAccounts"
                        :key="account.system_key + account.email"
                        class="rounded-md border border-gray-200 p-4 dark:border-gray-700"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', colorFor(account.system_key)]">
                                    {{ account.system_name }}
                                </span>
                                <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', accountStatusClass(account.active)]">
                                    {{ accountStatusLabel(account.active) }}
                                </span>
                            </div>
                            <span class="text-xs text-gray-400">Creado: {{ formatDate(account.created_at) }}</span>
                        </div>
                        <dl class="grid grid-cols-3 gap-1 text-sm">
                            <dt class="text-gray-400">Correo</dt>
                            <dd class="col-span-2 text-gray-700 dark:text-gray-200">{{ account.email }}</dd>

                            <dt class="text-gray-400">Roles</dt>
                            <dd class="col-span-2">
                                <span v-if="!account.roles.length" class="text-gray-400">Sin roles asignados / sistema sin tabla de roles</span>
                                <span v-else class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in account.roles"
                                        :key="role"
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                    >
                                        {{ role }}
                                    </span>
                                </span>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
