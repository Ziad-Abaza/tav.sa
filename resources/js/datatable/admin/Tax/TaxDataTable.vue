<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

const table = ref(null);

/* Refresh listener (same as Filament) */
function onTableRefresh() {
    table.value?.reload();
}

onMounted(() => {
    window.addEventListener("tax-table-refresh", onTableRefresh);
});

onBeforeUnmount(() => {
    window.removeEventListener("tax-table-refresh", onTableRefresh);
});

/* Actions */
function editTax(row) {
    if (!window.Livewire) return;

    window.Livewire.dispatch("editTax", {
        id: row.id,
    });
}

function deleteTax(row) {
    if (!window.Livewire) return;

    window.Livewire.dispatch("confirmDelete", {
        id: row.id,
    });
}
</script>

<template>
    <div class="rounded-xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="tax" base-url="/admin/api" storage-key="tax_table">

                <!-- Description Tooltip -->
                <template #description="{ value }">
                    <span class="cursor-pointer" :title="value">
                        {{ value?.length > 50 ? value.substring(0, 50) + '...' : value }}
                    </span>
                </template>

                <!-- Actions column header -->
                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>

                <!-- Row actions -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 space-x-2">

                        <button
                            class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-primary-600 shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 justify-center rounded-md"
                            @click="editTax(row)">
                            Edit
                        </button>

                        <button
                            class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-danger-600 shadow-sm hover:bg-danger-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600 justify-center rounded-md"
                            @click="deleteTax(row)">
                            Delete
                        </button>

                    </td>
                </template>

            </ResourceDataTable>
        </div>
    </div>
</template>