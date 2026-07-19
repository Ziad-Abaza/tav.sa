<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed } from "vue"
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue"
import { BsThreeDotsVertical } from "@kalimahapps/vue-icons";
import { useTableFilters } from "@/composables/useTableFilters";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";

const table = ref(null)

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}

// ─── Filters ─────────────────────────────────────────────────────────────────

const FILTER_KEYS = ["status","priority",
    "created_from",
    "created_until",];
const { activeFilters, activeCount, setFilter, clearFilter, clearAll } =
    useTableFilters(FILTER_KEYS, "maininvoices_table_filters");

const filterDefs = computed(() => [
    {
        key: "status",
        label: "Status",
        options: [
            { label: "Open", value: "open" },
            { label: "Answered", value: "answered" },
            { label: "Closed", value: "closed" },
            { label: "On Hold", value: "on_hold" },
        ]
    },
    {
        key: "priority",
        label: "Priority",
        options: [
            { label: "Low", value: "low" },
            { label: "Medium", value: "medium" },
            { label: "High", value: "high" },
        ],
    },
    {
        key: "created_from",
        label: "Created From",
        type: "date",
    },
    {
        key: "created_until",
        label: "Created Until",
        type: "date",
    },
])

// ─── Bulk selection ──────────────────────────────────────────────────────────

const selectedIds = ref([]);
const isAllSelected = ref(false);

// ─── Bulk selection helpers ───────────────────────────────────────────────────

function toggleSelectAll(items) {
    if (isAllSelected.value) {
        selectedIds.value = [];
        isAllSelected.value = false;
    } else {
        selectedIds.value = items.map((r) => r.id);
        isAllSelected.value = true;
    }
}

function toggleSelectRow(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
}

function isSelected(id) {
    return selectedIds.value.includes(id);
}

function clearSelection() {
    selectedIds.value = [];
    isAllSelected.value = false;
}

function bulkDelete() {
    if (!selectedIds.value.length) return;

    dispatchLivewire("confirmDelete", {
        ticketId: selectedIds.value,
    });

    clearSelection();
}

// ─── Dropdown — teleported to <body> to escape table overflow clipping ─────────

const activeDropdown = ref(null);
const dropdownPos = ref({ top: 0, left: 0 });

function openDropdown(event, rowId) {
    if (activeDropdown.value === rowId) {
        activeDropdown.value = null;
        return;
    }
    const rect = event.currentTarget.getBoundingClientRect();
    const dropdownHeight = 180; // approximate dropdown height
    const spaceBelow = window.innerHeight - rect.bottom;

    let top;

    if (spaceBelow < dropdownHeight) {
        // open upward
        top = rect.top - dropdownHeight - 4;
    } else {
        // open downward
        top = rect.bottom + 4;
    }
    dropdownPos.value = {
        top: top,
        left: rect.right,
    };
    activeDropdown.value = rowId;
}

onMounted(() => {
    document.addEventListener("mousedown", onDocumentMousedown);
     window.addEventListener("admin-tickets-table-refresh", onTableRefresh);
});

onBeforeUnmount(() => {
    document.removeEventListener("mousedown", onDocumentMousedown);
    window.removeEventListener("admin-tickets-table-refresh", onTableRefresh);
});

// ─── Reload on Livewire events ─────────────────────────────────────────────────

function onTableRefresh() {
    table.value?.reload();
}

function onDocumentMousedown(e) {
    if (activeDropdown.value === null) return;

    if (!e.target.closest("[data-tickets-dropdown]")) {
        closeDropdown();
    }
}

function closeDropdown() {
    activeDropdown.value = null;
}

function quickStatusChange(id) {
    dispatchLivewire("quickStatusChange", { ticketId: id });
}

function confirmDelete(id) {
    dispatchLivewire("confirmDelete", { ticketId: id });
}

function getRow(id) {
    return table.value?.collection?.items?.find(r => r.id === id);
}

// ─── Export ───────────────────────────────────────────────────────────────────

function exportCsv() {
    const params = table.value?.queryParams ?? {};
    const qs = new URLSearchParams(
        Object.fromEntries(Object.entries(params).filter(([, v]) => v != null)),
    ).toString();
    window.open(`/admin/api/tickets/export${qs ? "?" + qs : ""}`);
}

</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="tickets" base-url="/admin/api" storage-key="tickets_table"
                :extra-params="activeFilters">

                <template #ticket_id="{ row, value }">
                    <a :href="`/admin/tickets/${row.id}`"
                        class="dark:text-gray-200 text-primary-600 hover:underline dark:hover:text-primary-400">
                        {{ value }}
                    </a>
                </template>

                <template #status="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': value === 'answered',
                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': value === 'open',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': value === 'closed',
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': value === 'on_hold',
                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200': !['answered', 'open', 'closed', 'on_hold'].includes(value)
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1).replace('_', ' ') }}
                    </span>
                </template>

                <template #priority="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': value === 'low',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': value === 'high',
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': value === 'medium',
                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200': !['low', 'medium', 'high'].includes(value)
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1) }}
                    </span>
                </template>

                <!-- Export button -->
                <template #toolbar-trailing>
                    <button type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-gray-200 bg-white dark:bg-slate-800 dark:border-gray-600 dark:text-white px-2.5 text-sm font-medium text-gray-600 shadow-sm hover:bg-gray-50"
                        title="Export to CSV" @click="exportCsv">
                        <svg class="h-3.5 w-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export
                    </button>
                </template>

                <!-- Toolbar leading: bulk bar (when selected) OR filters -->
                <template #toolbar-leading>

                    <DataTableFilters :filters="filterDefs" :active-filters="activeFilters" :active-count="activeCount"
                        @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />


                    <transition enter-active-class="transition duration-150 ease-out"
                        enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-1">
                        <div v-if="selectedIds.length" class="flex items-center gap-2">
                            <span class="text-sm font-medium text-blue-700">
                                {{ selectedIds.length }} selected
                            </span>
                            <div class="h-4 w-px bg-gray-300" />
                            <button
                                class="inline-flex h-8 items-center rounded-md bg-danger-600 px-3 text-sm font-medium text-white shadow-sm hover:bg-danger-700"
                                @click="bulkDelete">
                                Delete
                            </button>
                            <button
                                class="inline-flex h-8 items-center rounded-md px-2 text-sm text-gray-400 hover:text-gray-600"
                                title="Clear selection" @click="clearSelection">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </transition>
                </template>

                <!-- Checkbox column header -->
                <template #header-leading>
                    <th class="h-10 w-10 px-4 text-left align-middle">
                        <input type="checkbox" :checked="isAllSelected"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 dark:border-gray-600 dark:bg-slate-800" @change="
                                () =>
                                    toggleSelectAll(
                                        table?.collection?.items ?? [],
                                    )
                            " />
                    </th>
                </template>

                <!-- Checkbox cell per row -->
                <template #row-leading="{ row }">
                    <td class="w-10 px-4 py-2 align-middle">
                        <input type="checkbox" :checked="isSelected(row.id)"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 dark:border-gray-600"
                            @change="() => toggleSelectRow(row.id)" />
                    </td>
                </template>

                <!-- Row actions — trigger button only; menu teleported to body -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle flex">
                        <div class="flex items-center justify-end">
                            <button data-tickets-dropdown
                                class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-white dark:hover:bg-slate-600"
                                @mousedown.stop="openDropdown($event, row.id)">
                                <BsThreeDotsVertical class="h-4 w-4" />
                            </button>
                        </div>
                    </td>
                </template>

                <!-- Actions column header -->
                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>

            </ResourceDataTable>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="activeDropdown !== null" data-tickets-dropdown
            class="fixed z-[9999] w-32 rounded-md border border-gray-200 bg-white shadow-lg dark:bg-slate-700 dark:border-gray-600" :style="{
                top: dropdownPos.top + 'px',
                left: dropdownPos.left + 'px',
                transform: 'translateX(-100%)'
            }">

            <!-- view -->
            <a :href="`/admin/tickets/${activeDropdown}`"
                class="block w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600 "
                @click="closeDropdown">
                View
            </a>

            <!-- Edit -->
            <button v-if="getRow(activeDropdown)?.status !== 'closed'"
                class="w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600 "
                @click="quickStatusChange(activeDropdown); closeDropdown()">
                Quick Status Change
            </button>

            <!-- Delete -->
            <button class="w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-slate-600"
                 @click="confirmDelete(activeDropdown); closeDropdown()">
                Delete
            </button>
        </div>
    </Teleport>
</template>