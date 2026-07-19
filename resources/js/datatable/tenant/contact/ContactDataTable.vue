<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";
import { BsThreeDotsVertical, BsChatDots } from "@kalimahapps/vue-icons";
import { useTableFilters } from "@/composables/useTableFilters";

const props = defineProps({
    subdomain: { type: String, required: true },
});

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

function onTableRefresh() {
    table.value?.reload();
}

// ─── permission on bulk delete ───────────────────────────────────────────────

const canDelete = computed(() => {
    const items = table?.value?.collection?.items ?? []
    return items.length ? items[0].can_delete : false
})

// ─── Bulk selection ──────────────────────────────────────────────────────────

const selectedIds = ref([]);
const isAllSelected = ref(false);
const allGroups = ref([])

// Toggle loading state per contact (prevent double-clicks)
// Must be reactive() not ref() — Vue tracks Set mutations only via reactive Proxy
const togglingEnabled = reactive(new Set());
const togglingOptedOut = reactive(new Set());

// ─── Filter options ──────────────────────────────────────────────────────────

const filterOptions = ref({ types: [], statuses: [], sources: [], users: [] });

onMounted(() => {
    // Register sync — NEVER inside async callback; listener must exist from mount
    document.addEventListener("mousedown", onDocumentMousedown);
    window.addEventListener("contact-table-refresh", onTableRefresh);

    // Fetch filter options async (separate concern)    
    axios
        .get(`/${props.subdomain}/api/contact-filters`)
        .then(({ data }) => {
            filterOptions.value = data;
            allGroups.value = data.groups || [];
        })
        .catch((e) => {
            console.error("[ContactDataTable] failed to load filter options:", e);
        });

});

onBeforeUnmount(() => {
    document.removeEventListener("mousedown", onDocumentMousedown);
    window.removeEventListener("contact-table-refresh", onTableRefresh);
});

// ─── Filters ─────────────────────────────────────────────────────────────────

const FILTER_KEYS = ["type", "status_id", "source_id", "assigned_id"];
const { activeFilters, activeCount, setFilter, clearFilter, clearAll } =
    useTableFilters(FILTER_KEYS, "contacts_table_filters");

const filterDefs = computed(() => [
    { key: "type", label: "Type", options: filterOptions.value.types },
    {
        key: "status_id",
        label: "Status",
        options: filterOptions.value.statuses,
    },
    { key: "source_id", label: "Source", options: filterOptions.value.sources },
    {
        key: "assigned_id",
        label: "Assigned To",
        options: filterOptions.value.users,
    },
]);

// ─── Inline-edit field config ────────────────────────────────────────────────
// Defines which columns are inline-editable and how.
// Keys must match column keys returned by the backend Table class.


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

// ─── Dropdown — teleported to <body> to escape table overflow clipping ─────────
const selectedRow = ref(null);
const activeDropdown = ref(null);
const dropdownPos = ref({ top: 0, left: 0 });

function openDropdown(event, row) {
    if (activeDropdown.value === row.id) {
        activeDropdown.value = null;
        selectedRow.value = null;
        return;
    }

    const rect = event.currentTarget.getBoundingClientRect();
    const dropdownHeight = 180;
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

    activeDropdown.value = row.id;
    selectedRow.value = row;
}


function closeDropdown() {
    activeDropdown.value = null;
}

function onDocumentMousedown(e) {
    if (activeDropdown.value === null) return;
    if (!e.target.closest("[data-contact-dropdown]")) {
        closeDropdown();
    }
}

// ─── Livewire event dispatching ───────────────────────────────────────────────

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}

function viewContact(id) {
    dispatchLivewire("viewContact", { contactId: id });
}

function confirmDelete(id) {
    dispatchLivewire("confirmDelete", { contactId: id });
}

function initiateChat(id) {
    dispatchLivewire("initiateChat", { id: id });
}

function bulkDelete() {
    if (!selectedIds.value.length) return;
    dispatchLivewire("bulkActionsOnContacts", {
        ids: selectedIds.value,
        action: "delete",
    });
    clearSelection();
}

function openBulkActions() {
    if (!selectedIds.value.length) return;
    dispatchLivewire("bulkActionsOnContacts", { ids: selectedIds.value });
}

function bulkInitiateChat() {
    if (!selectedIds.value.length) return;
    dispatchLivewire("bulkInitiateChatSending", { ids: selectedIds.value });
    clearSelection();
}

// ─── Inline toggles (PATCH calls) ────────────────────────────────────────────
async function toggleEnabled(row) {
    if (togglingEnabled.has(row.id)) return;

    togglingEnabled.add(row.id);

    try {
        const response = await axios.patch(
            `/${props.subdomain}/api/contacts/${row.id}/toggle-enabled`,
        );

        table.value?.reload();

        const message = response.data?.message || "Updated successfully";

        showNotification(message, "success");
    } catch (e) {
        console.error("[ContactDataTable] toggleEnabled error:", e);

        const errorMessage =
            e.response?.data?.message || "Something went wrong";

        showNotification(errorMessage, "danger");
    } finally {
        togglingEnabled.delete(row.id);
    }
}
async function toggleOptedOut(row) {
    if (togglingOptedOut.has(row.id)) return;
    togglingOptedOut.add(row.id);
    try {
        const response = await axios.patch(
            `/${props.subdomain}/api/contacts/${row.id}/toggle-opted-out`,
        );
        table.value?.reload();
        const message = response.data?.message || "Updated successfully";
        showNotification(message, "success");
    } catch (e) {
        console.error("[ContactDataTable] toggleOptedOut error:", e);
        const errorMessage =
            e.response?.data?.message || "Something went wrong";
        showNotification(errorMessage, "danger");
    } finally {
        togglingOptedOut.delete(row.id);
    }
}

// ─── Export ───────────────────────────────────────────────────────────────────

function exportCsv() {
    const params = table.value?.queryParams ?? {};
    const qs = new URLSearchParams(
        Object.fromEntries(Object.entries(params).filter(([, v]) => v != null)),
    ).toString();
    window.open(`/${props.subdomain}/api/contacts/export${qs ? "?" + qs : ""}`);
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="contacts" :subdomain="subdomain" :extra-params="activeFilters"
                storage-key="contacts_table">

                <template #default-cell="{ value }">
                    <span v-if="value === null || value === ''" class="text-gray-400">-</span>
                    <span v-else>{{ value }}</span>
                </template>


                <!-- Toolbar leading: bulk bar (when selected) OR filters -->
                <template #toolbar-leading>
                    <transition enter-active-class="transition duration-150 ease-out"
                        enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-1">
                        <div v-if="selectedIds.length" class="flex items-center gap-2">
                            <span class="text-sm font-medium text-blue-700">
                                {{ selectedIds.length }} selected
                            </span>
                            <div class="h-4 w-px bg-gray-300"/>
                            <button
                                class="inline-flex h-8 items-center rounded-md bg-white px-3 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-slate-600 dark:text-white border dark:border-gray-600"
                                @click="openBulkActions">
                                Bulk Actions
                            </button>
                            <button
                                class="inline-flex h-8 items-center gap-1.5 rounded-md bg-success-600 px-3 text-sm font-medium text-white shadow-sm hover:bg-success-700"
                                @click="bulkInitiateChat">
                                <BsChatDots class="h-3.5 w-3.5" />
                                Initiate chat
                            </button>
                            <button v-if="canDelete"
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

                    <DataTableFilters v-if="!selectedIds.length" :filters="filterDefs" :active-filters="activeFilters"
                        :active-count="activeCount" @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />
                </template>

                <template #groups="{ row }">
                    <div class="flex flex-col gap-1">

                        <!-- No groups -->
                        <span v-if="!row.groups || !row.groups.length" class="text-orange-400 text-sm">
                            N/A
                        </span>

                        <!-- Groups exist -->
                        <template v-else>

                            <!-- First row: 2 badges side-by-side -->
                            <div class="flex gap-1">
                                <span v-for="group in row.groups.slice(0, 2)" :key="group.id"
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-info-100 text-info-800 dark:bg-slate-700 dark:text-info-500 border dark:border-gray-500">
                                    {{ group.name }}
                                </span>
                            </div>

                            <!-- Second row: +X more -->
                            <div v-if="row.groups.length > 2">
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 w-fit dark:bg-slate-700 dark:text-gray-400 border dark:border-gray-500">
                                    +{{ row.groups.length - 2 }} more
                                </span>
                            </div>

                        </template>
                    </div>
                </template>

                <!-- Export button -->
                <template #toolbar-trailing>
                    <button type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2.5 text-sm font-medium text-gray-600 shadow-sm hover:bg-gray-50 dark:bg-slate-800 dark:text-white dark:border-gray-600"
                        title="Export to CSV" @click="exportCsv">
                        <svg class="h-3.5 w-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export
                    </button>
                </template>

                <!-- Checkbox column header -->
                <template #header-leading>
                    <th class="h-10 w-10 px-4 text-left align-middle">
                        <input type="checkbox" :checked="isAllSelected"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 dark:border-gray-600" @change="
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

                <!-- Phone column -->
                <template #phone="{ value }">
                    <span class="text-sm text-gray-700 dark:text-white">{{
                        value || "—"
                        }}</span>
                </template>

                <!-- Source column — expects { name } object -->
                <template #source="{ value }">
                    <span class="text-sm text-gray-700 dark:text-white">{{
                        value?.name || "—"
                        }}</span>
                </template>

                <!-- Initiate Chat column -->
                <template #initiate_chat="{ row }">
                    <button
                        class="inline-flex items-center justify-center rounded p-1.5 text-success-600 hover:bg-success-50 hover:text-success-700 dark:hover:bg-slate-700"
                        title="Initiate chat" @click="initiateChat(row.id)">
                        <BsChatDots class="h-5 w-5" />
                    </button>
                </template>

                <!-- Opted Out toggle — interactive PATCH, overrides ToggleCell -->
                <template #is_opted_out="{ row, value }">
                    <button
                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="value ? 'bg-primary-500' : 'bg-gray-200 dark:bg-slate-600'"
                        :disabled="togglingOptedOut.has(row.id)" role="switch" :aria-checked="String(value)"
                        @click="toggleOptedOut(row)">
                        <span
                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            :class="value ? 'translate-x-4' : 'translate-x-0'" />
                    </button>
                </template>

                <!-- Active (is_enabled) toggle — interactive PATCH, overrides ToggleCell -->
                <template #is_enabled="{ row, value }">
                    <button
                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="value ? 'bg-primary-500' : 'bg-gray-200 dark:bg-slate-600'"
                        :disabled="togglingEnabled.has(row.id)" role="switch" :aria-checked="String(value)"
                        @click="toggleEnabled(row)">
                        <span
                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            :class="value ? 'translate-x-4' : 'translate-x-0'" />
                    </button>
                </template>

                <!-- Row actions — trigger button only; menu teleported to body -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle">
                        <div class="flex items-center justify-end">
                            <button v-if="row.can_view || row.can_edit || row.can_delete" data-contact-dropdown
                                class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-600 dark:text-white"
                                @mousedown.stop="openDropdown($event, row)">
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

                <!-- Empty state -->
                <template #empty>
                    <div class="flex flex-col items-center py-4 text-gray-400">
                        <svg class="mb-2 h-10 w-10 opacity-40" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <p>No contacts found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>

    <!-- Dropdown teleported to <body> — escapes table overflow clipping -->
    <Teleport to="body">
        <div v-if="activeDropdown !== null && selectedRow" data-contact-dropdown
            class="fixed z-[9999] w-32 rounded-md border border-gray-200 bg-white shadow-lg dark:bg-slate-700 dark:border-gray-600" :style="{
                top: dropdownPos.top + 'px',
                left: dropdownPos.left + 'px',
                transform: 'translateX(-100%)'
            }">
            <!-- View -->
            <button v-if="selectedRow.can_view"
                class="w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600"
                @click="viewContact(activeDropdown); closeDropdown()">
                View
            </button>

            <!-- Edit -->
            <a v-if="selectedRow.can_edit" :href="`/${subdomain}/contacts/contact/${activeDropdown}`"
                class="block w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600"
                @click="closeDropdown">
                Edit
            </a>

            <!-- Delete -->
            <button v-if="selectedRow.can_delete"
                class="w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-slate-600"
                @click="confirmDelete(activeDropdown); closeDropdown()">
                Delete
            </button>
        </div>
    </Teleport>
</template>
