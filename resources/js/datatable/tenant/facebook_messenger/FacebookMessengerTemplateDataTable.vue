<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";
import ConfirmDeleteModal from "@/datatable/components/ConfirmDeleteModal.vue";
import { BsThreeDotsVertical, BsPlus, BsPencil, BsTrash } from "@kalimahapps/vue-icons";
import { useTableFilters } from "@/composables/useTableFilters";

const props = defineProps({
    subdomain: { type: String, required: true },
});

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

// Toggle loading state per template (prevent double-clicks)
const togglingActive = reactive(new Set());

// ─── Dropdown — teleported to <body> to escape table overflow clipping ─────────

const activeDropdown = ref(null);   // row id of open dropdown (or null)
const activeRow      = ref(null);   // full row object for the open dropdown
const dropdownPos    = ref({ top: 0, left: 0 });

function openDropdown(event, row) {
    if (activeDropdown.value === row.id) {
        activeDropdown.value = null;
        activeRow.value      = null;
        return;
    }
    const rect = event.currentTarget.getBoundingClientRect();
    dropdownPos.value = {
        top:  rect.bottom + window.scrollY + 4,
        left: rect.right  + window.scrollX,
    };
    activeDropdown.value = row.id;
    activeRow.value      = row;
}

function closeDropdown() {
    activeDropdown.value = null;
    activeRow.value      = null;
}

function onDocumentMousedown(e) {
    if (activeDropdown.value === null) return;
    if (!e.target.closest("[data-fb-messenger-dropdown]")) closeDropdown();
}

onMounted(() => {
    document.addEventListener("mousedown", onDocumentMousedown);
});

onBeforeUnmount(() => {
    document.removeEventListener("mousedown", onDocumentMousedown);
});

// ─── Filter options ──────────────────────────────────────────────────────────

const filterOptions = ref({ content_types: [], is_active: [] });

onMounted(async () => {
    try {
        const { data } = await axios.get(
            `/${props.subdomain}/api/facebook-messenger-template-filters`,
        );
        filterOptions.value = data;
    } catch (e) {
        console.error("[FBTemplateDataTable] failed to load filter options:", e);
    }
});

// ─── Filters ─────────────────────────────────────────────────────────────────

const FILTER_KEYS = ["content_type", "is_active"];
const { activeFilters, activeCount, setFilter, clearFilter, clearAll } =
    useTableFilters(FILTER_KEYS, "fb_messenger_templates_filters");

const filterDefs = computed(() => [
    { key: "content_type", label: "Type", options: filterOptions.value.content_types },
    { key: "is_active", label: "Status", options: filterOptions.value.is_active },
]);

// ─── Navigation ───────────────────────────────────────────────────────────────

function openCreate() {
    window.location.href = `/${props.subdomain}/facebook-messenger/template/create`;
}

function openEdit(row) {
    window.location.href = `/${props.subdomain}/facebook-messenger/template/${row.id}/edit`;
}

// ─── Toggle Active ────────────────────────────────────────────────────────────

async function toggleActive(row) {
    if (togglingActive.has(row.id)) return;
    togglingActive.add(row.id);

    try {
        const response = await axios.patch(
            `/${props.subdomain}/api/facebook-messenger-templates/${row.id}/toggle-active`,
        );
        table.value?.reload();
        showNotification(response.data?.message || "Updated successfully", "success");
    } catch (e) {
        console.error("[FBTemplateDataTable] toggleActive error:", e);
        showNotification(e.response?.data?.message || "Something went wrong", "danger");
    } finally {
        togglingActive.delete(row.id);
    }
}

// ─── Delete Template ──────────────────────────────────────────────────────────

const confirmingDelete = ref(false);
const templateToDelete = ref(null);

function deleteTemplate(row) {
    closeDropdown();
    templateToDelete.value = row;
    confirmingDelete.value = true;
}

function cancelDelete() {
    confirmingDelete.value = false;
    templateToDelete.value = null;
}

async function confirmDelete() {
    if (!templateToDelete.value) return;
    const row = templateToDelete.value;
    confirmingDelete.value = false;
    templateToDelete.value = null;

    try {
        await axios.delete(
            `/${props.subdomain}/api/facebook-messenger-templates/${row.id}`,
        );
        table.value?.reload();
        showNotification("Template deleted successfully", "success");
    } catch (e) {
        console.error("[FBTemplateDataTable] delete error:", e);
        showNotification(e.response?.data?.message || "Failed to delete template", "danger");
    }
}
</script>

<template>
    <div
        class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="p-4">
            <ResourceDataTable
                ref="table"
                resource="facebook-messenger-templates"
                :subdomain="subdomain"
                :extra-params="activeFilters"
                storage-key="fb_messenger_templates_table"
            >
                <!-- Toolbar leading: filters -->
                <template #toolbar-leading>
                    <DataTableFilters
                        :filters="filterDefs"
                        :active-filters="activeFilters"
                        :active-count="activeCount"
                        @change="setFilter"
                        @clear="clearFilter"
                        @clear-all="clearAll"
                    />
                </template>

                <!-- Active toggle (custom — overrides ToggleCell from table settings) -->
                <template #is_active="{ row, value }">
                    <button
                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="value ? 'bg-green-500' : 'bg-gray-200'"
                        :disabled="togglingActive.has(row.id)"
                        role="switch"
                        :aria-checked="String(value)"
                        @click="toggleActive(row)"
                    >
                        <span
                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            :class="value ? 'translate-x-4' : 'translate-x-0'"
                        />
                    </button>
                </template>

                <!-- Row actions — trigger button only; menu teleported to body -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle">
                        <div class="flex items-center justify-end">
                            <button
                                data-fb-messenger-dropdown
                                class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700"
                                @mousedown.stop="openDropdown($event, row)"
                            >
                                <BsThreeDotsVertical class="h-4 w-4" />
                            </button>
                        </div>
                    </td>
                </template>

                <!-- Actions column header -->
                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                    >
                        Actions
                    </th>
                </template>

                <!-- Empty state -->
                <template #empty>
                    <div class="flex flex-col items-center py-8 text-gray-400">
                        <svg
                            class="mb-3 h-12 w-12 opacity-40"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                            />
                        </svg>
                        <p class="text-sm">No templates found.</p>
                        <button
                            class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                            @click="openCreate"
                        >
                            <BsPlus class="h-4 w-4" />
                            Create your first template
                        </button>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <ConfirmDeleteModal
        :show="confirmingDelete"
        title="Delete Template"
        message="Are you sure you want to delete"
        :item-name="templateToDelete?.name"
        @confirm="confirmDelete"
        @cancel="cancelDelete"
    />

    <!-- Dropdown teleported to <body> — escapes table overflow clipping -->
    <Teleport to="body">
        <div
            v-if="activeDropdown !== null"
            data-fb-messenger-dropdown
            class="fixed z-[9999] w-36 rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
            :style="{ top: dropdownPos.top + 'px', left: dropdownPos.left - 144 + 'px' }"
        >
            <button
                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                @click="openEdit(activeRow); closeDropdown()"
            >
                <BsPencil class="h-3.5 w-3.5" />
                Edit
            </button>

            <button
                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                @click="deleteTemplate(activeRow)"
            >
                <BsTrash class="h-3.5 w-3.5" />
                Delete
            </button>
        </div>
    </Teleport>
</template>
