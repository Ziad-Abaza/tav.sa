<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";
import { useTableFilters } from "@/composables/useTableFilters";

const table = ref(null);

function onTableRefresh() {
    table.value?.reload();
}

onMounted(() => {
    window.addEventListener("transaction-table-refresh", onTableRefresh);
});

onBeforeUnmount(() => {
    window.removeEventListener("transaction-table-refresh", onTableRefresh);
});

// ─── Filters ─────────────────────────────────────────────────────────────────

const FILTER_KEYS = ["status", "created_from", "created_until"];

const { activeFilters, activeCount, setFilter, clearFilter, clearAll } =
    useTableFilters(FILTER_KEYS, "transaction_table_filters");

const filterDefs = computed(() => [
    {
        key: "status",
        label: "Status",
        options: [
            { value: "pending", label: "Pending" },
            { value: "success", label: "Success" },
            { value: "failed", label: "Failed" },
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

</script>


<template>
    <div class="rounded-xl border bg-white dark:bg-slate-800 dark:border-gray-600 shadow-sm">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="transaction" base-url="/admin/api" storage-key="transaction_table"
                :extra-params="activeFilters">

                <!-- Payment Gateway Badge (Type) -->
                <template #type="{ value }">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="{
                        'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400': value === 'stripe',
                        'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400': value === 'offline',
                        'bg-gray-100 text-gray-800 dark:text-gray-400 dark:bg-gray-900/20': !['stripe', 'offline'].includes(value)
                    }">
                        {{ value }}
                    </span>
                </template>

                <!-- Status Badge -->
                <template #status="{ value }">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium capitalize" :class="{
                        'bg-success-100 text-success-800 dark:text-success-500 border dark:border-success-800 dark:bg-success-900/20': value === 'success',
                        'bg-danger-100 text-danger-800 dark:text-danger-500 border dark:border-danger-800 dark:bg-danger-900/20': value === 'failed',
                        'bg-warning-100 text-warning-800 dark:text-warning-500 border dark:border-warning-800 dark:bg-warning-900/20': value === 'pending',
                        'bg-gray-100 text-gray-800 dark:text-gray-500 border dark:border-gray-600 dark:bg-gray-900/20': !['success', 'failed', 'pending'].includes(value)
                    }">
                        {{ value }}
                    </span>
                </template>

                <!-- Toolbar Filters -->
                <template #toolbar-leading>
                    <DataTableFilters :filters="filterDefs" :active-filters="activeFilters" :active-count="activeCount"
                        @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />
                </template>

                <!-- View Details Button -->
                <template #view_details="{ row }">
                    <a :href="`/admin/transactions/${row.id}`"
                        class="inline-flex items-center justify-center px-3 py-1 text-sm border border-info-300 rounded-md font-medium disabled:opacity-50 disabled:pointer-events-none transition bg-info-100 text-info-700 hover:bg-info-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-info-300 dark:bg-slate-700 dark:border-slate-500 dark:text-info-400 dark:hover:border-info-600 dark:hover:bg-info-600 dark:hover:text-white dark:focus:ring-offset-slate-800">
                        View Details
                    </a>
                </template>

                <!-- Created At -->
                <template #created_at="{ row, value }">
                    <span :title="row.created_at_full">
                        {{ value }}
                    </span>
                </template>

            </ResourceDataTable>
        </div>
    </div>
</template>