<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import { BsThreeDotsVertical } from "@kalimahapps/vue-icons";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";
import { useTableFilters } from "@/composables/useTableFilters";

const props = defineProps({
    subdomain: { type: String, required: true },
});

const filterOptions = ref({
    reply_type: [],
    rel_type: [],
});

const FILTER_KEYS = [
    "reply_type",
    "rel_type",
    "created_from",
    "created_until",
];

const {
    activeFilters,
    activeCount,
    setFilter,
    clearFilter,
    clearAll,
} = useTableFilters(FILTER_KEYS, "messagebot_table_filters");

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

// Toggle loading state per row (prevent double-clicks)
// Must be reactive() not ref() — Vue tracks Set mutations only via reactive Proxy
const togglingActive = reactive(new Set());

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

// Register sync — NEVER inside async callback; listener must exist from mount
onMounted(() => {
    window.addEventListener("message-bot-table-refresh", onTableRefresh);
    document.addEventListener("mousedown", onDocumentMousedown);

    axios
        .get(`/${props.subdomain}/api/message-bot-filters`)
        .then(({ data }) => {
            filterOptions.value = data;
        })
        .catch((e) => {
            console.error("[MessageBotTable] Failed loading filters:", e);
        });
});

const filterDefs = computed(() => [
    {
        key: "reply_type",
        label: "Reply Type",
        options: filterOptions.value.reply_type,
    },
    {
        key: "rel_type",
        label: "Relation Type",
        options: filterOptions.value.rel_type,
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
]);

onBeforeUnmount(() => {
    window.removeEventListener("message-bot-table-refresh", onTableRefresh);
    document.removeEventListener("mousedown", onDocumentMousedown);
});

function onDocumentMousedown(e) {
    if (activeDropdown.value === null) return;
    if (!e.target.closest("[data-message_bot-dropdown]")) {
        closeDropdown();
    }
}

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

// ─── is_active toggle (interactive PATCH) ─────────────────────────────────────

async function toggleActive(row) {
    if (togglingActive.has(row.id)) return;

    togglingActive.add(row.id);

    try {
        const response = await axios.patch(
            `/${props.subdomain}/api/message-bot/${row.id}/toggle-active`,
        );

        table.value?.reload();

        const message = response.data?.message || "Updated successfully";
        showNotification(message, "success");
    } catch (e) {
        console.error("[MessageBoaptDataTable] toggleActive error:", e);
        const errorMessage = e.response?.data?.message || "Something went wrong";
        showNotification(errorMessage, "danger");
    } finally {
        togglingActive.delete(row.id);
    }
}

function cloneRecord(id) {
    dispatchLivewire("cloneRecord", { messagebotId: id });
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="message-bot" :extra-params="activeFilters" :subdomain="subdomain"
                storage-key="message_bot_table">

                <template #toolbar-leading>
                    <DataTableFilters :filters="filterDefs" :active-filters="activeFilters" :active-count="activeCount"
                        @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />
                </template>

                <!-- is_active toggle — interactive PATCH, overrides ToggleCell -->
                <template #is_bot_active="{ row, value }">
                    <button
                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="value ? 'bg-primary-500' : 'bg-gray-200 dark:bg-slate-700'" :disabled="togglingActive.has(row.id)"
                        role="switch" :aria-checked="String(value)" @click="toggleActive(row)">
                        <span
                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            :class="value ? 'translate-x-4' : 'translate-x-0'" />
                    </button>
                </template>

                <!-- Row actions — trigger button only; menu teleported to body -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle">
                        <div class="flex items-center justify-end">
                            <button v-if="row.can_edit || row.can_delete" data-message_bot-dropdown
                                class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-600 dark:text-white"
                                @mousedown.stop="openDropdown($event, row)">
                                <BsThreeDotsVertical class="h-4 w-4" />
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
                        <p>No message bot found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>

    <!-- Dropdown teleported to <body> — escapes table overflow clipping -->
    <Teleport to="body">
        <div v-if="activeDropdown !== null && selectedRow" data-message_bot-dropdown
            class="fixed z-[9999] w-32 rounded-md border border-gray-200 bg-white shadow-lg dark:bg-slate-700 dark:border-gray-600" :style="{
                top: dropdownPos.top + 'px',
                left: dropdownPos.left + 'px',
                transform: 'translateX(-100%)'
            }">

            <a v-if="selectedRow.can_edit" :href="`/${subdomain}/message-bot/bot/${activeDropdown}`"
                class="block w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600"
                @click="closeDropdown">
                Edit
            </a>

            <!-- Clone -->
            <button v-if="selectedRow.can_edit"
                class="w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600"
                @click="cloneRecord(activeDropdown); closeDropdown()">
                Clone
            </button>

            <!-- Delete -->
            <button v-if="selectedRow.can_delete"
                class="w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-slate-600" @click="
                    dispatchLivewire('confirmDelete', {
                        messagebotId: activeDropdown,
                    });
                closeDropdown();
                ">
                Delete
            </button>
        </div>
    </Teleport>
</template>
