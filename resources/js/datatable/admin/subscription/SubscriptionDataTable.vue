<script setup>
import { ref } from "vue"
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue"

const table = ref(null)

</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="subscriptions" base-url="/admin/api"
                storage-key="admin_subscriptions_table">

                <template #status="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'border dark:border-green-900 bg-green-100 text-green-800 dark:bg-slate-800 dark:text-green-500': value === 'active',
                        'border dark:border-blue-900 bg-blue-100 text-blue-800 dark:bg-slate-700 dark:text-blue-500': value === 'new',
                        'border dark:border-gray-900 bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-gray-500': value === 'ended',
                        'border dark:border-red-900 bg-red-100 text-red-800 dark:bg-slate-700 dark:text-red-500': value === 'cancelled',
                        'border dark:border-pink-900 bg-pink-100 text-pink-800 dark:bg-slate-700 dark:text-pink-500': value === 'terminated',
                        'border dark:border-yellow-900 bg-yellow-100 text-yellow-800 dark:bg-slate-700 dark:text-yellow-500': value === 'trial',
                        'border dark:border-purple-900 bg-purple-100 text-purple-800 dark:bg-slate-700 dark:text-purple-500': value === 'paused',
                        'border dark:border-orange-900 bg-orange-100 text-orange-800 dark:bg-slate-700 dark:text-orange-500':
                            !['active', 'new', 'ended', 'cancelled', 'terminated', 'trial', 'paused'].includes(value)
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1) }}
                    </span>
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

                            <a :href="`/admin/subscription/${row.id}`"
                                class="inline-flex items-center justify-center px-3 py-1 text-sm border border-info-300 rounded-md font-medium transition bg-info-100 text-info-700 hover:bg-info-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-info-300 dark:bg-slate-700 dark:border-slate-500 dark:text-info-400 dark:hover:border-info-600 dark:hover:bg-info-600 dark:hover:text-white dark:focus:ring-offset-slate-800">
                                View Details
                            </a>

                        </div>
                    </td>
                </template>

            </ResourceDataTable>
        </div>
    </div>
</template>