<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const query = ref('');
const loading = ref(false);
const groups = ref([]);
const searched = ref(false);

let debounceTimer = null;

watch(query, (value) => {
    clearTimeout(debounceTimer);

    if (value.trim().length < 2) {
        groups.value = [];
        searched.value = false;
        return;
    }

    debounceTimer = setTimeout(() => runSearch(value.trim()), 350);
});

async function runSearch(value) {
    loading.value = true;
    try {
        const res = await fetch(route('search.query', { q: value }));
        const data = await res.json();
        groups.value = data.groups ?? [];
        searched.value = true;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Head title="Buscar en sistemas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Buscar usuario en todos los sistemas
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
                <div class="rounded-md border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <input
                        v-model="query"
                        type="text"
                        placeholder="Escribe un nombre (mínimo 2 letras)..."
                        class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        autofocus
                    />
                    <p class="mt-2 text-xs text-gray-400">
                        Busca en vivo, directo en la base de datos de cada sistema activo — incluye usuarios que no fueron creados desde este IAM.
                    </p>
                </div>

                <div v-if="loading" class="text-sm text-gray-400">Buscando...</div>

                <div v-else-if="searched && !groups.length" class="rounded-md border border-gray-200 bg-white p-6 text-center text-sm text-gray-400 dark:border-gray-700 dark:bg-gray-800">
                    Nadie coincide con "{{ query }}" en ningún sistema.
                </div>

                <div v-for="group in groups" :key="group.system.key" class="rounded-md border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ group.system.name }}</h3>
                        <span v-if="group.error" class="text-xs text-red-500">{{ group.error }}</span>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="user in group.results" :key="user.id" class="flex items-center justify-between py-2 text-sm">
                            <span class="text-gray-700 dark:text-gray-200">{{ user.name }}</span>
                            <span class="text-gray-400">{{ user.email }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
