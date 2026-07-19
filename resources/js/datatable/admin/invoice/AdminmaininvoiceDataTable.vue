<script setup>
import { ref, computed } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";
import { useTableFilters } from "@/composables/useTableFilters";


const table = ref(null);

const FILTER_KEYS = ["status"];

const { activeFilters, activeCount, setFilter, clearFilter, clearAll } =
    useTableFilters(FILTER_KEYS, "transaction_table_filters");

const filterDefs = computed(() => [
    {
        key: "status",
        label: "Status",
        options: [
            { value: "new", label: "Unpaid" },
            { value: "paid", label: "Paid" },
            { value: "cancelled", label: "Cancelled" },
        ],
    },
]);

</script>

<template>
    <div class="rounded-xl border bg-white dark:bg-slate-800 dark:border-gray-600 shadow-sm">
        <div class="p-4">

            <ResourceDataTable ref="table" resource="maininvoice" base-url="/admin/api" storage-key="maininvoice_table"
                :extra-params="activeFilters">

                <!-- Status Badge -->
                <template #status="{ value }">
                    <!-- Paid State -->
                    <span v-if="value === 'paid'"
                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-success-100 dark:bg-slate-800 dark:text-success-500 border dark:border-success-800  text-success-800">
                        Paid
                    </span>

                    <!-- Unpaid / New State -->
                    <span v-else-if="value === 'new'"
                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-warning-100 dark:bg-slate-800 dark:text-warning-500 border dark:border-warning-800 text-warning-800">
                        Unpaid
                    </span>

                    <!-- Cancelled -->
                    <span v-else-if="value === 'cancelled'"
                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-danger-100 dark:bg-slate-800 dark:text-danger-500 border dark:border-danger-800 text-danger-800">
                        Cancelled
                    </span>

                    <!-- Default / Other States -->
                    <span v-else
                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-slate-800 text-gray-800 dark:text-gray-300">
                        <span class="h-1.5 w-1.5 bg-gray-400 rounded-full mr-1.5 inline-block"></span>
                        {{ value.charAt(0).toUpperCase() + value.slice(1) }}
                    </span>
                </template>

                <!-- Toolbar Filters -->
                <template #toolbar-leading>
                    <DataTableFilters :filters="filterDefs" :active-filters="activeFilters" :active-count="activeCount"
                        @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />
                </template>


                <!-- Created At -->
                <template #created_at="{ row, value }">
                    <span :title="row.created_at_full">
                        {{ value }}
                    </span>
                </template>

                <!-- View Details -->
                <template #view_details="{ row }">
                    <a :href="row.view_url"
                        class="inline-flex items-center justify-center px-3 py-1 text-sm border border-info-300 rounded-md font-medium disabled:opacity-50 disabled:pointer-events-none transition bg-info-100 text-info-700 hover:bg-info-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-info-300 dark:bg-slate-700 dark:border-slate-500 dark:text-info-400 dark:hover:border-info-600 dark:hover:bg-info-600 dark:hover:text-white dark:focus:ring-offset-slate-800">
                        View Details
                    </a>
                </template>

            </ResourceDataTable>

        </div>
    </div>
</template>