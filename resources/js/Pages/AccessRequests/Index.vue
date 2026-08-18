<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    people: Array,
    systems: Array,
});

const page = usePage();
const processingId = ref(null);
const expanded = ref({});

function toggleExpanded(email) {
    expanded.value[email] = !expanded.value[email];
}

const showDelete = ref(false);
const deletingPerson = ref(null);
const deleteForm = useForm({});

function confirmDelete(person) {
    deletingPerson.value = person;
    showDelete.value = true;
}

function closeDelete() {
    showDelete.value = false;
    deletingPerson.value = null;
}

function destroyPerson() {
    deleteForm.transform(() => ({
        access_request_ids: deletingPerson.value.access_request_ids,
    })).delete(route('access-requests.destroy-person'), {
        preserveScroll: true,
        onSuccess: () => closeDelete(),
    });
}

function statusLabel(status) {
    return { pending: 'Pendiente', approved: 'Aprobado', rejected: 'Rechazado' }[status] ?? status;
}

function statusClass(status) {
    return {
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    }[status] ?? 'bg-gray-100 text-gray-600';
}

function outcomeLabel(status) {
    return { created: 'Creado', exists: 'Ya existía', failed: 'Falló' }[status] ?? status;
}

function approve(item) {
    processingId.value = item.id;
    router.post(route('access-requests.approve', item.id), {}, {
        preserveScroll: true,
        onFinish: () => (processingId.value = null),
    });
}

function reject(item) {
    processingId.value = item.id;
    router.post(route('access-requests.reject', item.id), {}, {
        preserveScroll: true,
        onFinish: () => (processingId.value = null),
    });
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value.replace(' ', 'T'));
    if (isNaN(d)) return value;
    return d.toLocaleString('es-BO', { dateStyle: 'medium', timeStyle: 'short' });
}

function extraFieldEntries(item) {
    return Object.entries(item.extra_fields ?? {}).filter(([, v]) => v !== null && v !== '' && (!Array.isArray(v) || v.length));
}

function fieldConfig(item, column) {
    const system = props.systems.find((s) => s.id === item.system_id);
    return system?.extra_fields?.find((f) => f.column === column) ?? null;
}

function fieldLabel(item, column) {
    return fieldConfig(item, column)?.label ?? column;
}

function fieldDisplayValue(item, column, value) {
    const options = fieldConfig(item, column)?.options ?? [];
    const resolve = (v) => options.find((o) => String(o.value) === String(v))?.label ?? v;

    return Array.isArray(value) ? value.map(resolve).join(', ') : resolve(value);
}

// --- Editar solicitud (corregir lo que la persona llenó mal antes de aprobar) ---
const showEdit = ref(false);
const editingItem = ref(null);
const rolesState = reactive({ loading: false, roles: [] });
const extraFieldsState = reactive({ loading: false, fields: [] });

const editForm = useForm({
    name: '',
    email: '',
    role_id: null,
    role_name: null,
    alias: '',
    extra_fields: {},
});

function systemFor(item) {
    return item ? props.systems.find((s) => s.id === item.system_id) : null;
}

async function openEdit(item) {
    editingItem.value = item;
    editForm.clearErrors();
    editForm.name = item.requester_name;
    editForm.email = item.requester_email;
    editForm.role_id = item.role_id;
    editForm.role_name = item.role_name;
    editForm.alias = item.alias ?? '';
    editForm.extra_fields = { ...(item.extra_fields ?? {}) };
    showEdit.value = true;

    rolesState.loading = true;
    extraFieldsState.loading = true;
    try {
        const [rolesRes, fieldsRes] = await Promise.all([
            fetch(route('users.roles', item.system_id, false)),
            fetch(route('users.extra-fields', item.system_id, false)),
        ]);
        const rolesData = await rolesRes.json();
        const fieldsData = await fieldsRes.json();
        rolesState.roles = rolesData.roles ?? [];
        extraFieldsState.fields = fieldsData.fields ?? [];
    } catch (e) {
        rolesState.roles = [];
        extraFieldsState.fields = [];
    } finally {
        rolesState.loading = false;
        extraFieldsState.loading = false;
    }
}

function closeEdit() {
    showEdit.value = false;
    editingItem.value = null;
}

function setRole(role) {
    editForm.role_id = role ? role.id : null;
    editForm.role_name = role ? role.name : null;
}

function setExtraField(column, value) {
    editForm.extra_fields[column] = value;
}

function toggleExtraFieldValue(column, value, checked) {
    const current = Array.isArray(editForm.extra_fields[column]) ? editForm.extra_fields[column] : [];
    editForm.extra_fields[column] = checked ? [...current, value] : current.filter((v) => v !== value);
}

function submitEdit() {
    editForm.put(route('access-requests.items.update', editingItem.value.id), {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
}
</script>

<template>
    <Head title="Solicitudes de acceso" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">
                Solicitudes de acceso
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Agrupado por persona: si alguien pidió acceso más de una vez, todo aparece en una sola tarjeta.
                    Aprueba o rechaza sistema por sistema — la cuenta recién se crea en el sistema real al aprobar.
                </p>

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="rounded-lg bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700 dark:bg-red-950 dark:text-red-400"
                >
                    {{ page.props.flash.error }}
                </div>

                <div v-if="!people.length" class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
                    No hay solicitudes de acceso todavía.
                </div>

                <div
                    v-for="person in people"
                    :key="person.email"
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex w-full items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                        <button
                            type="button"
                            @click="toggleExpanded(person.email)"
                            class="flex flex-1 items-center gap-2 text-left"
                        >
                            <svg
                                class="h-4 w-4 shrink-0 text-slate-400 transition-transform"
                                :class="{ 'rotate-90': expanded[person.email] }"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path fill-rule="evenodd" d="M7.3 4.3a1 1 0 011.4 0l5 5a1 1 0 010 1.4l-5 5a1 1 0 01-1.4-1.4L11.6 10 7.3 5.7a1 1 0 010-1.4z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ person.name }}</p>
                                <p class="text-xs text-slate-400">{{ person.email }}</p>
                            </div>
                        </button>

                        <div class="flex items-center gap-2">
                            <span
                                v-if="person.pending_count"
                                class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200"
                            >
                                {{ person.pending_count }} pendiente{{ person.pending_count === 1 ? '' : 's' }}
                            </span>
                            <button
                                type="button"
                                @click="confirmDelete(person)"
                                title="Eliminar solicitud"
                                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950 dark:hover:text-red-400"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.5 2a1.5 1.5 0 00-1.5 1.5V4H4a1 1 0 000 2h.1l.8 10.4A2 2 0 006.9 18h6.2a2 2 0 002-1.6L15.9 6h.1a1 1 0 100-2h-3V3.5A1.5 1.5 0 0011.5 2h-3zM10 8a1 1 0 011 1v6a1 1 0 11-2 0V9a1 1 0 011-1zm-3 1a1 1 0 012 0v6a1 1 0 11-2 0V9zm7-1a1 1 0 00-1 1v6a1 1 0 102 0V9a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-if="expanded[person.email]" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div v-for="item in person.items" :key="item.id" class="p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ item.system_name }}</span>
                                    <span v-if="item.role_name" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {{ item.role_name }}
                                    </span>
                                    <span :class="['rounded-full px-2.5 py-1 text-xs font-medium', statusClass(item.status)]">
                                        {{ statusLabel(item.status) }}
                                    </span>
                                    <span v-if="item.outcome_status" class="text-xs text-slate-400">
                                        ({{ outcomeLabel(item.outcome_status) }})
                                    </span>
                                </div>

                                <div v-if="item.status === 'pending'" class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400">{{ formatDate(item.requested_at) }}</span>
                                    <button
                                        @click="openEdit(item)"
                                        class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        @click="approve(item)"
                                        :disabled="processingId === item.id"
                                        class="rounded-md border border-emerald-300 px-3 py-1 text-xs font-medium text-emerald-600 hover:bg-emerald-50 disabled:opacity-40 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950"
                                    >
                                        Aprobar
                                    </button>
                                    <button
                                        @click="reject(item)"
                                        :disabled="processingId === item.id"
                                        class="rounded-md border border-red-300 px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-40 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950"
                                    >
                                        Rechazar
                                    </button>
                                </div>
                                <span v-else-if="item.decided_by_name" class="text-xs text-slate-400">
                                    Por {{ item.decided_by_name }} — {{ formatDate(item.decided_at) }}
                                </span>
                            </div>

                            <p v-if="item.outcome_message" class="mt-1 text-xs text-amber-600">{{ item.outcome_message }}</p>

                            <dl v-if="extraFieldEntries(item).length || item.alias" class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-xs sm:grid-cols-3">
                                <div v-if="item.alias">
                                    <dt class="text-slate-400">Alias</dt>
                                    <dd class="text-slate-600 dark:text-slate-300">{{ item.alias }}</dd>
                                </div>
                                <div v-for="[key, value] in extraFieldEntries(item)" :key="key">
                                    <dt class="text-slate-400">{{ fieldLabel(item, key) }}</dt>
                                    <dd class="text-slate-600 dark:text-slate-300">{{ fieldDisplayValue(item, key, value) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showEdit" @close="closeEdit" max-width="lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">
                    Corregir solicitud — {{ editingItem?.system_name }}
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Arreglá lo que la persona llenó mal antes de aprobar.
                </p>

                <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="edit-name" value="Nombre" />
                            <TextInput id="edit-name" v-model="editForm.name" type="text" class="mt-1 block w-full" />
                            <InputError :message="editForm.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="edit-email" value="Email" />
                            <TextInput id="edit-email" v-model="editForm.email" type="email" class="mt-1 block w-full" />
                            <InputError :message="editForm.errors.email" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Rol" />
                        <select
                            class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                            :disabled="rolesState.loading"
                            :value="editForm.role_id"
                            @change="setRole(rolesState.roles.find(r => String(r.id) === $event.target.value))"
                        >
                            <option value="">{{ rolesState.loading ? 'Cargando roles...' : 'Sin rol' }}</option>
                            <option v-for="role in rolesState.roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select>
                    </div>

                    <div v-if="systemFor(editingItem)?.alias_column">
                        <InputLabel value="Alias" />
                        <TextInput
                            v-model="editForm.alias"
                            type="text"
                            :required="systemFor(editingItem)?.alias_required"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="editForm.errors.alias" class="mt-1" />
                    </div>

                    <div
                        v-if="extraFieldsState.fields.length"
                        class="grid grid-cols-1 gap-3 rounded-md bg-gray-50 p-3 dark:bg-gray-900/40 sm:grid-cols-2"
                    >
                        <div v-for="field in extraFieldsState.fields" :key="field.column">
                            <label class="text-xs text-gray-500 dark:text-gray-400">
                                {{ field.label }}<span v-if="field.required" class="text-red-500">*</span>
                            </label>
                            <p v-if="field.help" class="mb-1 text-xs italic text-gray-400">{{ field.help }}</p>

                            <select
                                v-if="field.type === 'select'"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                :value="editForm.extra_fields[field.column] ?? ''"
                                @change="setExtraField(field.column, $event.target.value)"
                            >
                                <option value="">Seleccionar...</option>
                                <option v-for="opt in field.options ?? []" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>

                            <div v-else-if="field.type === 'multiselect'" class="mt-1 flex flex-wrap gap-2">
                                <label
                                    v-for="opt in field.options ?? []"
                                    :key="opt.value"
                                    class="flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"
                                        :checked="(Array.isArray(editForm.extra_fields[field.column]) ? editForm.extra_fields[field.column] : []).includes(opt.value)"
                                        @change="toggleExtraFieldValue(field.column, opt.value, $event.target.checked)"
                                    />
                                    {{ opt.label }}
                                </label>
                            </div>

                            <input
                                v-else
                                :type="field.type === 'date' ? 'date' : 'text'"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                :value="editForm.extra_fields[field.column] ?? ''"
                                @input="setExtraField(field.column, $event.target.value)"
                            />
                        </div>
                    </div>
                    <InputError :message="editForm.errors.extra_fields" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeEdit">Cancelar</SecondaryButton>
                    <PrimaryButton :disabled="editForm.processing" @click="submitEdit">Guardar</PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showDelete" @close="closeDelete" max-width="sm">
            <div class="p-6">
                <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">
                    ¿Eliminar la solicitud de {{ deletingPerson?.name }}?
                </h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Se borra por completo el registro de la solicitud (todos los sistemas que aparecen en esta
                    tarjeta), incluidos los ya aprobados o rechazados. Esto no elimina ninguna cuenta ya creada en
                    los sistemas remotos, solo el historial de la solicitud en el IAM. No se puede deshacer.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDelete">Cancelar</SecondaryButton>
                    <DangerButton :class="{ 'opacity-25': deleteForm.processing }" :disabled="deleteForm.processing" @click="destroyPerson">
                        Eliminar
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
