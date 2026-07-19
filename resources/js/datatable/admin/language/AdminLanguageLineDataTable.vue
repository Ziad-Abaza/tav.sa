<script setup>
import { ref} from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

const props = defineProps({
    languageCode: { type: String, required: true },
});

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

// ─── Export CSV ───────────────────────────────────────────────────────────────

function exportCsv() {
    const params = table.value?.queryParams ?? {}

    const qs = new URLSearchParams({
        ...params,
        languageCode: props.languageCode,
    }).toString()

    const url = `/admin/api/admin-language-lines/export${qs ? '?' + qs : ''}`

    const link = document.createElement('a')
    link.href = url
    link.download = ''
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="admin-language-lines"  base-url="/admin/api"  :extra-params="{ languageCode }"
            :editable-fields="{
        value: { type: 'text' }
    }"
                storage-key="admin_language_lines_table">

                <!-- Export button -->
                <template #toolbar-trailing>
                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-gray-200 bg-white dark:bg-slate-800 dark:border-gray-600 dark:text-white px-2.5 text-sm font-medium text-gray-600 shadow-sm hover:bg-gray-50"
                        title="Export to CSV"
                        @click="exportCsv"
                    >
                        <svg
                            class="h-3.5 w-3.5 flex-shrink-0"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                            />
                        </svg>
                        Export
                    </button>
                </template>
                

                <!-- Empty state -->
                <template #empty>
                    <div class="flex flex-col items-center py-4 text-gray-400">
                        <svg class="mb-2 h-10 w-10 opacity-40" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                        </svg>
                        <p>No Language Lines found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>

</template>
