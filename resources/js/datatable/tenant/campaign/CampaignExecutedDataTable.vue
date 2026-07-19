<script setup>
import { ref, onMounted } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

const props = defineProps({
    subdomain: { type: String, required: true },
    campaignId: { type: Number, default: null },
});

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

function exportCsv() {
    const params = table.value?.queryParams ?? {}

    const cleaned = Object.fromEntries(
        Object.entries({
            ...params,
            campaign_id: props.campaignId,
        }).filter(([_, v]) =>
            v !== undefined &&
            v !== null &&
            v !== '' &&
            v !== 'undefined'
        )
    )

    const qs = new URLSearchParams(cleaned).toString()

    window.open(`/${props.subdomain}/api/campaign-executed/export?${qs}`)
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="campaign-executed" :subdomain="subdomain"
                :extra-params="{ campaign_id: campaignId }" storage-key="campaign_executed_table">

                  <!-- Status badge -->
                <template #message_status="{ value }">
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                        :class="{
                            'bg-amber-100 text-amber-700': value === 'pending',
                             'bg-danger-100 text-danger-700': value === 'failed',
                            'bg-primary-100 text-primary-700': value === 'sent',
                        }"
                    >
                        {{ value.replace('_', ' ') }}
                    </span>
                </template>


                <template #toolbar-trailing>
                    <button
                        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2.5 text-sm font-medium text-gray-600 shadow-sm hover:bg-gray-50"
                        @click="exportCsv">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M12 5v14m0 0l-5-5m5 5l5-5" stroke-width="2" />
                        </svg>
                        Export
                    </button>
                </template>

                <!-- Empty state -->
                <template #empty>
                    <div class="flex flex-col items-center py-4 text-gray-400">
                        <svg class="mb-2 h-10 w-10 opacity-40" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                        </svg>
                        <p>No Details found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>

</template>
