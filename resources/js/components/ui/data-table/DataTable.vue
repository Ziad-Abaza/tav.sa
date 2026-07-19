<script setup>
/**
 * Reusable async DataTable component.
 * Ported from wm-saas-v2 (originally adapted from Concord CRM AsyncTable).
 * Uses inline SVG icons instead of lucide-vue-next for zero extra deps.
 *
 * Usage:
 *   <DataTable endpoint="/api/contacts" :columns="columns">
 *     <template #status="{ row, value }">
 *       <badge>{{ value }}</badge>
 *     </template>
 *   </DataTable>
 */
import { computed, provide, ref } from "vue";
import { useDataTable } from "@app/composables/useDataTable";
import { useTableDensity, DENSITY_LABELS } from "@app/composables/useTableDensity";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@app/components/ui/table/index.js";
import DataTablePagination from "./DataTablePagination.vue";
import DataTableSearch from "./DataTableSearch.vue";
import DataTableSkeleton from "./DataTableSkeleton.vue";

const isMobileMenuOpen = ref(false);
const props = defineProps({
    endpoint: { type: String, required: true },
    columns: { type: Array, required: true },
    searchable: { type: Boolean, default: true },
    extraParams: { type: Object, default: () => ({}) },
    /** Optional: array of column keys to show. null = show all (backward compat). */
    visibleColumns: { type: Array, default: null },
    /** Sticky header — keeps <thead> visible while scrolling. Requires maxHeight. */
    sticky: { type: Boolean, default: false },
    /** Max height for vertical scroll when sticky is true. E.g. "600px" or "70vh". */
    maxHeight: { type: String, default: "600px" },
    /** Freeze the first data column (left-pinned). */
    freezeFirstColumn: { type: Boolean, default: false },
    /** Storage key for persisting per-page setting in localStorage. */
    storageKey: { type: String, default: null },
});

const emit = defineEmits(["dataLoaded"]);

const {
    collection,
    isLoading,
    sort,
    direction,
    queryParams,
    setSort,
    setSearch,
    setPage,
    setPerPage,
    reload,
    updateRowData,
} = useDataTable({
    endpoint: props.endpoint,
    extraParams: props.extraParams,
    storageKey: props.storageKey,
});

const activeColumns = computed(() => {
    if (!props.visibleColumns) return props.columns;
    return props.columns.filter((c) => props.visibleColumns.includes(c.key));
});

// Density — provide to all TableCell children via inject
const { density, densityClass, cycleDensity } =
    useTableDensity("datatable_density");
provide("tableDensityClass", densityClass);

defineExpose({ reload, collection, queryParams, updateRowData });
</script>

<template>
    <!-- Toolbar -->
    <div class="flex items-center justify-between gap-3 py-2">
        <!-- Left side: optional leading slot (e.g. filters) -->
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <!-- Optional leading slot — filters button + chips live here -->
            <slot name="toolbar-leading" />
        </div>

        <!-- Right side -->
        <div class="flex flex-shrink-0 items-center gap-2">
            <!-- Desktop Controls -->
            <div class="hidden md:flex items-center gap-2">
                <slot name="toolbar-trailing" />

                <div
                    class="inline-flex divide-x divide-gray-200 rounded-md border border-gray-200 dark:border-gray-600 dark:divide-gray-600 dark:bg-slate-800 bg-white shadow-sm"
                >
                    <slot name="toolbar-columns" />

                    <button
                        type="button"
                        class="inline-flex h-8 items-center gap-1.5 rounded-r-md px-2.5 text-sm font-medium text-gray-600 dark:text-white hover:bg-gray-50 hover:dark:bg-slate-700"
                        :title="`Row density: ${DENSITY_LABELS[density]}`"
                        @click="cycleDensity"
                    >
                        <svg
                            class="h-3.5 w-3.5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                        {{ DENSITY_LABELS[density] }}
                    </button>
                </div>

                <DataTableSearch
                    v-if="searchable"
                    :disabled="isLoading"
                    @search="setSearch"
                />
            </div>

            <!-- Mobile 3 Dots -->
            <div class="relative md:hidden">
                <button
                    @click="isMobileMenuOpen = !isMobileMenuOpen"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 bg-white shadow-sm"
                >
                    <!-- 3 dots icon -->
                    <svg
                        class="h-5 w-5 text-gray-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm0 5.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3z"
                        />
                    </svg>
                </button>

                <div
                    v-if="isMobileMenuOpen"
                    class="absolute right-0 z-50 mt-2 w-screen max-w-[65vw] sm:w-72 rounded-lg border border-gray-200 bg-white shadow-xl"
                >
                    <div class="p-3 space-y-2">
                        <!-- Export / Trailing -->
                        <div class="w-full">
                            <slot name="toolbar-trailing" />
                        </div>

                        <!-- Columns -->
                        <div class="w-full">
                            <slot name="toolbar-columns" />
                        </div>

                        <!-- Density -->
                        <button
                            type="button"
                            class="w-full flex items-center justify-between rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            @click="cycleDensity"
                        >
                            <span>Density</span>
                            <span class="text-xs text-gray-500">
                                {{ DENSITY_LABELS[density] }}
                            </span>
                        </button>

                        <!-- Search -->
                        <div class="w-full">
                            <DataTableSearch
                                v-if="searchable"
                                class="w-full"
                                :disabled="isLoading"
                                @search="setSearch"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table wrapper — overlay spinner on reload, skeleton on first load -->
    <div class="relative rounded-md bg-white shadow-sm ">
        <!-- Reload overlay: only when data already exists (subsequent loads) -->
        <div
            v-if="isLoading && !collection.isEmpty()"
            class="absolute inset-0 z-10 flex items-center justify-center rounded-md bg-white/60 backdrop-blur-sm dark:bg-slate-700"
        >
            <div
                class="h-6 w-6 animate-spin rounded-full border-2 border-blue-500 border-t-transparent"
            />
        </div>

        <Table :max-height="sticky ? maxHeight : null">
            <TableHeader :sticky="sticky">
                <TableRow>
                    <!-- Optional leading slot for checkboxes etc -->
                    <slot name="header-leading" />

                    <TableHead
                        v-for="(col, colIdx) in activeColumns"
                        :key="col.key"
                        :frozen="freezeFirstColumn && colIdx === 0"
                        :class="[
                            col.class,
                            col.sortable &&
                                'cursor-pointer select-none hover:bg-gray-50 dark:hover:bg-slate-700',
                        ]"
                        @click="col.sortable ? setSort(col.key) : undefined"
                    >
                        <div class="flex items-center gap-1">
                            {{ col.label }}
                            <template v-if="col.sortable">
                                <!-- Sort ascending -->
                                <svg
                                    v-if="
                                        sort === col.key && direction === 'asc'
                                    "
                                    class="h-3.5 w-3.5 text-blue-500"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 15l7-7 7 7"
                                    />
                                </svg>
                                <!-- Sort descending -->
                                <svg
                                    v-else-if="
                                        sort === col.key && direction === 'desc'
                                    "
                                    class="h-3.5 w-3.5 text-blue-500"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                                <!-- Unsorted -->
                                <svg
                                    v-else
                                    class="h-3.5 w-3.5 opacity-30"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"
                                    />
                                </svg>
                            </template>
                        </div>
                    </TableHead>

                    <!-- Optional trailing slot for actions column header -->
                    <slot name="header-trailing" />
                </TableRow>
            </TableHeader>

            <!-- Skeleton: first load only (loading + no data yet) -->
            <DataTableSkeleton
                v-if="isLoading && collection.isEmpty()"
                :columns="activeColumns.length + 2"
            />

            <TableBody v-else>
                <!-- Empty state -->
                <template v-if="collection.isEmpty()">
                    <TableRow>
                        <TableCell
                            :colspan="activeColumns.length + 2"
                            class="py-10 text-center text-gray-400"
                        >
                            <slot name="empty">No records found.</slot>
                        </TableCell>
                    </TableRow>
                </template>

                <!-- Data rows -->
                <template v-for="row in collection.items" :key="row.id">
                    <slot name="before-row" :row="row" />

                    <TableRow>
                        <!-- Optional leading cell slot (e.g. checkbox) -->
                        <slot name="row-leading" :row="row" />

                        <TableCell
                            v-for="(col, colIdx) in activeColumns"
                            :key="col.key"
                            :frozen="freezeFirstColumn && colIdx === 0"
                            :class="col.class"
                        >
                            <slot
                                :name="col.key"
                                :row="row"
                                :value="row[col.key]"
                            >
                                {{
                                    col.formatter
                                        ? col.formatter(row[col.key], row)
                                        : row[col.key]
                                }}
                            </slot>
                        </TableCell>

                        <!-- Optional trailing cell slot (e.g. row actions) -->
                        <slot name="row-trailing" :row="row" />
                    </TableRow>

                    <slot name="after-row" :row="row" />
                </template>
            </TableBody>
        </Table>
    </div>

    <!-- Pagination -->
    <DataTablePagination
        v-if="collection.hasPagination"
        class="mt-3"
        :collection="collection"
        :loading="isLoading"
        :per-page="collection.perPage"
        :per-page-options="collection.perPageOptions"
        @go-to-page="setPage"
        @go-to-previous="() => setPage(collection.currentPage - 1)"
        @go-to-next="() => setPage(collection.currentPage + 1)"
        @change-per-page="setPerPage"
    />
</template>
