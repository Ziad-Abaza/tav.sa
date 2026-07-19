<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

const props = defineProps({
    subdomain: { type: String, required: true },
});

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

function onTableRefresh() {
    table.value?.reload();
}

onMounted(() => {
    window.addEventListener("ai-prompt-table-refresh", onTableRefresh);
});

onBeforeUnmount(() => {
    window.removeEventListener("ai-prompt-table-refresh", onTableRefresh);
});

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}


function truncate(text, length = 20) {
    if (!text) return ''
    return text.length > length
        ? text.substring(0, length) + '...'
        : text
}

</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="ai-prompt" :subdomain="subdomain" storage-key="ai-prompt_table">

                <!-- Actions column header -->
                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>

                <!-- Row actions — trigger button only; menu teleported to body -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 space-x-2">

                        <button
                            class="inline-flex items-center px-3 py-1 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-500"
                            @click="dispatchLivewire('editAiPrompt', { promptId: row.id })">
                            Edit
                        </button>

                        <button
                            class="inline-flex items-center px-3 py-1 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-500"
                            @click="dispatchLivewire('confirmDelete', { promptId: row.id })">
                            Delete
                        </button>

                    </td>
                </template>

                <template #prompt_action="{ row, value }">
                    <span v-if="row.prompt_action" class="text-sm text-gray-800 dark:text-gray-300">
                        {{ truncate(row.prompt_action, 100) }}
                    </span>
                </template>

                <!-- Empty state -->
                <template #empty>
                    <div class="flex flex-col items-center py-4 text-gray-400">
                        <svg class="mb-2 h-10 w-10 opacity-40" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                        </svg>
                        <p>No Ai Prompts found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>

</template>
