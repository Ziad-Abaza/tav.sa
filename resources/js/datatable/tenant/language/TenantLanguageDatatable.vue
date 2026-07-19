<script setup>
import { ref, onMounted } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

const props = defineProps({
    subdomain: { type: String, required: true },
});

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);


// Register sync — NEVER inside async callback; listener must exist from mount
onMounted(() => {
    window.addEventListener("tenant-language-table-refresh", onTableRefresh);
});

// ─── Reload on Livewire actions ────────────────────────────────────────────────

function onTableRefresh() {
    table.value?.reload();
}

// ─── Livewire event dispatching ───────────────────────────────────────────────

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}

</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="tenant-languages" :subdomain="subdomain"
                storage-key="tenant_languages_table">

                <!-- Actions column header -->
                <template #header-trailing>
                    <th class="w-[220px] px-4  text-xs font-semibold uppercase text-gray-500">
                        Actions
                    </th>
                </template>

                <!-- Row Actions -->
                <template #row-trailing="{ row }">
                    <td class="w-[220px] px-4 py-2">
                        <div class="flex items-center justify-end gap-2">

                            <button
                                class="px-3 py-1 text-sm font-medium text-white bg-success-600 rounded hover:bg-success-500"
                                @click="dispatchLivewire('translateLanguage', { code: row.code })">
                                Translate
                            </button>

                            <button
                                class="px-3 py-1 text-sm font-medium text-white bg-primary-600 rounded hover:bg-primary-500"
                                @click="dispatchLivewire('editLanguage', { languageCode: row.code })">
                                Edit
                            </button>

                            <button
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
                                d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                        </svg>
                        <p>No Languages found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>

</template>
