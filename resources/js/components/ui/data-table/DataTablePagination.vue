<script setup>
import DataTablePerPage from './DataTablePerPage.vue'

const props = defineProps({
    collection: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    perPage: { type: Number, required: true },
    perPageOptions: { type: Array, required: true },
})

const emit = defineEmits(['goToPage', 'goToPrevious', 'goToNext', 'changePerPage'])
</script>

<template>
    <div class="flex items-center justify-between">
        <!-- Mobile: prev/next only -->
        <div class="flex flex-1 justify-between sm:hidden">
            <button
                class="rounded-md border border-gray-300 bg-white  px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!collection.hasPreviousPage || loading"
                @click="emit('goToPrevious')"
            >
                Previous
            </button>
            <button
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!collection.hasNextPage || loading"
                @click="emit('goToNext')"
            >
                Next
            </button>
        </div>

        <!-- Desktop: showing info + per-page selector + numbered links -->
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600 dark:text-white">
                Showing
                <span class="font-medium">{{ collection.from }}</span>
                to
                <span class="font-medium">{{ collection.to }}</span>
                of
                <span class="font-medium">{{ collection.total }}</span>
                results
            </p>

            <!-- Per-page selector in center -->
            <div class="flex items-center">
                <DataTablePerPage
                    :per-page="perPage"
                    :options="perPageOptions"
                    :disabled="loading"
                    @change="emit('changePerPage', $event)"
                />
            </div>

            <nav class="flex items-center gap-1">
                <!-- Previous -->
                <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white dark:bg-slate-800 dark:text-white dark:border-gray-600 text-sm text-gray-600 shadow-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!collection.hasPreviousPage || loading"
                    @click="emit('goToPrevious')"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <template v-for="(link, i) in collection.pagination" :key="i">
                    <span
                        v-if="link === '...'"
                        class="px-2 text-sm text-gray-500"
                    >
                        …
                    </span>
                    <button
                        v-else
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md border text-sm shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
                        :class="
                            collection.isCurrentPage(link)
                                ? 'border-blue-500 bg-blue-500 font-medium text-white'
                                : 'border-gray-300 bg-white dark:bg-slate-800 text-gray-700 dark:text-white dark:border-gray-600 hover:bg-gray-50'
                        "
                        :disabled="loading || collection.isCurrentPage(link)"
                        @click="emit('goToPage', link)"
                    >
                        {{ link }}
                    </button>
                </template>

                <!-- Next -->
                <button
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white dark:bg-slate-800 text-sm dark:text-white dark:border-gray-600 text-gray-600 shadow-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!collection.hasNextPage || loading"
                    @click="emit('goToNext')"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </nav>
        </div>
    </div>
</template>
