<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

const table = ref(null);

const togglingStatus = reactive(new Set());

function onTableRefresh() {
    table.value?.reload();
}

onMounted(() => {
    window.addEventListener("currency-table-refresh", onTableRefresh);
    document.addEventListener("mousedown", onDocumentMousedown);
});

onBeforeUnmount(() => {
    window.removeEventListener("currency-table-refresh", onTableRefresh);
    document.removeEventListener("mousedown", onDocumentMousedown);
});

const activeDropdown = ref(null);

function onDocumentMousedown(e) {
    if (activeDropdown.value === null) return;
    if (!e.target.closest("[data-currency-dropdown]")) {
        closeDropdown();
    }
}

/* Edit */
function editCurrency(row) {
    if (!window.Livewire) return;

    window.Livewire.dispatch("editCurrency", {
        id: row.id,
    });
}

/* Delete */
function deleteCurrency(row) {
    // console.log(row.id);
    if (!window.Livewire) return;

    window.Livewire.dispatch("confirmDelete", {
        id: row.id,
    });
}

async function toggleStatus(row) {
    if (togglingStatus.has(row.id)) return;

    togglingStatus.add(row.id);

    try {
        const response = await axios.patch(
            `/admin/api/currency/${row.id}/toggle-status`,
            {},
            {
                validateStatus: () => true // <-- this prevents axios error
            }
        );

        if (response.status === 422) {
            showNotification(response.data?.message || "Cannot update", "danger");
            table.value?.reload();
            return;
        }

        row.is_default = response.data.value;
        showNotification(response.data?.message || "Updated successfully", "success");

    } finally {
        togglingStatus.delete(row.id);
    }
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="currency" storage-key="currency_table" base-url="/admin/api" >

                <template #is_default="{ row, value }">
                    <button
                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="value ? 'bg-primary-500' : 'bg-gray-200 dark:bg-slate-600'" :disabled="togglingStatus.has(row.id)"
                        role="switch" :aria-checked="String(value)" @click="toggleStatus(row)">
                        <span
                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
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

                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 text-left space-x-2">

                        <button
                            class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-primary-600 shadow-sm hover:bg-primary-500 rounded-md" v-if="row.can_edit"
                            @click="editCurrency(row)">
                            Edit
                        </button>

                        <button
                            class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-danger-600 shadow-sm hover:bg-danger-500 rounded-md"
                            v-if="row.can_delete" @click="deleteCurrency(row)">
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
                                d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                        </svg>
                        <p>No Currency found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>
</template>
