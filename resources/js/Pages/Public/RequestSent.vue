<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Solicitud enviada" />

    <div class="flex min-h-screen items-center justify-center bg-slate-50 px-6 dark:bg-slate-950">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900 dark:text-emerald-300">
                <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.4 7.4a1 1 0 01-1.4 0L3.3 9.5a1 1 0 111.4-1.4l3.6 3.6 6.7-6.7a1 1 0 011.4 0z" clip-rule="evenodd" />
                </svg>
            </span>
            <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Solicitud enviada</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Un administrador va a revisar tu solicitud y aprobar el acceso a cada sistema por separado.
                Te avisarán cuando tu cuenta esté lista.
            </p>
            <div class="mt-6 flex items-center justify-center gap-2 text-xs text-slate-400">
                <ApplicationLogo class="h-4 w-4" />
                IAM AGBC
            </div>
            <Link :href="route('access-requests.create')" class="mt-4 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500">
                Enviar otra solicitud
            </Link>
        </div>
    </div>
</template>
