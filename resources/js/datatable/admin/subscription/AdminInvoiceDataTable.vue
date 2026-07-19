<script setup>
import { ref } from "vue"
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue"
import {
    AkEye,
    AkDownload
} from "@kalimahapps/vue-icons";

const props = defineProps({
    subscriptionId: { type: [String, Number], required: true },
})

const table = ref(null)

/**
 * Generate show URL
 */
function invoiceShowUrl(id) {
    return `/admin/invoices/${id}`;
}

/**
 * Generate download URL
 */
function invoiceDownloadUrl(id) {
    return `/admin/invoices/${id}/download`;
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="invoices" base-url="/admin/api"
                :extra-params="{ subscription_id: subscriptionId }" storage-key="admin-invoices_table">

                <template #status="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-green-100 text-green-800 dark:bg-slate-700 dark:text-green-500 border dark:border-gray-600': value === 'paid',
                        'bg-blue-100 text-blue-800 dark:bg-slate-700 dark:text-blue-500 border dark:border-gray-600': value === 'new',
                        'bg-red-100 text-red-800 dark:bg-slate-700 dark:text-red-500 border dark:border-gray-600': value === 'cancelled',
                        'bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-gray-500 border dark:border-gray-600': !['paid', 'new', 'cancelled'].includes(value)
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1) }}
                    </span>
                </template>

                <!-- Actions Column Header -->
                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>

                <!-- Row Actions -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle flex flex-row">
                        <div class="flex items-center justify-end gap-4">

                            <!-- View -->
                            <a :href="invoiceShowUrl(row.id)"
                                class="text-info-600 hover:text-info-500 flex items-center text-sm gap-2">
                                <AkEye class="h-4 w-4" /> View
                            </a>

                            <!-- Download (Only if Paid) -->
                            <a v-if="row.status === 'paid'" :href="invoiceDownloadUrl(row.id)"
                                class="text-info-600 hover:text-info-500 flex items-center text-sm gap-2">
                                <AkDownload class="h-4 w-4" /> Download
                            </a>

                        </div>
                    </td>
                </template>

            </ResourceDataTable>
        </div>
    </div>
</template>