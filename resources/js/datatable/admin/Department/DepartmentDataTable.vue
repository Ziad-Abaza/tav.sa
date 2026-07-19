<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";
import { useTableFilters } from "@/composables/useTableFilters";

const table = ref(null);

const selectedIds = ref([]);

const togglingStatus = reactive(new Set());

function onTableRefresh() {
    table.value?.reload();
}

const filterOptions = ref({ status: [] });
const FILTER_KEYS = ["status"];

const { activeFilters, activeCount, setFilter, clearFilter, clearAll } =
    useTableFilters(FILTER_KEYS, "department_table_filters");

onMounted(() => {
    window.addEventListener("department-table-refresh", onTableRefresh);
    document.addEventListener("mousedown", onDocumentMousedown);

    axios
        .get(`/admin/api/department-filters`)
        .then(({ data }) => {
            filterOptions.value = data;
        })
        .catch((e) => {
            console.error("[DepartmentDataTable] failed to load filter options:", e);
        });
});

onBeforeUnmount(() => {
    window.removeEventListener("department-table-refresh", onTableRefresh);
    document.removeEventListener("mousedown", onDocumentMousedown);
});

// ─── Filters ─────────────────────────────────────────────────────────────────

const filterDefs = computed(() => [
    {
        key: "status",
        label: "Status",
        options: filterOptions.value.status,
    },
]);

const activeDropdown = ref(null);
const dropdownPos = ref({ top: 0, left: 0 });

function openDropdown(event, rowId) {
    if (activeDropdown.value === rowId) {
        activeDropdown.value = null;
        return;
    }
    const rect = event.currentTarget.getBoundingClientRect();
    dropdownPos.value = {
        top: rect.bottom + window.scrollY + 4,
        left: rect.right + window.scrollX,
    };
    activeDropdown.value = rowId;
}

function closeDropdown() {
    activeDropdown.value = null;
}


function onDocumentMousedown(e) {
    if (activeDropdown.value === null) return;
    if (!e.target.closest("[data-department-dropdown]")) {
        closeDropdown();
    }
}

/* Edit */
function editDepartment(row) {
    if (!window.Livewire) return;

    window.Livewire.dispatch("editDepartment", {
        id: row.id,
    });
}

/* Delete */
function deleteDepartment(row) {
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
        const response = await axios.patch(`/admin/api/department/${row.id}/toggle-status`);
        table.value?.reload();
        const message = response.data?.message || "Updated successfully";
        showNotification(message, "success");
    } catch (e) {
        console.error("[DepartmentDataTable] toggleStatus error:", e);
        const errorMessage = e.response?.data?.message || "Something went wrong";
        showNotification(errorMessage, "danger");
    } finally {
        togglingStatus.delete(row.id);
    }
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="department" :extra-params="activeFilters"
                storage-key="department_table" base-url="/admin/api">

                <template #toolbar-leading>
                    <DataTableFilters :filters="filterDefs" :active-filters="activeFilters" :active-count="activeCount"
                        @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />
                </template>

                <template #description="{ value }">
                    <div class="group relative max-w-[320px]">
                        <!-- Truncated text -->
                        <p class="truncate text-sm text-gray-700 dark:text-white">
                            {{ value }}
                        </p>

                        <!-- Tooltip on hover (above) -->
                        <div v-if="value"
                            class="pointer-events-none absolute bottom-full left-0 z-50 dark:text-white mb-1 hidden w-max max-w-sm rounded-md bg-gray-900 px-2 py-1 text-xs text-white shadow-lg group-hover:block">
                            {{ value }}
                        </div>
                    </div>
                </template>

                <template #assignees="{ row }">
                    <div class="flex flex-col">
                        <template v-if="row.assignees?.list?.length">
                            <span v-for="u in row.assignees.list" :key="u.id" class="text-xs">
                                {{ u.name }}
                            </span>

                            <span v-if="row.assignees.more > 0" class="text-xs text-gray-500 dark:text-white">
                                +{{ row.assignees.more }} more
                            </span>
                        </template>

                        <span v-else class="text-gray-400 text-xs dark:text-white">--</span>
                    </div>
                </template>

                <template #status="{ row, value }">
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
                            class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-primary-600 shadow-sm hover:bg-primary-500 rounded-md"
                            v-if="row.can_edit" @click="editDepartment(row)">
                            Edit
                        </button>

                        <button
                            class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-danger-600 shadow-sm hover:bg-danger-500 rounded-md"
                            v-if="row.can_delete" @click="deleteDepartment(row)">
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
                        <p>No department found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>
</template>
