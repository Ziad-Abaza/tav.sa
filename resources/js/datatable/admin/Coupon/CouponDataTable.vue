<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import { BsThreeDotsVertical } from "@kalimahapps/vue-icons";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";
import { useTableFilters } from "@/composables/useTableFilters";

const table = ref(null);
const activeRowStatus = ref(false);

function onTableRefresh() {
    table.value?.reload();
}

const filterOptions = ref({
    type: [],
    is_active: [],
    expires: [],
});

const FILTER_KEYS = [
    "type",
    "is_active",
    "expires_at",
];

const {
    activeFilters,
    activeCount,
    setFilter,
    clearFilter,
    clearAll,
} = useTableFilters(FILTER_KEYS, "coupon_table_filters");


onMounted(() => {
    window.addEventListener("coupon-table-refresh", onTableRefresh);
    document.addEventListener("mousedown", onDocumentMousedown);

    axios
        .get(`/admin/api/coupon-filters`)
        .then(({ data }) => {
            filterOptions.value = data;
        })
        .catch((e) => {
            console.error("[CouponTable] Failed loading filters:", e);
        });
});

const filterDefs = computed(() => [
    {
        key: "is_active",
        label: "Status",
        options: filterOptions.value.is_active,
    },
    {
        key: "type",
        label: "Type",
        options: filterOptions.value.type,
    },
    {
        key: "expires_at",
        label: "Expired",
        options: filterOptions.value.expires_at,
    },
]);

onBeforeUnmount(() => {
    window.removeEventListener("coupon-table-refresh", onTableRefresh);
    document.removeEventListener("mousedown", onDocumentMousedown);
});

const activeDropdown = ref(null);
const dropdownPos = ref({ top: 0, left: 0 });

function openDropdown(event, row) {
    if (activeDropdown.value?.id === row.id) {
        activeDropdown.value = null;
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

    activeDropdown.value = row;   // ✅ store full row
    activeRowStatus.value = row.is_active;
}

function closeDropdown() {
    activeDropdown.value = null;
}

function onDocumentMousedown(e) {
    if (activeDropdown.value === null) return;
    if (!e.target.closest("[data-coupon-dropdown]")) {
        closeDropdown();
    }
}

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <ResourceDataTable ref="table" resource="coupon" :extra-params="activeFilters" storage-key="coupon_table"
                base-url="/admin/api">

                <template #type="{ value }">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium" :class="{
                        'bg-info-100 text-info-800 dark:bg-slate-800 dark:text-info-500 border dark:border-info-700': value === 'percentage',
                        'bg-success-100 text-success-800 dark:bg-slate-800 dark:text-success-500 border dark:border-success-700': value === 'fixed_amount',
                        'bg-gray-100 text-gray-800 dark:bg-gray-800/50 border dark:border-gray-500 dark:text-gray-300': !['percentage', 'fixed_amount'].includes(value),
                    }">
                        <span v-if="!['percentage', 'fixed_amount'].includes(value)"
                            class="h-1.5 w-1.5 bg-gray-400 rounded-full mr-1.5 inline-block" />
                        {{ value?.replace('_', ' ') }}
                    </span>
                </template>

                <template #toolbar-leading>
                    <DataTableFilters :filters="filterDefs" :active-filters="activeFilters" :active-count="activeCount"
                        @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />
                </template>

                <template #is_active="{ value }">
                    <span v-if="value === 'Active'"
                        class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-1 text-xs font-medium text-success-800 dark:bg-slate-800 border dark:border-success-700 dark:text-success-500 ">
                        Active
                    </span>

                    <span v-else
                        class="inline-flex items-center rounded-full bg-danger-100 px-2.5 py-1 text-xs font-medium text-danger-800 dark:bg-slate-800 border dark:border-danger-700 dark:text-danger-500">
                        Inactive
                    </span>
                </template>

                <template #header-trailing>
                    <th
                        class="h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Actions
                    </th>
                </template>

                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 align-middle">
                        <div class="flex items-center ">
                            <button data-coupon-dropdown
                                class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-white dark:hover:bg-slate-600"
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
                                d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
                        </svg>
                        <p>No coupon found.</p>
                    </div>
                </template>
            </ResourceDataTable>
        </div>
    </div>
    <Teleport to="body">
        <div v-if="activeDropdown !== null" data-coupon-dropdown
            class="fixed z-[9999] w-32 rounded-md border border-gray-200 bg-white shadow-lg dark:bg-slate-700 dark:border-gray-600" :style="{
                top: dropdownPos.top + 'px',
                left: dropdownPos.left + 'px',
                transform: 'translateX(-100%)'
            }">

            <button class="w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600" @click="
                dispatchLivewire('showUsageDetails', {
                    couponId: activeDropdown.id,
                });
            closeDropdown();
            ">
                View
            </button>

            <button class="w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600"
                v-if="activeDropdown?.can_edit" @click="
                    dispatchLivewire('toggleStatus', {
                        id: activeDropdown.id,
                    });
                closeDropdown();
                ">
                {{ activeRowStatus == "Inactive" ? 'Activate' : 'Deactivate' }}
            </button>

            <a :href="`/admin/coupons/${activeDropdown.id}/edit`"
                class="block w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-white dark:hover:bg-slate-600"
                v-if="activeDropdown?.can_edit" @click="closeDropdown">
                Edit
            </a>

            <!-- Delete -->
            <button class="w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-slate-600"
                v-if="activeDropdown?.can_delete" @click="
                    dispatchLivewire('confirmDelete', {
                        id: activeDropdown.id,
                    });
                closeDropdown();
                ">
                Delete
            </button>
        </div>
    </Teleport>
</template>
