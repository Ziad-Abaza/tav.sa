<script setup>
import { ref, computed } from "vue"
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue"
import { useTableFilters } from "@/composables/useTableFilters";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";


const props = defineProps({
    subdomain: { type: String, required: true },
})

const table = ref(null)

// ─── Filters ─────────────────────────────────────────────────────────────────

const FILTER_KEYS = ["status"];
const { activeFilters, activeCount, setFilter, clearFilter, clearAll } =
    useTableFilters(FILTER_KEYS, "maininvoices_table_filters");

const filterDefs = computed(() => [
    {
        key: "status",
        label: "Status",
        options: [
            { label: "Paid", value: "paid" },
            { label: "Unpaid", value: "unpaid" },
        ],
    },
]);

// ─── Reload on Livewire actions ────────────────────────────────────────────────

function onTableRefresh() {
    table.value?.reload();
}

function viewInvoice(id) {
    window.dispatchEvent(
        new CustomEvent('viewInvoice', {
            detail: { invoiceId: id }
        })
    )
}

function truncate(text, length = 30) {
    if (!text) return ''
    return text.length > length
        ? text.substring(0, length) + '...'
        : text
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="maininvoices" :subdomain="subdomain"
                storage-key="maininvoices_table" :extra-params="activeFilters">

                <template #toolbar-leading>
                    <DataTableFilters :filters="filterDefs" :active-filters="activeFilters" :active-count="activeCount"
                        @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />
                </template>

                <template #status="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': value === 'paid',
                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': value === 'new',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': value === 'cancelled',
                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200': !['paid', 'new', 'cancelled'].includes(value)
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1) }}
                    </span>
                </template>

                <template #title="{ row, value }">
                    <div class="flex flex-col">
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ value }}
                        </span>

                        <span v-if="row.description" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ truncate(row.description, 30) }}
                        </span>
                    </div>
                </template>

                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle flex flex-row">
                        <div class="flex items-center justify-end gap-4">
                            <span @click="viewInvoice(row.id)"
                                class="inline-flex items-center justify-center px-3 py-1 text-sm border border-info-300 rounded-md font-medium disabled:opacity-50 disabled:pointer-events-none transition bg-info-100 text-info-700 hover:bg-info-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-info-300 dark:bg-slate-700 dark:border-slate-500 dark:text-info-400 dark:hover:border-info-600 dark:hover:bg-info-600 dark:hover:text-white dark:focus:ring-offset-slate-800 cursor-pointer">
                                View Details
                            </span>
                        </div>
                    </td>
                </template>
            </ResourceDataTable>
        </div>
    </div>
</template>