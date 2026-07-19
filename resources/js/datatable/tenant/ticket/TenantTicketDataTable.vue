<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";
import { useTableFilters } from "@/composables/useTableFilters";

const props = defineProps({
    subdomain: { type: String, required: true },
});

// Table reference
const table = ref(null);

// ─── Filter options from API ─────────────────────────────────────
const filterOptions = ref({
    statuses: [],
    priorities: [],
});


// ─── Persisted filter state ──────────────────────────────────────
const FILTER_KEYS = [
    "status",
    "priority",
    "created_from",
    "created_until",
];

const {
    activeFilters,
    activeCount,
    setFilter,
    clearFilter,
    clearAll,
} = useTableFilters(FILTER_KEYS, "tenant_tickets_table_filters");

// ─── Load filters + Livewire refresh ─────────────────────────────
onMounted(() => {
    window.addEventListener("tenant-tickets-table-refresh", onTableRefresh);

    axios
        .get(`/${props.subdomain}/api/ticket-filters`)
        .then(({ data }) => {
            filterOptions.value = data;
        })
        .catch((e) => {
            console.error("[TenantTicketTable] Failed loading filters:", e);
        });
});

onBeforeUnmount(() => {
    window.removeEventListener(
        "tenant-tickets-table-refresh",
        onTableRefresh
    );
});

// ─── Filter definitions for DataTableFilters ─────────────────────
const filterDefs = computed(() => [
    {
        key: "status",
        label: "Status",
        options: filterOptions.value.statuses.map(s => ({
            value: s.id,
            label: s.name,
        })),
    },
    {
        key: "priority",
        label: "Priority",
        options: filterOptions.value.priorities.map(p => ({
            value: p.id,
            label: p.name,
        })),
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

// ─── Reload handler ──────────────────────────────────────────────
function onTableRefresh() {
    table.value?.reload();
}

// ─── Livewire dispatch ───────────────────────────────────────────
function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}

// ─── Export CSV ──────────────────────────────────────────────────
function exportCsv() {
    const params = table.value?.queryParams ?? {};

    const qs = new URLSearchParams(
        Object.fromEntries(
            Object.entries(params).filter(([, v]) => v != null)
        )
    ).toString();

    window.open(
        `/${props.subdomain}/api/tenant-ticket/export${qs ? "?" + qs : ""}`
    );
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm
               dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="tenant-ticket" :subdomain="subdomain" :extra-params="activeFilters"
                storage-key="tenant_ticket_table">

                <template #ticket_id="{ row, value }">
                    <a :href="`/${subdomain}/tickets/${row.id}`"
                        class="dark:text-gray-200 text-primary-600 hover:underline dark:hover:text-primary-400">
                        {{ value }}
                    </a>
                </template>

                <!-- Filters -->
                <template #toolbar-leading>
                    <DataTableFilters :filters="filterDefs" :active-filters="activeFilters" :active-count="activeCount"
                        @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />
                </template>

                <!-- Export -->
                <template #toolbar-trailing>
                    <button type="button" class="inline-flex h-8 items-center gap-1.5
                               rounded-md border border-gray-200 bg-white
                               px-2.5 text-sm font-medium text-gray-600 shadow-sm
                               hover:bg-gray-50 dark:bg-slate-800 dark:text-white dark:border-gray-600" title="Export to CSV" @click="exportCsv">
                        <svg class="h-3.5 w-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export
                    </button>
                </template>

                <!-- Priority badge -->
                <template #priority="{ value }">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-success-100 text-success-700 dark:text-success-500 dark:bg-slate-800 border dark:border-success-600': value === 'low',
                        'bg-warning-100 text-warning-700 dark:text-warning-500 dark:bg-slate-800 border dark:border-warning-600': value === 'medium',
                        'bg-danger-100 text-danger-700 dark:text-danger-500 dark:bg-slate-800 border dark:border-danger-600': value === 'high',
                        'bg-purple-100 text-purple-700 dark:text-purple-500 dark:bg-slate-800 border dark:border-purple-600': value === 'urgent',
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1) }}
                    </span>
                </template>

                <!-- Status badge -->
                <template #status="{ value }">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-info-100 text-info-700 dark:text-info-500 dark:bg-slate-800 border dark:border-info-600': value === 'open',
                        'bg-primary-100 text-primary-700 dark:text-primary-500 dark:bg-slate-800 border dark:border-primary-600': value === 'answered',
                        'bg-warning-100 text-warning-700 dark:text-warning-500 dark:bg-slate-800 border dark:border-warning-600': value === 'on_hold',
                        'bg-gray-200 text-gray-700 dark:text-gray-500 dark:bg-slate-800 border dark:border-gray-600': value === 'closed',
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1).replace('_', ' ') }}
                    </span>
                </template>

                <!-- Created at -->
                <template #created_at_human="{ value }">
                    <span class="text-sm text-gray-500">
                        {{ value }}
                    </span>
                </template>

                <!-- Actions -->
                <template #header-trailing>
                    <th class="w-[80px] text-xs font-medium text-gray-400 uppercase">
                        Actions
                    </th>
                </template>

                <template #row-trailing="{ row }">
                    <td class="px-4 py-2">
                        <div class="flex justify-end">
                            <button type="button" title="View Ticket" class="inline-flex items-center px-2 py-1 text-xs
                                       font-medium text-primary-600 bg-primary-100 dark:bg-slate-700 dark:text-blue-500
                                       rounded hover:bg-primary-200"
                                @click="dispatchLivewire('viewTicket', { ticketId: row.id })">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5
                                           c4.478 0 8.268 2.943 9.542 7
                                           -1.274 4.057-5.064 7-9.542 7
                                           -4.477 0-8.268-2.943-9.542-7Z" />
                                </svg>
                            </button>
                        </div>
                    </td>
                </template>

                <!-- Empty state -->
                <template #empty>
                    <div class="flex flex-col items-center py-6 text-gray-400">
                        <svg class="mb-2 h-10 w-10 opacity-40" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4
                                   M9 3v18m0 0h10a2 2 0 002-2V9
                                   M9 21H5a2 2 0 01-2-2V9" />
                        </svg>
                        <p>No tickets found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>
</template>