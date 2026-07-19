<script setup>
import { ref, onMounted } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

const table = ref(null);

onMounted(() => {
    window.addEventListener("role-table-refresh", onTableRefresh);
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
    <div class="rounded-xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="admin-roles" storage-key="admin_roles_table" base-url="/admin/api">

                <!-- Actions column header -->
                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>

                <!-- Actions -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 space-x-2">
                        <button
                            class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-primary-600 shadow-sm hover:bg-primary-500 rounded-md"
                            @click="dispatchLivewire('editRole', { roleId: row.id })">
                            Edit
                        </button>

                        <button
                            class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-danger-600 shadow-sm hover:bg-danger-500 rounded-md"
                            @click="dispatchLivewire('confirmDelete', { roleId: row.id })">
                            Delete
                        </button>
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
                        <p>No Roles found.</p>
                    </div>
                </template>

            </ResourceDataTable>
        </div>
    </div>
</template>