<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue"
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue"

const props = defineProps({
    subdomain: { type: String, required: true },
})

const table = ref(null)

function onTableRefresh() {
    table.value?.reload();
}

onMounted(() => {
    window.addEventListener("pg:eventRefresh-webhook-logs-table", onTableRefresh);
});

onBeforeUnmount(() => {
    window.removeEventListener("pg:eventRefresh-webhook-logs-table", onTableRefresh);
});

// ─── permission on bulk delete ───────────────────────────────────────────────


const canDelete = computed(() => {
    const items = table?.value?.collection?.items ?? []
    return items.length ? items[0].can_delete : false
})

// ─── Livewire event dispatching ───────────────────────────────────────────────

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}

// ─── Bulk selection ──────────────────────────────────────────────────────────

const selectedIds = ref([]);
const isAllSelected = ref(false);

// ─── Bulk selection helpers ───────────────────────────────────────────────────

function toggleSelectAll(items) {
    if (isAllSelected.value) {
        selectedIds.value = [];
        isAllSelected.value = false;
    } else {
        selectedIds.value = items.map((r) => r.id);
        isAllSelected.value = true;
    }
}

function toggleSelectRow(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
}

function isSelected(id) {
    return selectedIds.value.includes(id);
}

function clearSelection() {
    selectedIds.value = [];
    isAllSelected.value = false;
}

function bulkDelete() {
    dispatchLivewire("confirmDelete", {
        logId: selectedIds.value,
    });

    clearSelection();
}

function confirmDelete(id) {
    dispatchLivewire("confirmDelete", { logId: id });
}

</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="webhook-logs" :subdomain="subdomain"
                storage-key="webhook-logs_table">

                <!-- Clear Log button -->
                <template #toolbar-trailing>
                    <button type="button" v-if="canDelete"
                        class="inline-flex h-8 items-center gap-1.5 rounded-md 
                        bg-red-600 px-2.5 text-sm font-medium text-white 
                        shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1" title="Clear Logs"
                        @click="bulkDelete">
                        <svg class="h-3.5 w-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6 7h12M9 7V4h6v3m-7 4v6m4-6v6m4-6v6M5 7h14l-1 12a2 2 0 01-2 2H8a2 2 0 01-2-2L5 7z" />
                        </svg>
                        Clear Logs
                    </button>
                </template>

                <template #send_status="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': value === 'sent',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': value === 'failed',
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': value === 'pending',
                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200': !['sent', 'failed', 'pending'].includes(value)
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1) }}
                    </span>
                </template>

                <template #delivery_status="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': value === 'sent',
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': value === 'delivered',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': value === 'failed',
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': value === 'read',
                        'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200': !['sent', 'delivered', 'failed', 'pending'].includes(value)
                    }">
                        {{ value?.charAt(0).toUpperCase() + value?.slice(1) }}
                    </span>
                </template>


                <!-- Toolbar leading: bulk bar (when selected) OR filters -->
                <template #toolbar-leading>
                    <transition enter-active-class="transition duration-150 ease-out"
                        enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-1">
                        <div v-if="selectedIds.length" class="flex items-center gap-2">
                            <span class="text-sm font-medium text-blue-700">
                                {{ selectedIds.length }} selected
                            </span>
                            <div class="h-4 w-px bg-gray-300" />
                            <button v-if="canDelete"
                                class="inline-flex h-8 items-center rounded-md bg-danger-600 px-3 text-sm font-medium text-white shadow-sm hover:bg-danger-700"
                                @click="bulkDelete">
                                Delete
                            </button>
                            <button
                                class="inline-flex h-8 items-center rounded-md px-2 text-sm text-gray-400 hover:text-gray-600"
                                title="Clear selection" @click="clearSelection">
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </transition>
                </template>

                <!-- Checkbox column header -->
                <template #header-leading>
                    <th v-if="canDelete" class="h-10 w-10 px-4 text-left align-middle">
                        <input type="checkbox" :checked="isAllSelected"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 dark:border-gray-600" @change="
                                () =>
                                    toggleSelectAll(
                                        table?.collection?.items ?? [],
                                    )
                            " />
                    </th>
                </template>

                <!-- Checkbox cell per row -->
                <template #row-leading="{ row }">
                    <td v-if="canDelete" class="w-10 px-4 py-2 align-middle">
                        <input type="checkbox" :checked="isSelected(row.id)"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 dark:border-gray-600"
                            @change="() => toggleSelectRow(row.id)" />
                    </td>
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
                            <a :href="`/${subdomain}/webhook-logs/${row.id}`"
                                class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-primary-600 shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 justify-center rounded-md">
                                View
                            </a>

                            <!-- Download (Only if Paid) -->
                            <button v-if="row.can_delete" @click="confirmDelete(row.id)"
                                class="inline-flex items-center gap-2 px-3 py-1 text-sm font-medium text-white bg-danger-600 shadow-sm hover:bg-danger-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600 justify-center rounded-md">
                                Delete
                            </button>

                        </div>
                    </td>
                </template>
            </ResourceDataTable>
        </div>
    </div>
</template>