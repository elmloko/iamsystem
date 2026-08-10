<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    systems: Array,
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    systems: [], // [{ system_id, role_id, role_name }]
});

// Estado de roles por sistema: { [systemId]: { loading, roles, selectedRoleId } }
const rolesState = reactive({});

// Estado de campos extra por sistema: { [systemId]: { loading, fields } }
const extraFieldsState = reactive({});

function isChecked(systemId) {
    return form.systems.some((s) => s.system_id === systemId);
}

async function toggleSystem(system) {
    const idx = form.systems.findIndex((s) => s.system_id === system.id);

    if (idx !== -1) {
        form.systems.splice(idx, 1);
        return;
    }

    form.systems.push({ system_id: system.id, role_id: null, role_name: null, alias: '', extra_fields: {} });

    if (!rolesState[system.id]) {
        rolesState[system.id] = { loading: true, roles: [] };
        try {
            const res = await fetch(route('users.roles', system.id, false));
            const data = await res.json();
            rolesState[system.id] = { loading: false, roles: data.roles ?? [] };
        } catch (e) {
            rolesState[system.id] = { loading: false, roles: [] };
        }
    }

    if (!extraFieldsState[system.id]) {
        extraFieldsState[system.id] = { loading: true, fields: [] };
        try {
            const res = await fetch(route('users.extra-fields', system.id, false));
            const data = await res.json();
            extraFieldsState[system.id] = { loading: false, fields: data.fields ?? [] };
        } catch (e) {
            extraFieldsState[system.id] = { loading: false, fields: [] };
        }
    }
}

function setExtraField(systemId, column, value) {
    const entry = entryFor(systemId);
    if (entry) {
        entry.extra_fields[column] = value;
    }
}

function toggleExtraFieldValue(systemId, column, value, checked) {
    const entry = entryFor(systemId);
    if (!entry) return;

    const current = Array.isArray(entry.extra_fields[column]) ? entry.extra_fields[column] : [];
    entry.extra_fields[column] = checked
        ? [...current, value]
        : current.filter((v) => v !== value);
}

function setRole(systemId, role) {
    const entry = form.systems.find((s) => s.system_id === systemId);
    if (entry) {
        entry.role_id = role ? role.id : null;
        entry.role_name = role ? role.name : null;
    }
}

function entryFor(systemId) {
    return form.systems.find((s) => s.system_id === systemId);
}

function submit() {
    form.post(route('users.store'));
}
</script>

<template>
    <Head title="Crear usuario" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Crear usuario
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form
                    @submit.prevent="submit"
                    class="space-y-6 rounded-md border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div>
                        <InputLabel for="name" value="Nombre" />
                        <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.email" class="mt-1" />
                        <p class="mt-1 text-xs text-gray-400">
                            Si ya existe una persona con este email en el IAM, se reutiliza en vez de duplicarla.
                        </p>
                    </div>

                    <div>
                        <InputLabel for="password" value="Contraseña inicial" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required minlength="8" />
                        <InputError :message="form.errors.password" class="mt-1" />
                        <p class="mt-1 text-xs text-gray-400">
                            Se usará la misma contraseña en todos los sistemas seleccionados.
                        </p>
                    </div>

                    <div>
                        <InputLabel value="Sistemas y roles" />
                        <InputError :message="form.errors.systems" class="mt-1" />

                        <div class="mt-2 divide-y divide-gray-100 rounded-md border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                            <div v-if="!systems.length" class="p-4 text-sm text-gray-400">
                                No hay sistemas activos configurados todavía.
                            </div>

                            <div v-for="system in systems" :key="system.id" class="flex flex-col gap-2 p-3">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                        <input
                                            type="checkbox"
                                            :checked="isChecked(system.id)"
                                            @change="toggleSystem(system)"
                                            class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"
                                        />
                                        {{ system.name }}
                                    </label>

                                    <div v-if="isChecked(system.id)" class="sm:w-56">
                                        <select
                                            class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                            :disabled="rolesState[system.id]?.loading"
                                            @change="setRole(system.id, rolesState[system.id]?.roles.find(r => String(r.id) === $event.target.value))"
                                        >
                                            <option value="">
                                                {{ rolesState[system.id]?.loading ? 'Cargando roles...' : 'Sin rol' }}
                                            </option>
                                            <option v-for="role in rolesState[system.id]?.roles ?? []" :key="role.id" :value="role.id">
                                                {{ role.name }}
                                            </option>
                                        </select>
                                        <p v-if="!rolesState[system.id]?.loading && rolesState[system.id]?.roles.length === 0" class="mt-1 text-xs text-amber-600">
                                            Este sistema no expone una tabla de roles reconocible.
                                        </p>
                                    </div>
                                </div>

                                <div v-if="isChecked(system.id) && system.alias_column" class="sm:max-w-xs">
                                    <label class="text-xs text-gray-500 dark:text-gray-400">
                                        Alias<span v-if="system.alias_required" class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="entryFor(system.id).alias"
                                        type="text"
                                        :required="system.alias_required"
                                        :placeholder="system.alias_required ? '' : 'Opcional'"
                                        class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                    />
                                </div>

                                <div
                                    v-if="isChecked(system.id) && extraFieldsState[system.id]?.fields.length"
                                    class="grid grid-cols-1 gap-3 rounded-md bg-gray-50 p-3 dark:bg-gray-900/40 sm:grid-cols-2"
                                >
                                    <div v-for="field in extraFieldsState[system.id].fields" :key="field.column">
                                        <label class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ field.label }}<span v-if="field.required" class="text-red-500">*</span>
                                        </label>
                                        <p v-if="field.help" class="mb-1 text-xs italic text-gray-400">{{ field.help }}</p>

                                        <select
                                            v-if="field.type === 'select'"
                                            class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                            :required="field.required"
                                            @change="setExtraField(system.id, field.column, $event.target.value)"
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
                                                    @change="toggleExtraFieldValue(system.id, field.column, opt.value, $event.target.checked)"
                                                />
                                                {{ opt.label }}
                                            </label>
                                        </div>

                                        <input
                                            v-else
                                            :type="field.type === 'date' ? 'date' : 'text'"
                                            class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                            :required="field.required"
                                            @input="setExtraField(system.id, field.column, $event.target.value)"
                                        />
                                    </div>
                                </div>
                                <p v-else-if="isChecked(system.id) && extraFieldsState[system.id]?.loading" class="text-xs text-gray-400">
                                    Cargando campos adicionales...
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <PrimaryButton :disabled="form.processing">
                            Crear usuario
                        </PrimaryButton>
                        <span v-if="form.processing" class="text-sm text-gray-400">Aprovisionando en los sistemas seleccionados...</span>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
