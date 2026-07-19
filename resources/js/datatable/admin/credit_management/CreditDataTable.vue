<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

const table = ref(null);

function onTableRefresh() {
    table.value?.reload();
}

onMounted(() => {
    window.addEventListener("credit-table-refresh", onTableRefresh);
});

onBeforeUnmount(() => {
    window.removeEventListener("credit-table-refresh", onTableRefresh);
});
</script>

<template>
    <div class="rounded-xl border bg-white dark:bg-slate-800 dark:border-gray-600 shadow-sm">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="credit" storage-key="credit_table" base-url="/admin/api">

                <!-- Updated At (since + tooltip) -->
                <template #updated_at="{ value }">
                    <span :title="value">
                        {{ value }}
                    </span>
                </template>

                <!-- View Details Button -->
                <template #view_details="{ row }">
                    <a :href="`/admin/credit-management/${row.tenant_id}`"
                        class="inline-flex items-center justify-center px-3 py-1 text-sm border border-info-300 rounded-md font-medium disabled:opacity-50 disabled:pointer-events-none transition bg-info-100 text-info-700 hover:bg-info-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-info-300 dark:bg-slate-700 dark:border-slate-500 dark:text-info-400 dark:hover:border-info-600 dark:hover:bg-info-600 dark:hover:text-white dark:focus:ring-offset-slate-800">
                        View Details
                    </a>
                </template>

            </ResourceDataTable>
        </div>
    </div>
</template>