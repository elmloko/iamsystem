<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    requests: Array,
});

const page = usePage();
const processingId = ref(null);

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
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Personas que pidieron acceso desde el formulario público. Aprueba o rechaza sistema por sistema —
                    la cuenta recién se crea en el sistema real al aprobar.
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

                <div v-if="!requests.length" class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
                    No hay solicitudes de acceso todavía.
                </div>

                <div
                    v-for="req in requests"
                    :key="req.id"
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                        <div>
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ req.name }}</p>
                            <p class="text-xs text-slate-400">{{ req.email }}</p>
                        </div>
                        <span class="text-xs text-slate-400">Solicitado: {{ formatDate(req.created_at) }}</span>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div v-for="item in req.items" :key="item.id" class="p-4">
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

                                <div v-if="item.status === 'pending'" class="flex gap-2">
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

                            <dl v-if="extraFieldEntries(item).length || item.alias" class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-3">
                                <template v-if="item.alias">
                                    <dt class="text-slate-400">Alias</dt>
                                    <dd class="text-slate-600 dark:text-slate-300">{{ item.alias }}</dd>
                                </template>
                                <template v-for="[key, value] in extraFieldEntries(item)" :key="key">
                                    <dt class="text-slate-400">{{ key }}</dt>
                                    <dd class="text-slate-600 dark:text-slate-300">{{ Array.isArray(value) ? value.join(', ') : value }}</dd>
                                </template>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
