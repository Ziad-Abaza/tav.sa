<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";
import ConfirmDeleteModal from "@/datatable/components/ConfirmDeleteModal.vue";
import { BsThreeDotsVertical, BsPlus, BsEye, BsTrash, BsPause, BsPlay, BsPencil } from "@kalimahapps/vue-icons";
import { useTableFilters } from "@/composables/useTableFilters";

const props = defineProps({
    subdomain: { type: String, required: true },
});

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

// Toggle loading state per campaign (prevent double-clicks)
const togglingPause = reactive(new Set());

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
    if (!e.target.closest("[data-fb-campaign-dropdown]")) closeDropdown();
}

onMounted(() => {
    document.addEventListener("mousedown", onDocumentMousedown);
});

onBeforeUnmount(() => {
    document.removeEventListener("mousedown", onDocumentMousedown);
});

// ─── Filter options ──────────────────────────────────────────────────────────

const filterOptions = ref({ status: [] });

onMounted(async () => {
    try {
        const { data } = await axios.get(
            `/${props.subdomain}/api/fb-messenger-campaign-filters`,
        );
        filterOptions.value = data;
    } catch (e) {
        console.error("[FBCampaignDataTable] failed to load filter options:", e);
    }
});

// ─── Filters ─────────────────────────────────────────────────────────────────

const FILTER_KEYS = ["status"];
const { activeFilters, activeCount, setFilter, clearFilter, clearAll } =
    useTableFilters(FILTER_KEYS, "fb_messenger_campaigns_filters");

const filterDefs = computed(() => [
    { key: "status", label: "Status", options: filterOptions.value.status },
]);

// ─── Navigation ───────────────────────────────────────────────────────────────

function openCreate() {
    window.location.href = `/${props.subdomain}/facebook-messenger/campaign/create`;
}

function openDetails(row) {
    window.location.href = `/${props.subdomain}/facebook-messenger/campaign/${row.id}/details`;
}

function openEdit(row) {
    window.location.href = `/${props.subdomain}/facebook-messenger/campaign/${row.id}/edit`;
}

// ─── Toggle Pause ────────────────────────────────────────────────────────────

async function togglePause(row) {
    if (togglingPause.has(row.id)) return;
    togglingPause.add(row.id);

    try {
        const response = await axios.patch(
            `/${props.subdomain}/api/fb-messenger-campaigns/${row.id}/toggle-pause`,
        );
        table.value?.reload();
        showNotification(response.data?.message || "Updated successfully", "success");
    } catch (e) {
        console.error("[FBCampaignDataTable] togglePause error:", e);
        showNotification(e.response?.data?.message || "Something went wrong", "danger");
    } finally {
        togglingPause.delete(row.id);
    }
}

// ─── Delete Campaign ──────────────────────────────────────────────────────────

const confirmingDelete = ref(false);
const campaignToDelete = ref(null);

function deleteCampaign(row) {
    closeDropdown();
    campaignToDelete.value = row;
    confirmingDelete.value = true;
}

function cancelDelete() {
    confirmingDelete.value = false;
    campaignToDelete.value = null;
}

async function confirmDelete() {
    if (!campaignToDelete.value) return;
    const row = campaignToDelete.value;
    confirmingDelete.value = false;
    campaignToDelete.value = null;

    try {
        await axios.delete(
            `/${props.subdomain}/api/fb-messenger-campaigns/${row.id}`,
        );
        table.value?.reload();
        showNotification("Campaign deleted successfully", "success");
    } catch (e) {
        console.error("[FBCampaignDataTable] delete error:", e);
        showNotification(e.response?.data?.message || "Failed to delete campaign", "danger");
    }
}

// ─── Status badge colors ─────────────────────────────────────────────────────

function getStatusBadgeClass(color) {
    const colorMap = {
        success: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        secondary: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        danger: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    };
    return colorMap[color] || colorMap.secondary;
}
</script>

<template>
    <div
        class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="p-4">
            <ResourceDataTable
                ref="table"
                resource="fb-messenger-campaigns"
                :subdomain="subdomain"
                :extra-params="activeFilters"
                storage-key="fb_messenger_campaigns_table"
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

                <!-- Status badge -->
                <template #status="{ row }">
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="getStatusBadgeClass(row.status_color)"
                    >
                        {{ row.status }}
                    </span>
                </template>

                <!-- Delivery stats -->
                <template #delivery="{ row }">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-green-600 dark:text-green-400" title="Sent">{{ row.sent_count }}</span>
                        <span class="text-gray-400">/</span>
                        <span class="text-gray-600 dark:text-gray-300" title="Total">{{ row.total_count }}</span>
                        <span v-if="row.failed_count > 0" class="text-red-500" title="Failed">({{ row.failed_count }} failed)</span>
                    </div>
                </template>

                <!-- Row actions — trigger button only; menu teleported to body -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle">
                        <div class="flex items-center justify-end gap-1">
                            <!-- Pause/Resume button -->
                            <button
                                v-if="!row.is_sent || row.pending_count > 0"
                                class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700"
                                :disabled="togglingPause.has(row.id)"
                                @click="togglePause(row)"
                                :title="row.pause_campaign ? 'Resume' : 'Pause'"
                            >
                                <BsPlay v-if="row.pause_campaign" class="h-4 w-4 text-green-500" />
                                <BsPause v-else class="h-4 w-4 text-yellow-500" />
                            </button>

                            <button
                                data-fb-campaign-dropdown
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
                                d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"
                            />
                        </svg>
                        <p class="text-sm">No campaigns found.</p>
                        <button
                            class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                            @click="openCreate"
                        >
                            <BsPlus class="h-4 w-4" />
                            Create your first campaign
                        </button>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <ConfirmDeleteModal
        :show="confirmingDelete"
        title="Delete Campaign"
        message="Are you sure you want to delete"
        :item-name="campaignToDelete?.name"
        :is-deleting="false"
        @confirm="confirmDelete"
        @cancel="cancelDelete"
    />

    <!-- Dropdown teleported to <body> — escapes table overflow clipping -->
    <Teleport to="body">
        <div
            v-if="activeDropdown !== null"
            data-fb-campaign-dropdown
            class="fixed z-[9999] w-36 rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
            :style="{ top: dropdownPos.top + 'px', left: dropdownPos.left - 144 + 'px' }"
        >
            <button
                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                @click="openDetails(activeRow); closeDropdown()"
            >
                <BsEye class="h-3.5 w-3.5" />
                View Details
            </button>

            <button
                v-if="activeRow && !activeRow.is_sent"
                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                @click="openEdit(activeRow); closeDropdown()"
            >
                <BsPencil class="h-3.5 w-3.5" />
                Edit
            </button>

            <button
                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                @click="deleteCampaign(activeRow)"
            >
                <BsTrash class="h-3.5 w-3.5" />
                Delete
            </button>
        </div>
    </Teleport>
</template>
