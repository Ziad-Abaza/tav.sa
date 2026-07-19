<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

// Toggle loading state per contact (prevent double-clicks)
const togglingStatus = reactive(new Set());

function onTableRefresh() {
    table.value?.reload();
}

onMounted(() => {
    window.addEventListener("page-table-refresh", onTableRefresh);
});

onBeforeUnmount(() => {
    window.removeEventListener("page-table-refresh", onTableRefresh);
});

/* Edit */
function editPage(row) {
    if (!window.Livewire) return;

    window.Livewire.dispatch("editPage", {
        pageId: row.id,
    });
}

/* Delete */
function deletePage(row) {
    if (!window.Livewire) return;

    window.Livewire.dispatch("confirmDelete", {
        pageId: row.id,
    });
}

/* Toggle */
async function toggleStatus(row) {
    if (togglingStatus.has(row.id)) return;

    togglingStatus.add(row.id);

    try {
        const response = await axios.patch(`/admin/api/page/${row.id}/toggle-status`);
        table.value?.reload();
        const message = response.data?.message || "Updated successfully";
        showNotification(message, "success");
    } catch (e) {
        console.error("[PageDataTable] toggleStatus error:", e);
        const errorMessage = e.response?.data?.message || "Something went wrong";
        showNotification(errorMessage, "danger");
    } finally {
        togglingStatus.delete(row.id);
    }
}
</script>

<template>
    <div class="rounded-xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="page" storage-key="page_table" base-url="/admin/api">

                <!-- Toggle Override -->
                <template #status="{ row, value }">
                    <button
                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition duration-200 ease-in-out"
                        :class="value ? 'bg-primary-500' : 'bg-gray-200 dark:bg-slate-700'" :disabled="togglingStatus.has(row.id)"
                        @click="toggleStatus(row)">
                        <span
                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200 ease-in-out"
                            :class="value ? 'translate-x-4' : 'translate-x-0'" />
                    </button>
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

                        <!-- Edit -->
                        <button
                            class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-primary-600 shadow-sm hover:bg-primary-500 rounded-md"
                            @click="editPage(row)">
                            Edit
                        </button>

                        <!-- Delete -->
                        <button
                            class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-danger-600 shadow-sm hover:bg-danger-500 rounded-md"
                            @click="deletePage(row)">
                            Delete
                        </button>

                    </td>
                </template>

            </ResourceDataTable>
        </div>
    </div>
</template>