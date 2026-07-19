<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import { BsThreeDotsVertical } from "@kalimahapps/vue-icons";

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

// ─── Dropdown ─────────────────────────────────────────────────────────────────

const activeDropdown = ref(null);
const activeRow = ref(null);
const dropdownPos = ref({ top: 0, left: 0 });

function openDropdown(event, row) {
    if (activeDropdown.value === row.id) {
        activeDropdown.value = null;
        activeRow.value = null;
        return;
    }
    const rect = event.currentTarget.getBoundingClientRect();
    const dropdownHeight = 180; // approximate dropdown height
    const spaceBelow = window.innerHeight - rect.bottom;

    let top;

    if (spaceBelow < dropdownHeight) {
        // open upward
        top = rect.top - dropdownHeight - 4;
    } else {
        // open downward
        top = rect.bottom + 4;
    }
    dropdownPos.value = {
        top: top,
        left: rect.right,
    };
    activeDropdown.value = row.id;
    activeRow.value = row;
}

function closeDropdown() {
    activeDropdown.value = null;
    activeRow.value = null;
}

onMounted(() => {
    window.addEventListener("tenant-table-refresh", onTableRefresh);
    document.addEventListener("mousedown", onDocumentMousedown);
});

onBeforeUnmount(() => {
    window.removeEventListener("tenant-table-refresh", onTableRefresh);
    document.removeEventListener("mousedown", onDocumentMousedown);
});

function onDocumentMousedown(e) {
    if (activeDropdown.value === null) return;
    if (!e.target.closest("[data-tenant-dropdown]")) {
        closeDropdown();
    }
}

// ─── Reload on Livewire events ─────────────────────────────────────────────────

function onTableRefresh() {
    table.value?.reload();
}

// ─── Livewire event dispatching ───────────────────────────────────────────────

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}
const statusDropdown = ref(null);
const statusRow = ref(null);
const statuses = ref({});

function openStatusDropdown(event, row) {
    const rect = event.currentTarget.getBoundingClientRect();

    const dropdownWidth = 176; // w-44 = 11rem = 176px

    dropdownPos.value = {
        top: rect.bottom + window.scrollY + 6,
        left:
            rect.left +
            window.scrollX +
            rect.width / 2 -
            dropdownWidth / 2,
    };

    statusRow.value = row;
    statusDropdown.value = row.id;
}

function changeStatus(newStatus) {
    axios.patch(`/admin/api/tenants/${statusRow.value.id}/status`, {
        status: newStatus
    }).then(() => {
        table.value.reload();
        statusDropdown.value = null;
    });
}

function statusClasses(status) {
    return {
        'bg-green-100 text-green-800 dark:bg-slate-700 dark:text-green-500 border dark:border-gray-600': status === 'active',
        'bg-yellow-100 text-yellow-800 dark:bg-slate-700 dark:text-yellow-500 border dark:border-gray-600': status === 'suspended',
        'bg-gray-100 text-gray-800 dark:bg-slate-700 dark:text-gray-400 border dark:border-gray-600': status === 'deactive',
        'bg-red-100 text-red-800 dark:bg-slate-700 dark:text-red-500 border dark:border-gray-600': status === 'expired',
        'bg-red-100 text-red-800 dark:bg-slate-700 dark:text-red-500 border dark:border-gray-600': status === 'pending_deletion',
    };
}

onMounted(() => {
    axios.get('/admin/api/tenants/status-options')
        .then(res => statuses.value = res.data.statuses);
});

</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="tenants" base-url="/admin/api" storage-key="admin_tenants_table">

                <template #status="{ value, row }">
                    <div class="relative">
                        <button @click="value !== 'pending_deletion' && openStatusDropdown($event, row)"
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium cursor-pointer "
                            :class="statusClasses(value)">
                            {{value.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}}
                            <svg v-if="value !== 'pending_deletion'" class="ml-1 h-3.5 w-3.5 text-current opacity-70"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </template>

                <!-- Actions column header -->
                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>

                <!-- Row actions -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <a v-if="row.email_verified_at === null" :href="`/admin/tenants/tenant/${row.id}`"
                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-primary-600 bg-primary-100 rounded hover:bg-primary-200 border border-primary-300 dark:bg-primary-900 dark:text-primary-200"
                                    :title="'Confirm Registration'" @click.prevent="
                                        dispatchLivewire('confirmTenantRegistration', { tenantId: row.id });
                                    ">
                                    Confirm
                                </a>

                                <a :href="`/admin/login-as/${row.user_id}`" v-if="row.can_login"
                                    class="inline-flex items-center justify-center px-3 py-1 text-sm border border-success-300 rounded-md font-medium disabled:opacity-50 disabled:pointer-events-none transition bg-success-100 text-success-800 hover:bg-success-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-success-300 dark:bg-slate-700 dark:border-slate-500 dark:text-success-400 dark:hover:border-success-600 dark:hover:bg-success-600 dark:hover:text-white dark:focus:ring-offset-slate-800">
                                    Login as Tenant
                                </a>
                            </div>
                            <button data-tenant-dropdown
                                class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 justify-end dark:hover:bg-slate-600 dark:text-white"
                                @mousedown.stop="openDropdown($event, row)">
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
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <p>No tenants found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="statusDropdown !== null && statusRow !== null"
            class="fixed inset-0 z-[9999] flex items-center justify-center">
            <!-- Blurred backdrop -->
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="statusDropdown = null"></div>

            <!-- Dropdown box -->
            <div
                class="relative w-80 rounded-xl border border-gray-200 bg-white dark:bg-slate-700 dark:border-gray-600 p-4 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            Tenant Status Details
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Static info about this tenant’s status.
                        </p>
                    </div>

                    <button
                        class="w-6 rounded-md p-1 text-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-600 dark:text-white"
                        @click="statusDropdown = null">
                        ✕
                    </button>
                </div>

                <button v-for="(label, key) in statuses" :key="key" @click="changeStatus(key)"
                    class="mb-2 flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left dark:border-gray-600 text-sm transition"
                    :class="{
                        'border-blue-500 bg-green-50 ring-1 ring-blue-500': key === statusDropdown,
                        'border-gray-200 hover:bg-gray-50 dark:hover:bg-slate-600': key !== statusDropdown
                    }">
                    <div class="flex items-center gap-2">
                        <!-- Status dot -->
                        <span class="h-2.5 w-2.5 rounded-full" :class="{
                            'bg-green-500': key === 'active',
                            'bg-gray-400': key === 'deactive',
                            'bg-yellow-400': key === 'suspended'
                        }"></span>

                        {{ label }}
                    </div>

                    <!-- Check icon -->
                    <span v-if="key === statusDropdown" class="text-blue-600">
                        ✓
                    </span>
                </button>
            </div>
        </div>
    </Teleport>

    <!-- Dropdown teleported to <body> — escapes table overflow clipping -->
    <Teleport to="body">
        <div v-if="activeDropdown !== null && activeRow !== null" data-tenant-dropdown
            class="fixed z-[9999] w-44 rounded-md border border-gray-200 bg-white shadow-lg dark:bg-slate-700 dark:border-gray-600"
            :style="{
                top: dropdownPos.top + 'px',
                left: dropdownPos.left + 'px',
                transform: 'translateX(-100%)'
            }">

            <!-- View -->
            <button
                class="w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-slate-600 dark:text-white"
                @click="
                    dispatchLivewire('viewTenant', { tenantId: activeDropdown });
                closeDropdown();
                ">
                View
            </button>

            <!-- Edit -->
            <button v-if="activeRow.can_edit"
                class="w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-slate-600 dark:text-white"
                @click="
                    dispatchLivewire('editTenant', { tenantId: activeDropdown });
                closeDropdown();
                ">
                Edit
            </button>

            <!-- Delete / Restore -->
            <button v-if="activeRow.deleted_date"
                class="w-full px-3 py-1.5 text-left text-sm text-success-600 hover:bg-success-50" @click="
                    dispatchLivewire('restoreTenant', { tenantId: activeDropdown });
                closeDropdown();
                ">
                Restore
            </button>
            <button v-else
                class="w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-slate-600"
                v-if="activeRow.can_delete" @click="
                    dispatchLivewire('confirmDelete', { tenantId: activeDropdown });
                closeDropdown();
                ">
                Delete
            </button>
        </div>
    </Teleport>
</template>
