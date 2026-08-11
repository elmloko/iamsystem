<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    admins: Array,
});

const page = usePage();

// Modal crear/editar
const showForm = ref(false);
const editingAdmin = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function openCreate() {
    editingAdmin.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function openEdit(admin) {
    editingAdmin.value = admin;
    form.reset();
    form.clearErrors();
    form.name = admin.name;
    form.email = admin.email;
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (editingAdmin.value) {
        form.put(route('admins.update', editingAdmin.value.id), {
            onSuccess: () => closeForm(),
        });
    } else {
        form.post(route('admins.store'), {
            onSuccess: () => closeForm(),
        });
    }
}

// Modal eliminar
const showDelete = ref(false);
const deletingAdmin = ref(null);
const deleteForm = useForm({});

function confirmDelete(admin) {
    deletingAdmin.value = admin;
    showDelete.value = true;
}

function closeDelete() {
    showDelete.value = false;
    deletingAdmin.value = null;
}

function destroyAdmin() {
    deleteForm.delete(route('admins.destroy', deletingAdmin.value.id), {
        onSuccess: () => closeDelete(),
        preserveScroll: true,
    });
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value.replace(' ', 'T'));
    if (isNaN(d)) return value;
    return d.toLocaleString('es-BO', { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<template>
    <Head title="Usuarios internos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-200">
                    Usuarios internos
                </h2>
                <PrimaryButton class="normal-case tracking-normal" @click="openCreate">
                    + Crear usuario
                </PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Cuentas que pueden iniciar sesión en este panel IAM (no son cuentas de los sistemas remotos).
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

                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-900/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Correo</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Creado</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="admin in admins" :key="admin.id">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-slate-800 dark:text-slate-100">
                                    {{ admin.name }}
                                    <span
                                        v-if="admin.id === page.props.auth.user.id"
                                        class="ml-2 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200"
                                    >
                                        Tú
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                    {{ admin.email }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                    {{ formatDate(admin.created_at) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            @click="openEdit(admin)"
                                            class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            @click="confirmDelete(admin)"
                                            :disabled="admin.id === page.props.auth.user.id"
                                            class="rounded-md border border-red-200 px-3 py-1 text-xs font-medium text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-30 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950"
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!admins.length">
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-400">
                                    No hay usuarios administradores todavía.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal crear / editar -->
        <Modal :show="showForm" @close="closeForm" max-width="md">
            <form @submit.prevent="submit" class="p-6">
                <h3 class="mb-4 text-lg font-medium text-slate-900 dark:text-slate-100">
                    {{ editingAdmin ? 'Editar usuario' : 'Crear usuario' }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="name" value="Nombre" />
                        <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Correo electrónico" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="password" :value="editingAdmin ? 'Nueva contraseña (opcional)' : 'Contraseña'" />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full"
                            :required="!editingAdmin"
                            autocomplete="new-password"
                            :placeholder="editingAdmin ? 'Dejar en blanco para no cambiarla' : ''"
                        />
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="password_confirmation" value="Confirmar contraseña" />
                        <TextInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            :required="!editingAdmin || !!form.password"
                            autocomplete="new-password"
                        />
                        <InputError :message="form.errors.password_confirmation" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeForm">Cancelar</SecondaryButton>
                    <PrimaryButton class="normal-case tracking-normal" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ editingAdmin ? 'Guardar cambios' : 'Crear usuario' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal confirmar eliminación -->
        <Modal :show="showDelete" @close="closeDelete" max-width="sm">
            <div class="p-6">
                <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">
                    ¿Eliminar a {{ deletingAdmin?.name }}?
                </h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Esta cuenta perderá acceso al panel IAM. Esta acción no se puede deshacer.
                </p>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDelete">Cancelar</SecondaryButton>
                    <DangerButton :class="{ 'opacity-25': deleteForm.processing }" :disabled="deleteForm.processing" @click="destroyAdmin">
                        Eliminar
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
