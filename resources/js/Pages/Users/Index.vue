<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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

function applyFilters() {
    router.get(
        route('users.index'),
        { q: q.value, system: systemFilter.value },
        { preserveState: true, replace: true },
    );
}

const statusStyles = {
    created: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    exists: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    pending: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
};

function statusLabel(status) {
    return { created: 'Creado', exists: 'Ya existía', failed: 'Falló', pending: 'Pendiente' }[status] ?? status;
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
                            <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusStyles[r.status] ?? statusStyles.pending]">
                                {{ statusLabel(r.status) }}
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
                        <option v-for="s in systems" :key="s.id" :value="s.key">{{ s.name }}</option>
                    </select>
                    <button
                        @click="applyFilters"
                        class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200"
                    >
                        Filtrar
                    </button>
                </div>

                <!-- Tabla -->
                <div class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sistemas y roles</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="person in people.data" :key="person.id">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">
                                    {{ person.name }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ person.email }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="account in person.accounts"
                                            :key="account.id"
                                            :title="account.message ?? ''"
                                            :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium', statusStyles[account.status] ?? statusStyles.pending]"
                                        >
                                            {{ account.system?.name }}
                                            <span v-if="account.role_name" class="opacity-75">· {{ account.role_name }}</span>
                                        </span>
                                        <span v-if="!person.accounts.length" class="text-xs text-gray-400">
                                            Sin sistemas asignados
                                        </span>
                                    </div>
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
    </AuthenticatedLayout>
</template>
