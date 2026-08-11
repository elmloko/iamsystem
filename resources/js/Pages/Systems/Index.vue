<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    systems: Array,
});

const page = usePage();

const q = ref('');
const statusFilter = ref('');
const visibilityFilter = ref('');

const filteredSystems = computed(() => {
    const term = q.value.trim().toLowerCase();

    return props.systems.filter((s) => {
        if (term && !s.name.toLowerCase().includes(term) && !s.key.toLowerCase().includes(term)) {
            return false;
        }
        if (statusFilter.value && s.status !== statusFilter.value) {
            return false;
        }
        if (visibilityFilter.value === 'visible' && !s.visible_in_public_form) {
            return false;
        }
        if (visibilityFilter.value === 'oculto' && s.visible_in_public_form) {
            return false;
        }
        return true;
    });
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

const togglingVisibility = ref(null);

function toggleVisibility(system) {
    togglingVisibility.value = system.id;
    router.patch(route('systems.toggle-visibility', system.id), {}, {
        preserveScroll: true,
        onFinish: () => (togglingVisibility.value = null),
    });
}

const showDelete = ref(false);
const deletingSystem = ref(null);
const deleteForm = useForm({});

function confirmDelete(system) {
    deletingSystem.value = system;
    showDelete.value = true;
}

function closeDelete() {
    showDelete.value = false;
    deletingSystem.value = null;
}

function destroySystem() {
    deleteForm.delete(route('systems.destroy', deletingSystem.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDelete(),
    });
}

function xsrfHeader() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

// Modal "Editar"
const showEdit = ref(false);
const editingSystem = ref(null);
const testResult = ref(null);
const testing = ref(false);

const form = useForm({
    key: '',
    name: '',
    status: 'pending',
    notes: '',
    repo_url: '',
    url_internal: '',
    url_external: '',

    db_driver: 'pgsql',
    db_host: '',
    db_port: '',
    db_database: '',
    db_username: '',
    db_password: '',
    has_password: false,

    users_table: '',
    name_column: '',
    last_name_column: '',
    email_column: '',
    password_column: '',
    password_hash_algo: 'bcrypt',
    model_type: 'App\\Models\\User',

    role_mechanism: 'none',
    role_column: '',
    role_json_column: '',
    roles_table: '',
    role_pivot_table: '',
    role_pivot_user_column: 'user_id',
    role_pivot_role_column: 'role_id',
    hidden_roles_text: '',

    active_type: '',
    active_column: '',
    active_values_text: '',

    alias_column: '',

    created_at_column: '',
    created_at_format: '',
});

function openCreate() {
    editingSystem.value = null;
    testResult.value = null;
    form.reset();
    form.clearErrors();
    form.status = 'pending';
    form.db_driver = 'pgsql';
    form.model_type = 'App\\Models\\User';
    form.role_mechanism = 'none';
    form.role_pivot_user_column = 'user_id';
    form.role_pivot_role_column = 'role_id';
    showEdit.value = true;
}

function openEdit(system) {
    editingSystem.value = system;
    testResult.value = null;
    form.clearErrors();

    form.key = system.key ?? '';
    form.name = system.name ?? '';
    form.status = system.status ?? 'active';
    form.notes = system.notes ?? '';
    form.repo_url = system.repo_url ?? '';
    form.url_internal = system.url_internal ?? '';
    form.url_external = system.url_external ?? '';

    form.db_driver = system.db_driver ?? 'pgsql';
    form.db_host = system.db_host ?? '';
    form.db_port = system.db_port ?? '';
    form.db_database = system.db_database ?? '';
    form.db_username = system.db_username ?? '';
    form.db_password = '';
    form.has_password = system.has_password ?? false;

    form.users_table = system.users_table ?? 'users';
    form.name_column = system.name_column ?? 'name';
    form.last_name_column = system.last_name_column ?? '';
    form.email_column = system.email_column ?? 'email';
    form.password_column = system.password_column ?? 'password';
    form.password_hash_algo = system.password_hash_algo ?? 'bcrypt';
    form.model_type = system.model_type ?? 'App\\Models\\User';

    form.role_mechanism = system.role_mechanism ?? 'none';
    form.role_column = system.role_column ?? '';
    form.role_json_column = system.role_json_column ?? '';
    form.roles_table = system.roles_table ?? '';
    form.role_pivot_table = system.role_pivot_table ?? '';
    form.role_pivot_user_column = system.role_pivot_user_column ?? 'user_id';
    form.role_pivot_role_column = system.role_pivot_role_column ?? 'role_id';
    form.hidden_roles_text = system.hidden_roles_text ?? '';

    form.active_type = system.active_type ?? '';
    form.active_column = system.active_column ?? '';
    form.active_values_text = system.active_values_text ?? '';

    form.alias_column = system.alias_column ?? '';

    form.created_at_column = system.created_at_column ?? '';
    form.created_at_format = system.created_at_format ?? '';

    showEdit.value = true;
}

function closeEdit() {
    showEdit.value = false;
    editingSystem.value = null;
}

function submit() {
    if (editingSystem.value) {
        form.put(route('systems.update', editingSystem.value.id), {
            onSuccess: () => closeEdit(),
        });
    } else {
        form.post(route('systems.store'), {
            onSuccess: () => closeEdit(),
        });
    }
}

async function testConnection() {
    testing.value = true;
    testResult.value = null;

    try {
        const res = await fetch(route('systems.test-connection', undefined, false), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfHeader(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                system_id: editingSystem.value?.id,
                db_driver: form.db_driver,
                db_host: form.db_host,
                db_port: form.db_port || null,
                db_database: form.db_database,
                db_username: form.db_username,
                db_password: form.db_password || null,
            }),
        });
        const data = await res.json();
        testResult.value = data;
    } catch (e) {
        testResult.value = { status: 'error', message: 'Error de conexión al probar.' };
    } finally {
        testing.value = false;
    }
}

// Estado de conexión en vivo (columna "Conexión" en el listado)
const liveConnection = ref({});

async function checkLiveConnection(system) {
    if (!system.db_host || !system.db_database) {
        liveConnection.value = {
            ...liveConnection.value,
            [system.id]: { checking: false, status: 'unavailable' },
        };
        return;
    }

    liveConnection.value = { ...liveConnection.value, [system.id]: { checking: true } };

    try {
        const res = await fetch(route('systems.test-connection', undefined, false), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfHeader(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                system_id: system.id,
                db_driver: system.db_driver,
                db_host: system.db_host,
                db_port: system.db_port || null,
                db_database: system.db_database,
                db_username: system.db_username,
                db_password: null,
            }),
        });
        const data = await res.json();
        liveConnection.value = { ...liveConnection.value, [system.id]: { checking: false, ...data } };
    } catch (e) {
        liveConnection.value = {
            ...liveConnection.value,
            [system.id]: { checking: false, status: 'error', message: 'Error de conexión al probar.' },
        };
    }
}

// Estado de URL en vivo (interna/externa, dentro de la columna "Enlaces")
const liveUrl = ref({});

function urlStatusKey(system, field) {
    return `${system.id}_${field}`;
}

async function checkLiveUrl(system, field) {
    const url = system[field];
    const statusKey = urlStatusKey(system, field);

    if (!url) {
        liveUrl.value = { ...liveUrl.value, [statusKey]: null };
        return;
    }

    liveUrl.value = { ...liveUrl.value, [statusKey]: { checking: true } };

    try {
        const res = await fetch(route('systems.test-url', undefined, false), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfHeader(),
                Accept: 'application/json',
            },
            body: JSON.stringify({ url }),
        });
        const data = await res.json();
        liveUrl.value = { ...liveUrl.value, [statusKey]: { checking: false, ...data } };
    } catch (e) {
        liveUrl.value = {
            ...liveUrl.value,
            [statusKey]: { checking: false, status: 'error', message: 'Sin respuesta.' },
        };
    }
}

// Verifica todo (BD + URLs de todos los sistemas) en una sola petición al
// backend, en vez de disparar decenas de peticiones sueltas: el servidor de
// desarrollo en Windows es de un solo hilo y las serializa, haciéndolo lento.
const verifyingAll = ref(false);

async function checkAllLiveConnections() {
    verifyingAll.value = true;

    const checkingConnection = {};
    const checkingUrl = {};
    props.systems.forEach((system) => {
        checkingConnection[system.id] = { checking: true };
        if (system.url_internal) checkingUrl[urlStatusKey(system, 'url_internal')] = { checking: true };
        if (system.url_external) checkingUrl[urlStatusKey(system, 'url_external')] = { checking: true };
    });
    liveConnection.value = checkingConnection;
    liveUrl.value = checkingUrl;

    try {
        const res = await fetch(route('systems.verify-all', undefined, false), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfHeader(),
                Accept: 'application/json',
            },
        });
        const data = await res.json();

        const connections = {};
        Object.entries(data.connections ?? {}).forEach(([id, result]) => {
            connections[id] = { checking: false, ...result };
        });
        liveConnection.value = connections;

        const urls = {};
        Object.entries(data.urls ?? {}).forEach(([key, result]) => {
            urls[key] = { checking: false, ...result };
        });
        liveUrl.value = urls;
    } catch (e) {
        liveConnection.value = {};
        liveUrl.value = {};
    } finally {
        verifyingAll.value = false;
    }
}

onMounted(() => {
    checkAllLiveConnections();
});
</script>

<template>
    <Head title="Sistemas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">
                    Sistemas
                </h2>
                <PrimaryButton class="normal-case tracking-normal" @click="openCreate">
                    + Añadir sistema
                </PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ systems.length }} sistema(s) registrados en el IAM. Usa "Ver roles" para consultar los roles
                    disponibles directamente en cada base de datos, o "Editar" para ajustar cómo se conecta el IAM a
                    cada sistema.
                </p>

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400"
                >
                    {{ page.props.flash.success }}
                </div>

                <!-- Filtros -->
                <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center">
                    <input
                        v-model="q"
                        type="text"
                        placeholder="Buscar por nombre o clave..."
                        class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 sm:max-w-xs"
                    />
                    <select
                        v-model="statusFilter"
                        class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 sm:max-w-[10rem]"
                    >
                        <option value="">Todos los estados</option>
                        <option value="active">Activo</option>
                        <option value="pending">Pendiente</option>
                    </select>
                    <select
                        v-model="visibilityFilter"
                        class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 sm:max-w-[12rem]"
                    >
                        <option value="">Solicitud pública: todos</option>
                        <option value="visible">Solo visibles</option>
                        <option value="oculto">Solo ocultos</option>
                    </select>
                    <button
                        type="button"
                        @click="checkAllLiveConnections"
                        :disabled="verifyingAll"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        {{ verifyingAll ? 'Verificando...' : '⟳ Verificar conexiones y URLs' }}
                    </button>
                    <span class="text-xs text-slate-400 sm:ml-auto">{{ filteredSystems.length }} de {{ systems.length }}</span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <table class="w-full min-w-[72rem] divide-y divide-slate-200 dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Sistema</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Estado</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Conexión</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Enlaces</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Solicitud pública</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="system in filteredSystems" :key="system.id">
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
                                    <div>{{ system.db_host ? `${system.db_host} / ${system.db_database}` : (system.connection ?? '—') }}</div>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <span
                                            v-if="liveConnection[system.id]?.checking"
                                            class="inline-flex items-center gap-1 text-xs text-slate-400"
                                        >
                                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-slate-400"></span>
                                            Verificando...
                                        </span>
                                        <span
                                            v-else-if="liveConnection[system.id]?.status === 'ok'"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Conectado
                                        </span>
                                        <span
                                            v-else-if="liveConnection[system.id]?.status === 'error'"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-red-500"
                                            :title="liveConnection[system.id]?.message"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                            Sin conexión
                                        </span>
                                        <span
                                            v-else-if="liveConnection[system.id]?.status === 'unavailable'"
                                            class="text-xs text-slate-400"
                                            title="Este sistema usa una conexión fija (legado), no verificable desde aquí."
                                        >
                                            No verificable
                                        </span>
                                        <button
                                            type="button"
                                            @click="checkLiveConnection(system)"
                                            class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                            title="Volver a verificar"
                                        >
                                            ⟳
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top text-xs">
                                    <div class="flex flex-col gap-1.5">
                                        <a
                                            v-if="system.repo_url"
                                            :href="system.repo_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-indigo-600 hover:underline dark:text-indigo-400"
                                        >Repo</a>

                                        <div v-if="system.url_internal" class="flex flex-wrap items-center gap-1.5">
                                            <a
                                                :href="system.url_internal"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-indigo-600 hover:underline dark:text-indigo-400"
                                            >URL interna</a>
                                            <span
                                                v-if="liveUrl[urlStatusKey(system, 'url_internal')]?.checking"
                                                class="inline-flex items-center gap-1 text-slate-400"
                                            >
                                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-slate-400"></span>
                                                Verificando...
                                            </span>
                                            <span
                                                v-else-if="liveUrl[urlStatusKey(system, 'url_internal')]?.status === 'ok'"
                                                class="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                En línea
                                            </span>
                                            <span
                                                v-else-if="liveUrl[urlStatusKey(system, 'url_internal')]?.status === 'error'"
                                                class="inline-flex items-center gap-1 font-medium text-red-500"
                                                :title="liveUrl[urlStatusKey(system, 'url_internal')]?.message"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                                Sin respuesta
                                            </span>
                                            <button
                                                type="button"
                                                @click="checkLiveUrl(system, 'url_internal')"
                                                class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                                title="Volver a verificar"
                                            >⟳</button>
                                        </div>

                                        <div v-if="system.url_external" class="flex flex-wrap items-center gap-1.5">
                                            <a
                                                :href="system.url_external"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-indigo-600 hover:underline dark:text-indigo-400"
                                            >URL externa</a>
                                            <span
                                                v-if="liveUrl[urlStatusKey(system, 'url_external')]?.checking"
                                                class="inline-flex items-center gap-1 text-slate-400"
                                            >
                                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-slate-400"></span>
                                                Verificando...
                                            </span>
                                            <span
                                                v-else-if="liveUrl[urlStatusKey(system, 'url_external')]?.status === 'ok'"
                                                class="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                En línea
                                            </span>
                                            <span
                                                v-else-if="liveUrl[urlStatusKey(system, 'url_external')]?.status === 'error'"
                                                class="inline-flex items-center gap-1 font-medium text-red-500"
                                                :title="liveUrl[urlStatusKey(system, 'url_external')]?.message"
                                            >
                                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                                Sin respuesta
                                            </span>
                                            <button
                                                type="button"
                                                @click="checkLiveUrl(system, 'url_external')"
                                                class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                                title="Volver a verificar"
                                            >⟳</button>
                                        </div>

                                        <span v-if="!system.repo_url && !system.url_internal && !system.url_external" class="text-slate-400">—</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                                            system.visible_in_public_form
                                                ? 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                                        ]"
                                    >
                                        {{ system.visible_in_public_form ? 'Visible' : 'Oculto' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right align-top">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            @click="openRoles(system)"
                                            :disabled="system.status !== 'active'"
                                            class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                        >
                                            Ver roles
                                        </button>
                                        <button
                                            @click="toggleVisibility(system)"
                                            :disabled="togglingVisibility === system.id"
                                            class="rounded-md border border-sky-300 px-3 py-1.5 text-xs font-medium text-sky-600 hover:bg-sky-50 disabled:opacity-40 dark:border-sky-700 dark:text-sky-400 dark:hover:bg-sky-950"
                                        >
                                            {{ system.visible_in_public_form ? 'Ocultar' : 'Mostrar' }}
                                        </button>
                                        <button
                                            @click="openEdit(system)"
                                            class="rounded-md border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:border-indigo-700 dark:text-indigo-400 dark:hover:bg-indigo-950"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            @click="confirmDelete(system)"
                                            class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950"
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filteredSystems.length">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">
                                    {{ systems.length ? 'Ningún sistema coincide con el filtro.' : 'No hay sistemas registrados.' }}
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

        <!-- Modal "Editar sistema" -->
        <Modal :show="showEdit" @close="closeEdit" max-width="3xl">
            <form @submit.prevent="submit" class="max-h-[85vh] overflow-y-auto p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">
                        {{ editingSystem ? `Editar — ${editingSystem.name}` : 'Añadir sistema' }}
                    </h3>
                    <button type="button" @click="closeEdit" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">✕</button>
                </div>

                <div class="space-y-6">
                    <!-- Datos generales -->
                    <section class="space-y-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Datos generales</h4>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div v-if="!editingSystem">
                                <InputLabel value="Clave interna (sin espacios, ej: mi_sistema)" />
                                <TextInput v-model="form.key" type="text" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.key" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Nombre" />
                                <TextInput v-model="form.name" type="text" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.name" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Estado" />
                                <select v-model="form.status" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="active">Activo</option>
                                    <option value="pending">Pendiente</option>
                                </select>
                                <p v-if="!editingSystem" class="mt-1 text-xs text-slate-400">
                                    Déjalo en "Pendiente" hasta confirmar la conexión con "Probar conexión".
                                </p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <InputLabel value="Repo Git" />
                                <TextInput v-model="form.repo_url" type="text" class="mt-1 block w-full" placeholder="https://github.com/org/repo.git" />
                            </div>
                            <div>
                                <InputLabel value="URL interna" />
                                <TextInput v-model="form.url_internal" type="text" class="mt-1 block w-full" placeholder="http://172.65.10.55:8000" />
                            </div>
                            <div>
                                <InputLabel value="URL externa" />
                                <TextInput v-model="form.url_external" type="text" class="mt-1 block w-full" placeholder="https://sistema.correos.gob.bo" />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Notas" />
                            <textarea v-model="form.notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"></textarea>
                        </div>
                    </section>

                    <!-- Conexión -->
                    <section class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Conexión a la base de datos</h4>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <InputLabel value="Motor" />
                                <select v-model="form.db_driver" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="pgsql">PostgreSQL</option>
                                    <option value="mysql">MySQL</option>
                                    <option value="sqlsrv">SQL Server</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Host" />
                                <TextInput v-model="form.db_host" type="text" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.db_host" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Puerto" />
                                <TextInput v-model="form.db_port" type="text" class="mt-1 block w-full" placeholder="5432" />
                            </div>
                            <div>
                                <InputLabel value="Base de datos" />
                                <TextInput v-model="form.db_database" type="text" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.db_database" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Usuario" />
                                <TextInput v-model="form.db_username" type="text" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.db_username" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Contraseña" />
                                <TextInput
                                    v-model="form.db_password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    autocomplete="new-password"
                                    :placeholder="form.has_password ? 'Dejar en blanco para no cambiarla' : ''"
                                />
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <SecondaryButton type="button" @click="testConnection" :disabled="testing">
                                {{ testing ? 'Probando...' : 'Probar conexión' }}
                            </SecondaryButton>
                            <span
                                v-if="testResult"
                                :class="['text-xs', testResult.status === 'ok' ? 'text-emerald-600' : 'text-red-500']"
                            >
                                {{ testResult.message }}
                            </span>
                        </div>
                    </section>

                    <!-- Tabla y columnas -->
                    <section class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tabla y columnas de usuario</h4>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <InputLabel value="Tabla" />
                                <TextInput v-model="form.users_table" type="text" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <InputLabel value="Columna nombre" />
                                <TextInput v-model="form.name_column" type="text" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <InputLabel value="Columna apellido (opcional)" />
                                <TextInput v-model="form.last_name_column" type="text" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel value="Columna correo" />
                                <TextInput v-model="form.email_column" type="text" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <InputLabel value="Columna contraseña" />
                                <TextInput v-model="form.password_column" type="text" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <InputLabel value="Algoritmo de contraseña" />
                                <select v-model="form.password_hash_algo" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="bcrypt">Bcrypt (estándar Laravel)</option>
                                    <option value="sha256">SHA-256</option>
                                    <option value="sha1">SHA-1</option>
                                    <option value="md5">MD5</option>
                                    <option value="plain">Texto plano (sin hash)</option>
                                </select>
                                <p class="mt-1 text-xs text-slate-400">
                                    Cómo espera este sistema que se guarde la contraseña. Si no coincide, la cuenta se crea pero no puede iniciar sesión.
                                </p>
                            </div>
                            <div>
                                <InputLabel value="Columna alias (opcional)" />
                                <TextInput v-model="form.alias_column" type="text" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel value="Columna fecha de alta (opcional)" />
                                <TextInput v-model="form.created_at_column" type="text" class="mt-1 block w-full" placeholder="ej. created_at, fecha_creacion" />
                            </div>
                            <div>
                                <InputLabel value="Formato de fecha de alta" />
                                <select v-model="form.created_at_format" class="mt-1 block w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                                    <option value="">Sin especificar (usa created_at/updated_at si existen)</option>
                                    <option value="datetime">Fecha/hora normal</option>
                                    <option value="unix">Timestamp Unix (entero)</option>
                                </select>
                                <p class="mt-1 text-xs text-slate-400">
                                    Si el sistema no usa created_at/updated_at estándar de Laravel, mapea aquí su columna real (ej. SIGEC usa fecha_creacion como timestamp Unix). Sin esto, esa columna queda vacía al crear la cuenta.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Roles -->
                    <section class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Roles</h4>
                        <InputLabel value="Mecanismo" />
                        <select v-model="form.role_mechanism" class="block w-full max-w-xs rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                            <option value="none">Sin roles</option>
                            <option value="column">Columna de texto simple</option>
                            <option value="json">Columna JSON (arreglo de roles)</option>
                            <option value="pivot">Tabla de roles + pivote</option>
                        </select>

                        <div v-if="form.role_mechanism === 'column'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Columna de rol" />
                                <TextInput v-model="form.role_column" type="text" class="mt-1 block w-full" />
                            </div>
                        </div>

                        <div v-if="form.role_mechanism === 'json'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Columna JSON de roles" />
                                <TextInput v-model="form.role_json_column" type="text" class="mt-1 block w-full" />
                            </div>
                        </div>

                        <div v-if="form.role_mechanism === 'pivot'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Tabla de roles" />
                                <TextInput v-model="form.roles_table" type="text" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel value="Tabla pivote" />
                                <TextInput v-model="form.role_pivot_table" type="text" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <InputLabel value="Columna usuario en pivote" />
                                <TextInput v-model="form.role_pivot_user_column" type="text" class="mt-1 block w-full" />
                                <p class="mt-1 text-xs text-slate-400">Usa "model_id" si es pivote polimórfico (spatie/laravel-permission).</p>
                            </div>
                            <div>
                                <InputLabel value="Columna rol en pivote" />
                                <TextInput v-model="form.role_pivot_role_column" type="text" class="mt-1 block w-full" />
                            </div>
                            <div v-if="form.role_pivot_user_column === 'model_id'" class="sm:col-span-2">
                                <InputLabel value="Model type (pivote polimórfico)" />
                                <TextInput v-model="form.model_type" type="text" class="mt-1 block w-full" />
                            </div>
                        </div>

                        <div v-if="form.role_mechanism === 'column' || form.role_mechanism === 'pivot'">
                            <InputLabel value="Roles ocultos en el IAM (separados por coma)" />
                            <TextInput v-model="form.hidden_roles_text" type="text" class="mt-1 block w-full" placeholder="empresa" />
                            <p class="mt-1 text-xs text-slate-400">
                                Las cuentas con alguno de estos roles no aparecen en ningún listado, conteo ni búsqueda del IAM.
                            </p>
                        </div>
                    </section>

                    <!-- Estado de cuenta -->
                    <section class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Alta / Baja de cuentas</h4>
                        <InputLabel value="Tipo de columna" />
                        <select v-model="form.active_type" class="block w-full max-w-xs rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                            <option value="">Sin columna de estado</option>
                            <option value="boolean">Booleana (true/false)</option>
                            <option value="soft_delete">Soft-delete (deleted_at)</option>
                            <option value="text">Texto (estado/status)</option>
                        </select>

                        <div v-if="form.active_type" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Columna" />
                                <TextInput v-model="form.active_column" type="text" class="mt-1 block w-full" />
                            </div>
                            <div v-if="form.active_type === 'text'">
                                <InputLabel value='Valores que cuentan como "activo" (separados por coma)' />
                                <TextInput v-model="form.active_values_text" type="text" class="mt-1 block w-full" placeholder="activo, active, habilitado" />
                            </div>
                        </div>
                    </section>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <SecondaryButton type="button" @click="closeEdit">Cancelar</SecondaryButton>
                    <PrimaryButton class="normal-case tracking-normal" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ editingSystem ? 'Guardar cambios' : 'Crear sistema' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal confirmar eliminación -->
        <Modal :show="showDelete" @close="closeDelete" max-width="sm">
            <div class="p-6">
                <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">
                    ¿Eliminar "{{ deletingSystem?.name }}" del IAM?
                </h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Se borra la configuración de este sistema y su historial local (cuentas registradas y solicitudes
                    de acceso asociadas). <strong>No borra nada en la base de datos real del sistema</strong> — las
                    cuentas que ya existen ahí siguen intactas, solo dejan de administrarse desde este panel. No se
                    puede deshacer.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDelete">Cancelar</SecondaryButton>
                    <DangerButton :class="{ 'opacity-25': deleteForm.processing }" :disabled="deleteForm.processing" @click="destroySystem">
                        Eliminar
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
