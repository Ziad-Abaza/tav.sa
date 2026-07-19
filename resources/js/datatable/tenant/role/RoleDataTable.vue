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
    window.addEventListener("tenant-role-table-refresh", onTableRefresh);
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
            <ResourceDataTable ref="table" resource="roles" :subdomain="subdomain" storage-key="roles_table">
                <!-- Actions column header -->
                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="flex gap-2">

                            <!-- Edit Button -->
                            <button v-if="row.can_edit" @click="dispatchLivewire('editRole', { roleId: row.id })"
                                class="inline-flex rounded-md items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-primary-600 shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 justify-center">
                                Edit
                            </button>

                            <!-- Delete Button -->
                            <button v-if="row.can_delete" @click="dispatchLivewire('confirmDelete', { roleId: row.id })"
                                class="inline-flex rounded-md items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-danger-600 shadow-sm hover:bg-danger-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600 justify-center">
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
                        <p>No roles found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>

    <!-- Dropdown teleported to <body> — escapes table overflow clipping -->
    <Teleport to="body">
        <div class="fixed z-[9999] w-32 rounded-md border border-gray-200 bg-white shadow-lg">

        </div>
    </Teleport>
</template>
