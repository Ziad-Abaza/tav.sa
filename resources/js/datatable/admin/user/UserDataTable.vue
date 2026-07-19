<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from "vue";
import axios from "axios";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import { BsThreeDotsVertical } from "@kalimahapps/vue-icons";

const props = defineProps({
    subdomain: { type: String, required: true },
});

const table = ref(null);
// Toggle loading state per row (prevent double-clicks)
// Must be reactive() not ref() — Vue tracks Set mutations only via reactive Proxy
const togglingActive = reactive(new Set());

// ─── Dropdown — teleported to <body> to escape table overflow clipping ─────────

const activeDropdown = ref(null);
const dropdownPos = ref({ top: 0, left: 0 });

function openDropdown(event, rowId) {
    if (activeDropdown.value === rowId) {
        activeDropdown.value = null;
        return;
    }
    const rect = event.currentTarget.getBoundingClientRect();
    dropdownPos.value = {
        top: rect.bottom + window.scrollY + 4,
        left: rect.right + window.scrollX,
    };
    activeDropdown.value = rowId;
}

function closeDropdown() {
    activeDropdown.value = null;
}

// Register sync — NEVER inside async callback; listener must exist from mount
onMounted(() => {
    window.addEventListener("user-table-refresh", onTableRefresh);
    document.addEventListener("mousedown", onDocumentMousedown);
});

onBeforeUnmount(() => {
    window.removeEventListener("user-table-refresh", onTableRefresh);
    document.removeEventListener("mousedown", onDocumentMousedown);
});

function onDocumentMousedown(e) {
    if (activeDropdown.value === null) return;
    if (!e.target.closest("[data-user-dropdown]")) {
        closeDropdown();
    }
}

// ─── Reload on Livewire actions ────────────────────────────────────────────────

function onTableRefresh() {
    table.value?.reload();
}

// ─── Livewire event dispatching ───────────────────────────────────────────────

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}


// ─── is_active toggle (interactive PATCH) ─────────────────────────────────────

async function toggleActive(row) {
    if (togglingActive.has(row.id)) return;

    togglingActive.add(row.id);

    try {
        const response = await axios.patch(`/admin/api/users/${row.id}/toggle-active`);
        table.value?.reload();
        const message = response.data?.message || "Updated successfully";
        showNotification(message, "success");
    } catch (e) {
        console.error("[UserDataTable] toggleActive error:", e);
        const errorMessage = e.response?.data?.message || "Something went wrong";
        showNotification(errorMessage, "danger");
    } finally {
        togglingActive.delete(row.id);
    }
}
</script>

<template>
    <div class="rounded-xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="users" storage-key="users_table" base-url="/admin/api">

                <!-- Toggle Override -->
                <template #active="{ row, value }">
                    <button
                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition duration-200 ease-in-out"
                        :class="value ? 'bg-primary-500' : 'bg-gray-200 dark:bg-slate-600'" :disabled="togglingActive.has(row.id)"
                        @click="toggleActive(row)">
                        <span
                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200 ease-in-out"
                            :class="value ? 'translate-x-4' : 'translate-x-0'" />
                    </button>
                </template>

                <!-- Actions column header -->
                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>

                <!-- Row actions — trigger button only; menu teleported to body -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle flex">
                        <div class="flex items-center justify-end">
                            <button data-user-dropdown
                                class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-white dark:hover:bg-slate-600"
                                @mousedown.stop="openDropdown($event, row.id)">
                                <BsThreeDotsVertical class="h-4 w-4" />
                            </button>
                        </div>
                    </td>
                </template>

                <!-- Empty state -->
                <template #empty>
                    <div class="flex flex-col items-center py-4 text-gray-400">
                        <svg class="mb-2 h-10 w-10 opacity-40" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                        </svg>
                        <p>No users found.</p>
                    </div>
                </template>

            </ResourceDataTable>
        </div>
    </div>

    <!-- Dropdown teleported to <body> — escapes table overflow clipping -->
    <Teleport to="body">
        <div v-if="activeDropdown !== null" data-user-dropdown
            class="fixed z-[9999] w-40 rounded-md border border-gray-200 bg-white shadow-lg dark:bg-slate-700 dark:border-gray-600" :style="{
                top: dropdownPos.top + 'px',
                left: dropdownPos.left - 160 + 'px',
            }">
            <!-- Open Builder -->
            <button class="block w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600" @click="dispatchLivewire('viewUser', { userId: activeDropdown });
            closeDropdown();">
                View
            </button>

            <!-- Edit metadata -->
            <button class="w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600" @click="
                dispatchLivewire('editUser', { userId: activeDropdown });
            closeDropdown();
            ">
                Edit
            </button>

            <!-- Delete -->
            <button class="w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50  dark:hover:bg-slate-600" @click="
                dispatchLivewire('confirmDelete', {
                    userId: activeDropdown,
                });
            closeDropdown();
            ">
                Delete
            </button>
        </div>
    </Teleport>
</template>