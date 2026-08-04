<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    systems: Array,
});

const showRoles = ref(false);
const rolesLoading = ref(false);
const rolesError = ref(false);
const activeSystem = ref(null);
const roles = ref([]);

async function openRoles(system) {
    activeSystem.value = system;
    roles.value = [];
    rolesError.value = false;
    rolesLoading.value = true;
    showRoles.value = true;

    try {
        const res = await fetch(route('users.roles', system.id, false));
        if (!res.ok) throw new Error('request failed');
        const data = await res.json();
        roles.value = data.roles ?? [];
    } catch (e) {
        rolesError.value = true;
    } finally {
        rolesLoading.value = false;
    }
}

function closeRoles() {
    showRoles.value = false;
}
</script>

<template>
    <Head title="Sistemas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">
                Sistemas
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ systems.length }} sistema(s) registrados en el IAM. Usa "Ver roles" para consultar los roles
                    disponibles directamente en cada base de datos.
                </p>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Sistema</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Estado</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Conexión</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="system in systems" :key="system.id">
                                <td class="px-4 py-3 align-top">
                                    <div class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ system.name }}</div>
                                    <div class="text-xs text-slate-400">{{ system.key }}</div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                                            system.status === 'active'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200'
                                                : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
                                        ]"
                                    >
                                        {{ system.status === 'active' ? 'Activo' : 'Pendiente' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-top text-sm text-slate-500 dark:text-slate-400">
                                    {{ system.connection ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    <button
                                        @click="openRoles(system)"
                                        :disabled="system.status !== 'active'"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                    >
                                        Ver roles
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!systems.length">
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-400">
                                    No hay sistemas registrados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="showRoles" @close="closeRoles" max-width="md">
            <div class="p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">
                        Roles — {{ activeSystem?.name }}
                    </h3>
                    <button @click="closeRoles" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">✕</button>
                </div>

                <div v-if="rolesLoading" class="py-8 text-center text-sm text-slate-400">
                    Cargando roles...
                </div>

                <div v-else-if="rolesError" class="py-8 text-center text-sm text-red-500">
                    No se pudo consultar los roles de este sistema.
                </div>

                <div v-else-if="!roles.length" class="py-8 text-center text-sm text-slate-400">
                    Este sistema no expone una tabla de roles reconocible.
                </div>

                <ul v-else class="max-h-80 space-y-1.5 overflow-y-auto">
                    <li
                        v-for="role in roles"
                        :key="role.id"
                        class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        {{ role.name }}
                    </li>
                </ul>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
