<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';

const features = [
    'Un solo lugar para gestionar cuentas de todos los sistemas AGBC',
    'Consulta de roles en vivo directo desde cada sistema conectado',
    'Alta de usuarios y roles sin tocar cada base de datos a mano',
];
</script>

<template>
    <div class="flex min-h-screen bg-slate-50 dark:bg-slate-950">
        <!-- Panel de marca (oculto en pantallas pequeñas) -->
        <div
            class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-700 via-indigo-800 to-slate-900 p-12 text-white lg:flex"
        >
            <div
                class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/10 blur-3xl"
            />
            <div
                class="pointer-events-none absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-indigo-400/20 blur-3xl"
            />

            <Link href="/" class="relative flex items-center gap-3">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20"
                >
                    <ApplicationLogo class="h-6 w-6 text-white" />
                </span>
                <span class="text-lg font-semibold tracking-tight">IAM AGBC</span>
            </Link>

            <div class="relative">
                <h1 class="text-3xl font-semibold leading-tight tracking-tight">
                    Identidad y accesos,<br />centralizados.
                </h1>
                <p class="mt-4 max-w-sm text-sm text-indigo-100/80">
                    Administra cuentas y roles de todos los sistemas de AGBC desde un
                    único panel.
                </p>

                <ul class="mt-8 space-y-3">
                    <li
                        v-for="feature in features"
                        :key="feature"
                        class="flex items-start gap-2.5 text-sm text-indigo-100/90"
                    >
                        <svg
                            class="mt-0.5 h-4 w-4 shrink-0 text-indigo-300"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M16.7 5.3a1 1 0 010 1.4l-7.4 7.4a1 1 0 01-1.4 0L3.3 9.5a1 1 0 111.4-1.4l3.6 3.6 6.7-6.7a1 1 0 011.4 0z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        {{ feature }}
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-indigo-200/60">
                Corporación de Correos de Bolivia
            </p>
        </div>

        <!-- Panel del formulario -->
        <div class="flex w-full flex-col justify-center px-6 py-12 sm:px-12 lg:w-1/2 lg:px-16">
            <div class="mx-auto w-full max-w-sm">
                <Link href="/" class="mb-8 flex items-center gap-3 lg:hidden">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white"
                    >
                        <ApplicationLogo class="h-6 w-6" />
                    </span>
                    <span class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                        IAM AGBC
                    </span>
                </Link>

                <div
                    class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
