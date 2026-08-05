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

function xsrfHeader() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

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

// Modal "Editar"
const showEdit = ref(false);
const editLoading = ref(false);
const editName = ref('');
const editAccounts = ref([]);

async function openEdit(person) {
    editName.value = person.name;
    editAccounts.value = [];
    editLoading.value = true;
    showEdit.value = true;

    try {
        const res = await fetch(route('users.detail', { name: person.name }, false));
        const data = await res.json();
        editAccounts.value = (data.accounts ?? []).map((account) => ({
            ...account,
            _password: '',
            _saving: false,
            _message: null,
            _messageType: 'success',
            _rolesLoading: false,
            _availableRoles: [],
            _rolesLoaded: false,
            _selectedRoleId: '',
            _addingRole: false,
            _removingRoleId: null,
        }));
    } finally {
        editLoading.value = false;
    }
}

function closeEdit() {
    showEdit.value = false;
}

function flash(account, message, type = 'success') {
    account._message = message;
    account._messageType = type;
}

async function saveAccount(account) {
    account._saving = true;
    account._message = null;

    try {
        const res = await fetch(route('users.accounts.update', account.system_id, false), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfHeader(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                remote_user_id: account.remote_user_id,
                first_name: account.first_name,
                last_name: account.last_name,
                email: account.email,
                alias: account.alias,
                password: account._password || null,
            }),
        });
        const data = await res.json();

        if (data.status === 'updated') {
            flash(account, account._password ? 'Datos y contraseña actualizados.' : 'Datos actualizados.', 'success');
            account._password = '';
        } else {
            flash(account, data.message ?? 'No se pudo actualizar.', 'error');
        }
    } catch (e) {
        flash(account, 'Error de conexión al guardar.', 'error');
    } finally {
        account._saving = false;
    }
}

async function toggleActive(account) {
    const nextValue = !account.active;
    account._saving = true;
    account._message = null;

    try {
        const res = await fetch(route('users.accounts.status', account.system_id, false), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfHeader(),
                Accept: 'application/json',
            },
            body: JSON.stringify({ remote_user_id: account.remote_user_id, active: nextValue }),
        });
        const data = await res.json();

        if (data.status === 'updated') {
            account.active = nextValue;
            flash(account, nextValue ? 'Cuenta dada de alta.' : 'Cuenta dada de baja.', 'success');
        } else {
            flash(account, data.message ?? 'No se pudo cambiar el estado.', 'error');
        }
    } catch (e) {
        flash(account, 'Error de conexión al cambiar el estado.', 'error');
    } finally {
        account._saving = false;
    }
}

async function loadRolesFor(account) {
    if (account._rolesLoaded || account._rolesLoading) return;

    account._rolesLoading = true;
    try {
        const res = await fetch(route('users.roles', account.system_id, false));
        const data = await res.json();
        account._availableRoles = data.roles ?? [];
        account._rolesLoaded = true;
    } finally {
        account._rolesLoading = false;
    }
}

async function addRole(account) {
    if (!account._selectedRoleId) return;

    const role = account._availableRoles.find((r) => String(r.id) === String(account._selectedRoleId));
    account._addingRole = true;
    account._message = null;

    try {
        const res = await fetch(route('users.accounts.roles', account.system_id, false), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfHeader(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                remote_user_id: account.remote_user_id,
                role_id: role?.id ?? account._selectedRoleId,
                role_name: role?.name ?? null,
            }),
        });
        const data = await res.json();

        if (data.status === 'updated') {
            if (role) {
                const alreadyThere = account.roles.some((r) => String(r.id) === String(role.id));
                account.roles = account.single_role ? [role] : (alreadyThere ? account.roles : [...account.roles, role]);
            }
            flash(account, data.message ?? 'Rol agregado.', 'success');
        } else if (data.status === 'exists') {
            flash(account, data.message ?? 'Ya tenía ese rol.', 'error');
        } else {
            flash(account, data.message ?? 'No se pudo agregar el rol.', 'error');
        }
    } catch (e) {
        flash(account, 'Error de conexión al agregar el rol.', 'error');
    } finally {
        account._addingRole = false;
        account._selectedRoleId = '';
    }
}

async function removeRole(account, role) {
    account._removingRoleId = role.id;
    account._message = null;

    try {
        const res = await fetch(route('users.accounts.roles.destroy', account.system_id, false), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfHeader(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                remote_user_id: account.remote_user_id,
                role_id: role.id,
            }),
        });
        const data = await res.json();

        if (data.status === 'updated') {
            account.roles = account.roles.filter((r) => String(r.id) !== String(role.id));
            flash(account, 'Rol quitado.', 'success');
        } else {
            flash(account, data.message ?? 'No se pudo quitar el rol.', 'error');
        }
    } catch (e) {
        flash(account, 'Error de conexión al quitar el rol.', 'error');
    } finally {
        account._removingRoleId = null;
    }
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
                                    <div class="flex justify-end gap-2">
                                        <button
                                            @click="openDetail(person)"
                                            class="rounded-md border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                        >
                                            Ver
                                        </button>
                                        <button
                                            @click="openEdit(person)"
                                            class="rounded-md border border-indigo-300 px-3 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:border-indigo-700 dark:text-indigo-400 dark:hover:bg-indigo-950"
                                        >
                                            Editar
                                        </button>
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

                            <template v-if="account.has_alias">
                                <dt class="text-gray-400">Alias</dt>
                                <dd class="col-span-2 text-gray-700 dark:text-gray-200">{{ account.alias || '—' }}</dd>
                            </template>

                            <dt class="text-gray-400">Roles</dt>
                            <dd class="col-span-2">
                                <span v-if="!account.roles.length" class="text-gray-400">Sin roles asignados / sistema sin tabla de roles</span>
                                <span v-else class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in account.roles"
                                        :key="role.id"
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                    >
                                        {{ role.name }}
                                    </span>
                                </span>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Modal "Editar" -->
        <Modal :show="showEdit" @close="closeEdit" max-width="2xl">
            <div class="p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Editar — {{ editName }}</h3>
                    <button @click="closeEdit" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">✕</button>
                </div>

                <div v-if="editLoading" class="py-8 text-center text-sm text-gray-400">
                    Cargando...
                </div>

                <div v-else-if="!editAccounts.length" class="py-8 text-center text-sm text-gray-400">
                    No se encontraron cuentas para esta persona.
                </div>

                <div v-else class="max-h-[70vh] space-y-4 overflow-y-auto">
                    <div
                        v-for="account in editAccounts"
                        :key="account.system_key + account.remote_user_id"
                        class="rounded-md border border-gray-200 p-4 dark:border-gray-700"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', colorFor(account.system_key)]">
                                    {{ account.system_name }}
                                </span>
                                <span :class="['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium', accountStatusClass(account.active)]">
                                    {{ accountStatusLabel(account.active) }}
                                </span>
                            </div>

                            <button
                                v-if="account.active_editable"
                                @click="toggleActive(account)"
                                :disabled="account._saving"
                                :class="[
                                    'rounded-md border px-3 py-1 text-xs font-medium disabled:opacity-40',
                                    account.active
                                        ? 'border-red-300 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950'
                                        : 'border-emerald-300 text-emerald-600 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950',
                                ]"
                            >
                                {{ account.active ? 'Dar de baja' : 'Dar de alta' }}
                            </button>
                            <span v-else class="text-xs text-gray-400" title="Este sistema no tiene una columna de estado inequívoca">
                                Estado no editable
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs text-gray-400">Nombre</label>
                                <input
                                    v-model="account.first_name"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                />
                            </div>
                            <div v-if="account.has_last_name">
                                <label class="text-xs text-gray-400">Apellido</label>
                                <input
                                    v-model="account.last_name"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                />
                            </div>
                            <div>
                                <label class="text-xs text-gray-400">Correo</label>
                                <input
                                    v-model="account.email"
                                    type="email"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                />
                            </div>
                            <div v-if="account.has_alias">
                                <label class="text-xs text-gray-400">Alias</label>
                                <input
                                    v-model="account.alias"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                />
                            </div>
                            <div>
                                <label class="text-xs text-gray-400">Nueva contraseña</label>
                                <input
                                    v-model="account._password"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="Dejar en blanco para no cambiarla"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                />
                            </div>
                        </div>

                        <div class="mt-3">
                            <button
                                @click="saveAccount(account)"
                                :disabled="account._saving"
                                class="rounded-md bg-gray-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-700 disabled:opacity-40 dark:bg-gray-200 dark:text-gray-800"
                            >
                                Guardar cambios
                            </button>
                            <span
                                v-if="account._message"
                                :class="['ml-2 text-xs', account._messageType === 'error' ? 'text-red-500' : 'text-emerald-600']"
                            >
                                {{ account._message }}
                            </span>
                        </div>

                        <div class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-700">
                            <label class="text-xs text-gray-400">Roles</label>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span v-if="!account.roles.length" class="text-xs text-gray-400">Sin roles asignados</span>
                                <span
                                    v-for="role in account.roles"
                                    :key="role.id"
                                    class="inline-flex items-center gap-1 rounded-full bg-gray-100 py-0.5 pl-2 pr-1 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                >
                                    {{ role.name }}
                                    <button
                                        v-if="account.roles_editable"
                                        @click="removeRole(account, role)"
                                        :disabled="account._removingRoleId === role.id"
                                        class="rounded-full px-1 text-gray-400 hover:bg-gray-200 hover:text-red-600 disabled:opacity-40 dark:hover:bg-gray-600"
                                        title="Quitar rol"
                                    >
                                        ✕
                                    </button>
                                </span>
                            </div>

                            <div v-if="account.roles_editable" class="mt-2 flex items-center gap-2">
                                <select
                                    v-model="account._selectedRoleId"
                                    @focus="loadRolesFor(account)"
                                    class="w-full max-w-xs rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                >
                                    <option value="">
                                        {{ account._rolesLoading ? 'Cargando roles...' : 'Elegir rol para agregar' }}
                                    </option>
                                    <option v-for="role in account._availableRoles" :key="role.id" :value="role.id">
                                        {{ role.name }}
                                    </option>
                                </select>
                                <button
                                    @click="addRole(account)"
                                    :disabled="!account._selectedRoleId || account._addingRole"
                                    class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    + Agregar
                                </button>
                            </div>
                            <p v-else class="mt-1 text-xs text-gray-400">
                                Este sistema no tiene un mecanismo de roles reconocible.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
