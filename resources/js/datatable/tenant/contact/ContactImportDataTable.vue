<script setup>
import { ref } from "vue"
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue"
import { AkCheck, AkCross, AkTriangleAlert } from "@kalimahapps/vue-icons";

const props = defineProps({
    subdomain: { type: String, required: true },
})

const table = ref(null)

// ─── Livewire event dispatching ───────────────────────────────────────────────

function capitalize(value) {
    if (!value) return '';
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}
function showImportDetails(id) {
    dispatchLivewire("showImportDetails", { importId: id });
}

function confirmDeleteImport(id) {
    dispatchLivewire("confirmDeleteImport", { importId: id });
}

</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="contact-imports" :subdomain="subdomain"
                storage-key="contact-imports_table">

                <template #status="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': value === 'processing',
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': value === 'completed',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': value === 'failed',
                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200': !['processing', 'completed', 'failed'].includes(value)
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1) }}
                    </span>
                </template>

                <template #file="{ row, value }">
                    <button @click="dispatchLivewire('downloadFile', { importId: row.id })"
                        class="text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 hover:underline"
                        title="Download File">
                        <div class="inline-flex items-center space-x-1">
                            <span>{{ value.split('/').pop() }}</span>
                        </div>
                    </button>
                </template>

                <template #progress="{ row }">
                    <div v-if="row.total_records > 0" class="flex items-center space-x-2 w-full">
                        <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                            <div class="h-2" :class="{
                                'bg-green-600': row.status === 'completed',
                                'bg-red-600': row.status === 'failed',
                                'bg-blue-600': row.status === 'processing'
                            }" :style="{ width: ((row.processed_records / row.total_records) * 100) + '%' }" />
                        </div>
                        <span class="text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                            {{ row.processed_records }}/{{ row.total_records }}
                        </span>
                    </div>

                    <span v-else class="text-xs text-gray-500">-</span>
                </template>

                <template #total_records="{ row }">
                    <div class="flex flex-wrap gap-1 text-xs">

                        <span
                            class="inline-flex items-center px-1.5 py-0.5 rounded text-green-700 bg-green-100 dark:bg-green-900/20 dark:text-green-400">
                            <AkCheck class="h-3.5 w-3.5" /> {{ row.valid_records }}
                        </span>

                        <span v-if="row.invalid_records > 0"
                            class="inline-flex items-center px-1.5 py-0.5 rounded text-red-700 bg-red-100 dark:bg-red-900/20 dark:text-red-400">
                            <AkCross class="h-3.5 w-3.5" /> {{ row.invalid_records }}
                        </span>

                        <span v-if="row.skipped_records > 0"
                            class="inline-flex items-center px-1.5 py-0.5 rounded text-yellow-700 bg-yellow-100 dark:bg-yellow-900/20 dark:text-yellow-400">
                            <AkTriangleAlert class="h-3.5 w-3.5" /> {{ row.skipped_records }}
                        </span>

                    </div>
                </template>

                <!-- Actions Column Header -->
                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>

                <!-- Row Actions -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle flex flex-row">
                        <div class="flex items-center justify-end gap-4">

                            <!-- View -->
                            <button @click="showImportDetails(row.id)"
                                class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-primary-600 shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 justify-center rounded-md">
                                View
                            </button>

                            <!-- Download (Only if Paid) -->
                            <button @click="confirmDeleteImport(row.id)"
                                class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-danger-600 shadow-sm hover:bg-danger-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600 justify-center rounded-md">
                                Delete
                            </button>

                        </div>
                    </td>
                </template>

            </ResourceDataTable>
        </div>
    </div>
</template>