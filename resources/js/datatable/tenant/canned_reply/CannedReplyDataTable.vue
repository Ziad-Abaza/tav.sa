<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";

const props = defineProps({
    subdomain: { type: String, required: true },
});

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

// Toggle loading state per row (prevent double-clicks)
// Must be reactive() not ref() — Vue tracks Set mutations only via reactive Proxy
const togglingPublic = reactive(new Set());

function onTableRefresh() {
    table.value?.reload();
}

onMounted(() => {
    window.addEventListener("canned-reply-table-refresh", onTableRefresh);
});

onBeforeUnmount(() => {
    window.removeEventListener("canned-reply-table-refresh", onTableRefresh);
});

function editCanned(id) {
    window.dispatchEvent(new CustomEvent("editCannedPage", {
        detail: { cannedId: id }
    }));
}

function deleteCanned(id) {
    window.dispatchEvent(new CustomEvent("confirmDelete", {
        detail: { cannedId: id }
    }));
}

// togglebutton

async function togglePublic(row) {
    if (togglingPublic.has(row.id)) return;

    togglingPublic.add(row.id);

    try {

        const response = await axios.patch(
            `/${props.subdomain}/api/canned-reply/${row.id}/toggle-public`,
        );

        table.value?.reload();

        const message = response.data?.message || "Updated successfully";
        showNotification(message, "success");

    } catch (e) {

        console.error("[CannedReplyDataTable] togglingPublic error:", e);
        const errorMessage = e.response?.data?.message || "Something went wrong";
        showNotification(errorMessage, "danger");

    } finally {
        togglingPublic.delete(row.id);
    }
}
function truncate(text, length = 20) {
    if (!text) return ''
    return text.length > length
        ? text.substring(0, length) + '...'
        : text
}


</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="canned-reply" :subdomain="subdomain"
                storage-key="canned_reply_table">

                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 text-right space-x-2">

                        <button
                            class="inline-flex items-center px-3 py-1 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-500"
                            @click="editCanned(row.id)" v-if="row.can_edit">
                            Edit
                        </button>

                        <button
                            class="inline-flex items-center px-3 py-1 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-500"
                            @click="deleteCanned(row.id)" v-if="row.can_delete">
                            Delete
                        </button>

                    </td>
                </template>

                <template #description="{ row, value }">
                    <span v-if="row.description" class="text-sm text-gray-800 dark:text-gray-300">
                        {{ truncate(row.description, 30) }}
                    </span>
                </template>

                <!-- is_active toggle — interactive PATCH, overrides ToggleCell -->
                <template #public="{ row, value }">
                    <button
                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="value ? 'bg-primary-500' : 'bg-gray-200 dark:bg-slate-600'" :disabled="togglingPublic.has(row.id)"
                        role="switch" :aria-checked="String(value)" @click="togglePublic(row)">
                        <span
                            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            :class="value ? 'translate-x-4' : 'translate-x-0'" />
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
                        <p>No Canned reply found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>

</template>
