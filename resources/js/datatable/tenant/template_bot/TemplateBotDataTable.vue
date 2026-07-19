<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed } from "vue"
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue"
import { BsThreeDotsVertical } from "@kalimahapps/vue-icons";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";
import { useTableFilters } from "@/composables/useTableFilters";


const props = defineProps({
    subdomain: { type: String, required: true },
})

const table = ref(null)

function onTableRefresh() {
    table.value?.reload();
}

const togglingActive = reactive(new Set());

const FILTER_KEYS = [
    "reply_type",
    "relation_type",
    "created_from",
    "created_until",
]
const {
    activeFilters, activeCount, setFilter, clearFilter, clearAll
} = useTableFilters(FILTER_KEYS, "template_bots_table_filters")

const filterDefs = computed(() => [
    {
        key: "reply_type",
        label: "Reply Type",
        options: [
            { label: "On Exact Match", value: 1 },
            { label: "When Message Contains", value: 2 },
            { label: "When Lead or Client Sends First Message", value: 3 },
            { label: "Default Reply", value: 4 },
        ]
    },
    {
        key: "relation_type",
        label: "Relation Type",
        options: [
            { label: "Lead", value: "lead" },
            { label: "Guest", value: "guest" },
            { label: "Customer", value: "customer" },
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

onMounted(() => {
    document.addEventListener("mousedown", onDocumentMousedown);
    window.addEventListener("template-bot-table-refresh", onTableRefresh);
});

onBeforeUnmount(() => {
    document.removeEventListener("mousedown", onDocumentMousedown);
    window.removeEventListener("template-bot-table-refresh", onTableRefresh);
});

function onDocumentMousedown(e) {
    if (activeDropdown.value === null) return;

    if (!e.target.closest("[data-template-bot-dropdown]")) {
        closeDropdown();
    }
}

function closeDropdown() {
    activeDropdown.value = null;
}

// ─── is_active toggle (interactive PATCH) ─────────────────────────────────────

async function toggleActive(row) {
    if (togglingActive.has(row.id)) return;

    togglingActive.add(row.id);

    try {
        const response = await axios.patch(
            `/${props.subdomain}/api/template-bots/${row.id}/toggle-active`,
        );

        closeDropdown();
        table.value?.reload();

        const message = response.data?.message || "Updated successfully";
        showNotification(message, "success");
    } catch (e) {
        console.error("[CustomFieldDataTable] toggleActive error:", e);
        const errorMessage = e.response?.data?.message || "Something went wrong";
        showNotification(errorMessage, "danger");
    } finally {
        togglingActive.delete(row.id);
    }
}

// ─── Livewire event dispatching ───────────────────────────────────────────────

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}

function confirmDelete(id) {
    dispatchLivewire("confirmDelete", { templatebotId: id });
}

function cloneRecord(id) {
    dispatchLivewire("cloneRecord", { templatebotId: id });
}

</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="template-bots" :subdomain="subdomain" :extra-params="activeFilters"
                storage-key="template-bots_table">

                <template #toolbar-leading>
                    <DataTableFilters :filters="filterDefs" :active-filters="activeFilters" :active-count="activeCount"
                        @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />
                </template>

                <!-- is_active toggle — interactive PATCH, overrides ToggleCell -->
                <template #is_bot_active="{ row, value }">
                    <button
                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="value ? 'bg-primary-500' : 'bg-gray-200 dark:bg-slate-600'" :disabled="togglingActive.has(row.id)"
                        role="switch" :aria-checked="String(value)" @click="toggleActive(row)">
                        <span
                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            :class="value ? 'translate-x-4' : 'translate-x-0'" />
                    </button>
                </template>

                <!-- Row actions — trigger button only; menu teleported to body -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle flex">
                        <div class="flex items-center justify-end">
                            <button v-if="row.can_edit || row.can_delete" data-contact-dropdown
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

            </ResourceDataTable>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="activeDropdown !== null && selectedRow" data-template-bot-dropdown
            class="fixed z-[9999] w-32 rounded-md border border-gray-200 bg-white shadow-lg dark:bg-slate-700 dark:border-gray-600" :style="{
                top: dropdownPos.top + 'px',
                left: dropdownPos.left + 'px',
                transform: 'translateX(-100%)'
            }">

            <!-- Edit -->
            <a v-if="selectedRow.can_edit" :href="`/${subdomain}/template-bot/bot/${activeDropdown}`"
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
                class="w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-slate-600"
                @click="confirmDelete(activeDropdown); closeDropdown()">
                Delete
            </button>
        </div>
    </Teleport>
</template>