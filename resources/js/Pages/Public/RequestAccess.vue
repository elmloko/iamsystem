<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineProps({
    systems: Array,
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    systems: [], // [{ system_id, role_id, role_name, alias, extra_fields }]
});

const rolesState = reactive({});
const extraFieldsState = reactive({});

function isChecked(systemId) {
    return form.systems.some((s) => s.system_id === systemId);
}

function entryFor(systemId) {
    return form.systems.find((s) => s.system_id === systemId);
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
            const res = await fetch(route('access-requests.roles', system.id, false));
            const data = await res.json();
            rolesState[system.id] = { loading: false, roles: data.roles ?? [] };
        } catch (e) {
            rolesState[system.id] = { loading: false, roles: [] };
        }
    }

    // Si el sistema solo tiene un rol disponible (restringido en público), se
    // deja puesto de una — no tiene sentido hacer elegir algo sin opciones.
    if (rolesState[system.id].roles.length === 1) {
        setRole(system.id, rolesState[system.id].roles[0]);
    }

    if (!extraFieldsState[system.id]) {
        extraFieldsState[system.id] = { loading: true, fields: [] };
        try {
            const res = await fetch(route('access-requests.extra-fields', system.id, false));
            const data = await res.json();
            extraFieldsState[system.id] = { loading: false, fields: data.fields ?? [] };
        } catch (e) {
            extraFieldsState[system.id] = { loading: false, fields: [] };
        }
    }

    // Mismo criterio para los campos extra: si el select quedó con una sola
    // opción posible, se autocompleta en vez de mostrar un selector vacío.
    for (const field of extraFieldsState[system.id].fields) {
        if (field.type === 'select' && (field.options ?? []).length === 1) {
            setExtraField(system.id, field.column, field.options[0].value);
        }
    }
}

function setRole(systemId, role) {
    const entry = entryFor(systemId);
    if (entry) {
        entry.role_id = role ? role.id : null;
        entry.role_name = role ? role.name : null;
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

function submit() {
    form.post(route('access-requests.store'));
}
</script>

<template>
    <Head title="Solicitar acceso" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto flex max-w-3xl items-center gap-3 px-6 py-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white">
                    <ApplicationLogo class="h-5 w-5" />
                </span>
                <div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">IAM AGBC</p>
                    <p class="text-xs text-slate-400">Solicitud de acceso a sistemas</p>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div class="mb-6">
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Solicitar acceso</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Completa tus datos y elige a qué sistemas necesitas acceso. Un administrador revisará y aprobará
                    cada sistema por separado antes de que tu cuenta quede activa.
                </p>
            </div>

            <form
                @submit.prevent="submit"
                class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="name" value="Nombre completo" />
                        <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="email" value="Correo electrónico" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="password" value="Contraseña" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required minlength="8" autocomplete="new-password" />
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="password_confirmation" value="Confirmar contraseña" />
                        <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                    </div>
                </div>
                <p class="text-xs text-slate-400">
                    Esta será la contraseña de tus cuentas nuevas. Se usará la misma en todos los sistemas que elijas.
                </p>

                <div>
                    <InputLabel value="¿A qué sistemas necesitas acceso?" />
                    <InputError :message="form.errors.systems" class="mt-1" />

                    <div class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800">
                        <div v-if="!systems.length" class="p-4 text-sm text-slate-400">
                            No hay sistemas disponibles por el momento.
                        </div>

                        <div v-for="system in systems" :key="system.id" class="flex flex-col gap-2 p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                    <input
                                        type="checkbox"
                                        :checked="isChecked(system.id)"
                                        @change="toggleSystem(system)"
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    {{ system.name }}
                                </label>

                                <div v-if="isChecked(system.id)" class="sm:w-56">
                                    <select
                                        class="w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
                                        :disabled="rolesState[system.id]?.loading"
                                        :value="entryFor(system.id)?.role_id ?? ''"
                                        @change="setRole(system.id, rolesState[system.id]?.roles.find(r => String(r.id) === $event.target.value))"
                                    >
                                        <option value="">
                                            {{ rolesState[system.id]?.loading ? 'Cargando roles...' : 'Sin rol' }}
                                        </option>
                                        <option v-for="role in rolesState[system.id]?.roles ?? []" :key="role.id" :value="role.id">
                                            {{ role.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="isChecked(system.id) && system.alias_column" class="sm:max-w-xs">
                                <label class="text-xs text-slate-500 dark:text-slate-400">
                                    Alias de acceso<span v-if="system.alias_required" class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="entryFor(system.id).alias"
                                    type="text"
                                    :required="system.alias_required"
                                    :placeholder="system.alias_required ? '' : 'Opcional'"
                                    class="mt-1 w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
                                />
                            </div>

                            <div
                                v-if="isChecked(system.id) && extraFieldsState[system.id]?.fields.length"
                                class="grid grid-cols-1 gap-3 rounded-md bg-slate-50 p-3 dark:bg-slate-800/40 sm:grid-cols-2"
                            >
                                <div v-for="field in extraFieldsState[system.id].fields" :key="field.column">
                                    <label class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ field.label }}<span v-if="field.required" class="text-red-500">*</span>
                                    </label>
                                    <p v-if="field.help" class="mb-1 text-xs italic text-slate-400">{{ field.help }}</p>

                                    <select
                                        v-if="field.type === 'select'"
                                        class="mt-1 w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
                                        :required="field.required"
                                        :value="entryFor(system.id)?.extra_fields[field.column] ?? ''"
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
                                            class="flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300"
                                        >
                                            <input
                                                type="checkbox"
                                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                @change="toggleExtraFieldValue(system.id, field.column, opt.value, $event.target.checked)"
                                            />
                                            {{ opt.label }}
                                        </label>
                                    </div>

                                    <input
                                        v-else
                                        :type="field.type === 'date' ? 'date' : 'text'"
                                        class="mt-1 w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
                                        :required="field.required"
                                        @input="setExtraField(system.id, field.column, $event.target.value)"
                                    />
                                </div>
                            </div>
                            <p v-else-if="isChecked(system.id) && extraFieldsState[system.id]?.loading" class="text-xs text-slate-400">
                                Cargando campos adicionales...
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <PrimaryButton class="normal-case tracking-normal" :disabled="form.processing">
                        Enviar solicitud
                    </PrimaryButton>
                    <span v-if="form.processing" class="text-sm text-slate-400">Enviando...</span>
                </div>
            </form>

            <p class="mt-6 text-center text-xs text-slate-400">
                ¿Ya tienes acceso al panel?
                <Link :href="route('login')" class="font-medium text-indigo-600 hover:text-indigo-500">Inicia sesión aquí</Link>
            </p>
        </main>
    </div>
</template>
