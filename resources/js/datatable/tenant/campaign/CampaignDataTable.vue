<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed } from "vue"
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue"
import { BsThreeDotsVertical } from "@kalimahapps/vue-icons";
import { useTableFilters } from "@/composables/useTableFilters";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";

const props = defineProps({
    subdomain: { type: String, required: true },
})

const table = ref(null)

const togglingActive = reactive(new Set());
const togglingRequired = reactive(new Set());
const togglingShowOnTable = reactive(new Set());

function onTableRefresh() {
    table.value?.reload();
}

// ─── Dropdown — teleported to <body> to escape table overflow clipping ─────────
const selectedRow = ref(null);
const activeDropdown = ref(null);
const dropdownPos = ref({ top: 0, left: 0 });

const filterOptions = ref({ templates: [] });

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
    window.addEventListener("campaign-table-refresh", onTableRefresh);
    axios
        .get(`/${props.subdomain}/api/campaign-filters`)
        .then(({ data }) => {
            filterOptions.value = data;
        })
        .catch((e) => {
            console.error("[ContactDataTable] failed to load filter options:", e);
        });
});

onBeforeUnmount(() => {
    document.removeEventListener("mousedown", onDocumentMousedown);
    window.addEventListener("campaign-table-refresh", onTableRefresh);
});

function onDocumentMousedown(e) {
    if (activeDropdown.value === null) return;

    if (!e.target.closest("[data-campaign-dropdown]")) {
        closeDropdown();
    }
}

// ─── Filters ─────────────────────────────────────────────────────────────────

const FILTER_KEYS = ["template_id"];
const { activeFilters, activeCount, setFilter, clearFilter, clearAll } =
    useTableFilters(FILTER_KEYS, "contacts_table_filters");

const filterDefs = computed(() => [
    { key: "template_id", label: "Template", options: filterOptions.value.templates },
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
]);

function closeDropdown() {
    activeDropdown.value = null;
}


// ─── Livewire event dispatching ───────────────────────────────────────────────

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}

function confirmDelete(id) {
    dispatchLivewire("confirmDelete", { campaignId: id });
}

</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="campaigns" :subdomain="subdomain" storage-key="campaigns_table"
                :extra-params="activeFilters">

                <template #rel_type="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': value === 'Customer',
                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': value === 'Lead',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': value === 'Guest',
                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200': !['Customer', 'Lead', 'Guest'].includes(value)
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1) }}
                    </span>
                </template>

                <template #status="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': value === 'Executed',
                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': value === 'In Progress',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': value === 'Failed',
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': value === 'Pending',
                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200': !['Executed', 'In Progress', 'Failed', 'Pending'].includes(value)
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1) }}
                    </span>
                </template>

                <template #toolbar-leading>
                    <DataTableFilters :filters="filterDefs" :active-filters="activeFilters" :active-count="activeCount"
                        @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />
                </template>


                <template #campaign_name="{ row, value }">
                    <a :href="`/${subdomain}/campaigns/campaign/details/${row.id}`"
                        class="dark:text-gray-200 text-primary-600 hover:underline dark:hover:text-primary-400">
                        {{ value }}
                    </a>
                </template>

                <!-- Row actions — trigger button only; menu teleported to body -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle flex">
                        <div class="flex items-center justify-end">
                            <button v-if="row.can_view || row.can_edit || row.can_delete" data-campaign-dropdown
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
        <div v-if="activeDropdown !== null && selectedRow" data-campaign-dropdown
            class="fixed z-[9999] w-32 rounded-md border border-gray-200 bg-white shadow-lg dark:bg-slate-700 dark:border-gray-600" :style="{
                top: dropdownPos.top + 'px',
                left: dropdownPos.left + 'px',
                transform: 'translateX(-100%)'
            }">

            <!-- view -->
            <a v-if="selectedRow.can_view" :href="`/${subdomain}/campaigns/campaign/details/${activeDropdown}`"
                class="block w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600"
                @click="closeDropdown">
                View
            </a>

            <!-- Edit -->
            <a v-if="selectedRow.can_edit" :href="`/${subdomain}/campaign-data/${activeDropdown}`"
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