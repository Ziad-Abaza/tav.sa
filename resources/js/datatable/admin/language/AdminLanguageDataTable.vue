<script setup>
import { ref, onMounted } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

onMounted(() => {
    window.addEventListener("language-table-refresh", onTableRefresh);
});

// ─── Reload on Livewire events ─────────────────────────────────────────────────

function onTableRefresh() {
    table.value?.reload();
}

// ─── Livewire event dispatching ───────────────────────────────────────────────

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}

function goToTranslate(code) {
    window.location.href = `/admin/languages/${code}/translations`;
}

function isEnglish(row) {
    return (
        row.code === 'en' ||
        (row.name && row.name.toLowerCase() === 'english')
    )
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="admin-languages" base-url="/admin/api"
                storage-key="admin_languages_table">
                <!-- Actions column header -->
                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>

                <!-- Row actions -->
                <template #row-trailing="{ row }">
                    <td class="w-[220px] px-4 py-2">
                        <div class="flex items-center justify-end gap-2">

                            <!-- Translate -->
                            <button v-if="!isEnglish(row)"
                                class="px-3 py-1 text-sm font-medium text-white bg-success-600 rounded hover:bg-success-500"
                                @click="goToTranslate(row.code)">
                                Translate
                            </button>

                            <!-- Download -->
                            <button v-if="!isEnglish(row)"
                                class="px-3 py-1 text-sm font-medium text-white bg-info-600 rounded hover:bg-info-500"
                                @click="dispatchLivewire('downloadLanguage', { languageId: row.id })">
                                Download
                            </button>

                            <!-- Edit -->
                            <button v-if="!isEnglish(row)"
                                class="px-3 py-1 text-sm font-medium text-white bg-primary-600 rounded hover:bg-primary-500"
                                @click="dispatchLivewire('editLanguage', { languageCode: row.code })">
                                Edit
                            </button>

                            <!-- Delete -->
                            <button v-if="!isEnglish(row)"
                                class="px-3 py-1 text-sm font-medium text-white bg-danger-600 rounded hover:bg-danger-500"
                                @click="dispatchLivewire('confirmDelete', { languageId: row.id })">
                                Delete
                            </button>

                        </div>
                    </td>
                </template>

                <!-- Empty state -->
                <template #empty>
                    <div class="flex flex-col items-center py-4 text-gray-400">
                        <svg class="mb-2 h-10 w-10 opacity-40" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <p>No Languages found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>
</template>
