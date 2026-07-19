<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from "vue";
import ResourceDataTable from "@/components/ui/data-table/ResourceDataTable.vue";
import DataTableFilters from "@/components/ui/data-table/DataTableFilters.vue";
import { useTableFilters } from "@/composables/useTableFilters";
import { BsPencilSquare, BsTrash } from "@kalimahapps/vue-icons";

const props = defineProps({
    subdomain: { type: String, required: true },
});

// Table ref — exposes reload(), collection, queryParams from ResourceDataTable
const table = ref(null);

function onTableRefresh() {
    table.value?.reload();
}

// ─── Filter options ──────────────────────────────────────────────────────────
const filterOptions = ref({ template_name: [], language: [], category: [], header_data_format: [], status: [], });

onMounted(() => {
    window.addEventListener("whatspp-template-table-refresh", onTableRefresh);

    // Fetch filter options async (separate concern)
    axios
        .get(`/${props.subdomain}/api/whatsapp-template-filters`)
        .then(({ data }) => {
            filterOptions.value = data;
        })
        .catch((e) => {
            console.error("[WhatsappTemplateDataTable] failed to load filter options:", e);
        });
});

onBeforeUnmount(() => {
    window.removeEventListener("whatspp-template-table-refresh", onTableRefresh);
});

const FILTER_KEYS = [
    "template_id",
    "language",
    "category",
    "header_data_format",
    "status",
];


// ─── Filters ─────────────────────────────────────────────────────────────────

const { activeFilters, activeCount, setFilter, clearFilter, clearAll } =
    useTableFilters(FILTER_KEYS, "whatsapp-template_table_filters");

const filterDefs = computed(() => [
    {
        key: "template_id",
        label: "Template Name",
        type: "select",
        options: filterOptions.value.templates,
    },
    {
        key: "language",
        label: "Language",
        type: "select",
        options: filterOptions.value.language,
    },
    {
        key: "category",
        label: "Category",
        type: "select",
        options: filterOptions.value.category,
    },
    {
        key: "header_data_format",
        label: "Template Type",
        type: "select",
        options: filterOptions.value.header_data_format,
    },
    {
        key: "status",
        label: "Status",
        type: "select",
        options: filterOptions.value.status,
    },
]);

// ─── Livewire event dispatching ───────────────────────────────────────────────

function editTemplate(row) {
    window.Livewire.dispatch('showEditPage', {
        templateId: row.id,
        templateType: row.template_type
    });
}

function dispatchLivewire(event, params = {}) {
    if (window.Livewire) {
        window.Livewire.dispatch(event, params);
    }
}

function showDeleteConfirmation(row) {
    dispatchLivewire("showDeleteConfirmation", {
        templateId: row.id,
        templateName: row.template_name,
        templateMetaId: row.template_id
    });
}

function truncate(text, length = 20) {
    if (!text) return ''
    return text.length > length
        ? text.substring(0, length) + '...'
        : text
}

</script>

<template>
    <div class="rounded-xl border dark:border-gray-600 shadow-sm">
        <div class="p-4 bg-white dark:bg-slate-800">
            <ResourceDataTable ref="table" resource="whatsapp-template" :subdomain="subdomain"
                storage-key="whatsapp_template_table" :extra-params="activeFilters">

                <!-- Category Badge -->
                <template #category="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-blue-100 text-blue-800 dark:text-blue-400 dark:bg-blue-900/20 border dark:border-blue-400': value === 'MARKETING',
                        'bg-green-100 text-green-800 dark:text-green-400 dark:bg-green-900/20 border dark:border-green-400': value === 'UTILITY',
                        'bg-purple-100 text-purple-800 dark:text-purple-400 dark:bg-purple-900/20 border dark:border-purple-400': value === 'AUTHENTICATION',
                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border dark:border-gray-400': !['MARKETING', 'UTILITY', 'AUTHENTICATION'].includes(value)
                    }">
                        {{ value === 'AUTHENTICATION' ? 'OTP' : value }}
                    </span>
                </template>

                <!-- Status Badge -->
                <template #status="{ value }">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="{
                        'bg-success-100 text-success-800 dark:text-success-400 dark:bg-success-900/20 border dark:border-success-400': value === 'APPROVED',
                        'bg-danger-100 text-danger-800 dark:text-danger-400 dark:bg-danger-900/20 border dark:border-danger-400': value === 'REJECTED',
                        'bg-warning-100 text-warning-800 dark:text-warning-400 dark:bg-warning-900/20 border dark:border-warning-400': value === 'PENDING',
                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border dark:border-gray-400': !['APPROVED', 'REJECTED', 'PENDING'].includes(value)
                    }">
                        {{ value }}
                    </span>
                </template>

                <template #toolbar-leading>
                    <DataTableFilters :filters="filterDefs" :active-filters="activeFilters" :active-count="activeCount"
                        @change="setFilter" @clear="clearFilter" @clear-all="clearAll" />
                </template>

                <template #body_data="{ row, value }">
                    <span v-if="row.body_data" class="cursor-pointer text-sm text-gray-800 dark:text-gray-300"
                        :title="value">
                        {{ truncate(row.body_data, 50) }}
                    </span>
                </template>

                <template #template_name="{ row, value }">
                    <span v-if="row.template_name" class="text-sm text-gray-800 dark:text-gray-300">
                        {{ truncate(row.template_name, 20) }}
                    </span>
                </template>


                <!-- Actions -->
                <template #row-trailing="{ row }">
                    <td class="px-4 py-2 text-right space-x-2">

                        <!-- Edit Icon -->
                        <button v-if="row.status === 'APPROVED' && row.can_edit" @click="editTemplate(row)"
                            class="inline-flex items-center px-2 py-1 text-sm font-medium text-blue-800 bg-blue-100 rounded shadow-sm dark:text-blue-500 dark:bg-slate-700 border dark:border-gray-600 hover:bg-blue-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 justify-center"
                            title="Edit">
                            <BsPencilSquare class="w-4 h-4" />
                        </button>

                        <!-- Delete Icon -->
                        <button v-if="row.can_delete" @click="showDeleteConfirmation(row)"
                            class="inline-flex items-center px-2 py-1 text-sm font-medium text-danger-500 bg-danger-100 rounded shadow-sm dark:text-red-500 dark:bg-slate-700 border dark:border-gray-600 hover:bg-danger-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600 justify-center"
                            title="Delete">
                            <BsTrash class="w-4 h-4" />
                        </button>

                    </td>
                </template>
            </ResourceDataTable>
        </div>
    </div>
</template>