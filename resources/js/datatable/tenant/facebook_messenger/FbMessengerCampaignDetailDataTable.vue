<script setup>
import { ref, onMounted } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

const props = defineProps({
    subdomain: { type: String, required: true },
    campaignId: { type: [Number, String], required: true },
});

const table = ref(null);

function exportCsv() {
    const params = table.value?.queryParams ?? {};

    const cleaned = Object.fromEntries(
        Object.entries({
            ...params,
            campaign_id: props.campaignId,
        }).filter(
            ([_, v]) =>
                v !== undefined && v !== null && v !== "" && v !== "undefined"
        )
    );

    const qs = new URLSearchParams(cleaned).toString();

    window.open(`/${props.subdomain}/api/fb-campaign-details/export?${qs}`);
}

function getStatusClass(status) {
    const statusNum = parseInt(status);
    if (statusNum === 2 || status === "sent") {
        return "bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300";
    } else if (statusNum === 1 || status === "pending") {
        return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300";
    } else if (statusNum === 0 || status === "failed") {
        return "bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300";
    }
    return "bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300";
}

function getStatusLabel(status) {
    const statusNum = parseInt(status);
    if (statusNum === 2 || status === "sent") return "Sent";
    if (statusNum === 1 || status === "pending") return "Pending";
    if (statusNum === 0 || status === "failed") return "Failed";
    return status;
}
</script>

<template>
    <div
        class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="p-4">
            <ResourceDataTable
                ref="table"
                resource="fb-campaign-details"
                :subdomain="subdomain"
                :extra-params="{ campaign_id: campaignId }"
                storage-key="fb_campaign_details_table"
            >
                <!-- Status badge -->
                <template #status="{ value }">
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                        :class="getStatusClass(value)"
                    >
                        {{ getStatusLabel(value) }}
                    </span>
                </template>

                <!-- Message Status badge -->
                <template #message_status="{ value }">
                    <span
                        v-if="value"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                        :class="{
                            'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300':
                                value === 'delivered' || value === 'read',
                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300':
                                value === 'sent',
                            'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300':
                                value === 'failed' || value === 'error',
                        }"
                    >
                        {{ value.replace("_", " ") }}
                    </span>
                    <span v-else class="text-gray-400">-</span>
                </template>

                <!-- Contact info slot -->
                <template #contact_name="{ row }">
                    <div class="flex flex-col">
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ row.contact_name || "-" }}
                        </span>
                        <span
                            v-if="row.facebook_psid"
                            class="text-xs text-gray-500 dark:text-gray-400"
                        >
                            PSID: {{ row.facebook_psid }}
                        </span>
                    </div>
                </template>

                <!-- Toolbar with export -->
                <template #toolbar-trailing>
                    <button
                        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2.5 text-sm font-medium text-gray-600 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        @click="exportCsv"
                    >
                        <svg
                            class="h-3.5 w-3.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                        >
                            <path
                                d="M12 5v14m0 0l-5-5m5 5l5-5"
                                stroke-width="2"
                            />
                        </svg>
                        Export
                    </button>
                </template>

                <!-- Empty state -->
                <template #empty>
                    <div class="flex flex-col items-center py-4 text-gray-400">
                        <svg
                            class="mb-2 h-10 w-10 opacity-40"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"
                            />
                        </svg>
                        <p>No recipients found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>
</template>
