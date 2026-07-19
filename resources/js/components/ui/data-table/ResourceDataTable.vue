<script setup>
/**
 * ResourceDataTable — generic, backend-driven table component.
 *
 * Column definitions are fetched from the backend via GET /{subdomain}/api/{resource}/table/settings.
 * This means adding a new table only requires:
 *   - A PHP *Table class (extends BaseTable) defining columns
 *   - A backend controller for the data endpoint
 *   - This component in a thin wrapper Vue component with only slot overrides
 *
 * Cell rendering priority (highest wins):
 *   1. Named slot from parent (e.g. <template #name="{ row }">)
 *   2. Component map (column.component → imported Vue component)
 *   3. DataTable default: plain text (row[col.key])
 *
 * Usage:
 *   <ResourceDataTable resource="contacts" :subdomain="subdomain" :extra-params="filters">
 *     <template #toolbar-leading> ... </template>
 *     <template #name="{ row }"> ... </template>   ← overrides component map
 *   </ResourceDataTable>
 *
 *   Admin (no subdomain): pass baseUrl="/admin/api" instead of subdomain
 *   <ResourceDataTable resource="tenants" base-url="/admin/api">
 */
import { ref, computed, watch, provide, onMounted, onUnmounted } from 'vue'
import { useTableSettings } from '@app/composables/useTableSettings'
import { useColumnVisibility } from '@app/composables/useColumnVisibility'
import DataTable from './DataTable.vue'
import DataTableColumnPicker from './DataTableColumnPicker.vue'
import DataTableSkeleton from './DataTableSkeleton.vue'
import InlineEditCell from './InlineEditCell.vue'

// ─── Cell component map ───────────────────────────────────────────────────────
// Add new cell components here as you create them. Static imports only — no dynamic loading.
import BadgeCell from '@app/datatable/cells/BadgeCell.vue'
import StatusBadgeCell from '@app/datatable/cells/StatusBadgeCell.vue'
import AvatarCell from '@app/datatable/cells/AvatarCell.vue'
import ToggleCell from '@app/datatable/cells/ToggleCell.vue'
import DateCell from '@app/datatable/cells/DateCell.vue'
import BooleanCell from '@app/datatable/cells/BooleanCell.vue'
import LinkCell from '@app/datatable/cells/LinkCell.vue'

const CELL_COMPONENTS = {
    BadgeCell,
    StatusBadgeCell,
    AvatarCell,
    ToggleCell,
    DateCell,
    BooleanCell,
    LinkCell,
}

// ─── Props ────────────────────────────────────────────────────────────────────

const props = defineProps({
    /** Resource slug, e.g. 'contacts', 'campaigns'. Must match TableSettingsController registry. */
    resource: { type: String, required: true },
    /** Tenant subdomain used to build API URLs. Required unless baseUrl is provided. */
    subdomain: { type: String, default: null },
    /**
     * Explicit API base URL. When provided, subdomain is ignored.
     * Use for non-tenant contexts (e.g. admin panel): base-url="/admin/api"
     * Tenant default is computed as "/{subdomain}/api".
     */
    baseUrl: { type: String, default: null },
    /** Reactive filter/extra params forwarded to DataTable (triggers auto-reload on change). */
    extraParams: { type: Object, default: () => ({}) },
    /** Override sticky from backend settings. */
    sticky: { type: Boolean, default: undefined },
    /** Override max-height from backend settings. */
    maxHeight: { type: String, default: undefined },
    /** Storage key for persisting per-page setting in localStorage. */
    
    storageKey: { type: String, default: null },
    /**
     * Inline-editable fields map. Keys are column keys; values are field configs.
     *   { type: 'select', options: [{value, label}], field: 'status_id', valueFrom: 'status.id' }
     *   { type: 'text' }
     * null = no inline editing.
     */
    
    editableFields: { type: Object, default: null },
    /**
     * Auto-refresh interval. Accepts seconds as a Number or a human string:
     *   "30s" → 30 s | "1m" → 60 s | "2m" → 120 s | "90s" → 90 s
     * null / 0 = disabled.
     */
    autoRefresh: { type: [Number, String], default: null },
})

// ─── Parse autoRefresh prop value → seconds (Number) ─────────────────────────

function parseRefreshInterval(val) {
    if (!val) return null
    if (typeof val === 'number') return val > 0 ? val : null
    const str = String(val).trim()
    const mMatch = str.match(/^(\d+(?:\.\d+)?)m$/)
    if (mMatch) return Math.round(parseFloat(mMatch[1]) * 60)
    const sMatch = str.match(/^(\d+(?:\.\d+)?)s$/)
    if (sMatch) return Math.round(parseFloat(sMatch[1]))
    const plain = parseFloat(str)
    return isNaN(plain) || plain <= 0 ? null : Math.round(plain)
}

// ─── Resolve API base URL ─────────────────────────────────────────────────────
// baseUrl prop wins; falls back to /{subdomain}/api for tenant context.
//const apiBase = props.baseUrl ?? (props.subdomain ? `/${props.subdomain}/api` : '')
const apiBase = (props.baseUrl ?? (props.subdomain ? `/${props.subdomain}/api` : ''))
    .replace(/\/+$/, '') // ✅ remove trailing slash

    const resourcePath = props.resource.replace(/^\/+/, '') // ✅ remove leading slash

// ─── Provide subdomain for cell components (e.g. LinkCell) ───────────────────
provide('subdomain', computed(() => props.subdomain))

// ─── Settings fetch ───────────────────────────────────────────────────────────

const { settings, isReady, error } = useTableSettings(props.resource, apiBase)

// ─── Column definitions (derived from settings) ───────────────────────────────

/**
 * Flat column array for DataTable — enriched with resolved cell component reference.
 * DataTable only sees { key, label, sortable, class } — _component/_primary/_hidden
 * are internal ResourceDataTable concerns (prefixed _ to avoid conflicts).
 */
const columns = computed(() => {
    if (!settings.value) return []
    return settings.value.columns.map(col => ({
        key: col.key,
        label: col.label,
        sortable: col.sortable,
        class: col.width ? `min-w-[${col.width}]` : (col.class ?? ''),
        // Internal: resolved Vue component object or null
        _component: col.component ? (CELL_COMPONENTS[col.component] ?? null) : null,
        _meta: col.meta ?? null,
        _primary: col.primary,
        _hidden: col.hidden,
    }))
})

/** Keys that are always visible (primary columns) — not toggleable. */
const requiredKeys = computed(() => columns.value.filter(c => c._primary).map(c => c.key))

/** Columns that have a mapped cell component — need dynamic slot injection. */
const componentColumns = computed(() => columns.value.filter(c => c._component !== null))

/**
 * Union of component columns + editable-only columns (no component but inline-editable).
 * These all need a dynamic slot injected so InlineEditCell can wrap them.
 */
const managedColumns = computed(() => {
    const editable = Object.keys(props.editableFields ?? {})
    if (!editable.length) return componentColumns.value
    const withComponent = new Set(componentColumns.value.map(c => c.key))
    const editableOnly = columns.value.filter(c => editable.includes(c.key) && !withComponent.has(c.key))
    return [...componentColumns.value, ...editableOnly]
})

// ─── Column visibility ────────────────────────────────────────────────────────

const storageKey = computed(() => `${props.resource}_table_columns`)
const visibilityReady = ref(false)
const visibleKeys = ref(new Set())
const visibleColumns = ref([])

let _toggleColumn = null

watch(isReady, (ready) => {
    if (!ready || visibilityReady.value) return

    const allCols = columns.value
    const key = storageKey.value

    // Initialise useColumnVisibility with all columns + primary = required
    const vis = useColumnVisibility(allCols, key, requiredKeys.value)

    // Seed hidden-by-default columns: if no stored state exists, hide columns
    // marked hidden=true in PHP (Column::hidden()). This only runs on first visit.
    if (localStorage.getItem(key) === null) {
        const defaultVisible = new Set(allCols.filter(c => !c._hidden).map(c => c.key))
        // Always include primary (required) columns
        requiredKeys.value.forEach(k => defaultVisible.add(k))
        vis.visibleKeys.value = defaultVisible
        // Don't write to storage yet — let user toggle to persist their preference
    }

    // Bind to local refs so template can access them
    visibleKeys.value = vis.visibleKeys.value
    visibleColumns.value = vis.visibleColumns.value
    _toggleColumn = vis.toggleColumn

    // Keep visibleKeys in sync when vis.visibleKeys changes (after toggleColumn calls)
    watch(vis.visibleKeys, (newSet) => {
        visibleKeys.value = newSet
    })
    watch(vis.visibleColumns, (newCols) => {
        visibleColumns.value = newCols
    })

    visibilityReady.value = true
}, { immediate: true })

function toggleColumn(key) {
    if (_toggleColumn) _toggleColumn(key)
}

// ─── DataTable props (derived from settings + prop overrides) ─────────────────

const endpoint = computed(() =>
    settings.value ? `${apiBase}/${resourcePath}` : null
)

const tableSticky = computed(() =>
    props.sticky !== undefined ? props.sticky : (settings.value?.sticky ?? false)
)

const tableMaxHeight = computed(() =>
    props.maxHeight !== undefined ? props.maxHeight : (settings.value?.max_height ?? '600px')
)

// ─── Auto-refresh ─────────────────────────────────────────────────────────────

const refreshSeconds = computed(() => parseRefreshInterval(props.autoRefresh))
const countdown = ref(null)
let _refreshTimer = null

function stopAutoRefresh() {
    if (_refreshTimer) {
        clearInterval(_refreshTimer)
        _refreshTimer = null
    }
    countdown.value = null
}

function startAutoRefresh(seconds) {
    stopAutoRefresh()
    if (!seconds || seconds <= 0) return
    countdown.value = seconds
    _refreshTimer = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0) {
            countdown.value = seconds
            tableRef.value?.reload()
        }
    }, 1000)
}

onMounted(() => startAutoRefresh(refreshSeconds.value))
onUnmounted(() => stopAutoRefresh())

watch(refreshSeconds, (val) => startAutoRefresh(val))

// ─── Expose (forward DataTable API to parent) ─────────────────────────────────

const tableRef = ref(null)

defineExpose({
    reload: () => tableRef.value?.reload(),
    updateRowData: (id, data) => tableRef.value?.updateRowData(id, data),
    collection: computed(() => tableRef.value?.collection),
    queryParams: computed(() => tableRef.value?.queryParams),
})

/** Called by InlineEditCell after a successful save — updates the row instantly. */
function onRowUpdated(row) {
    tableRef.value?.updateRowData(row.id, row)
}
</script>

<template>
    <!-- Loading: settings not yet fetched -->
    <div v-if="!isReady" class="rounded-xl border border-gray-200 bg-white dark:bg-slate-700 dark:border-gray-600 p-4 shadow-sm">
        <div class="mb-3 flex items-center gap-3">
            <div class="h-8 w-28 animate-pulse rounded-md bg-gray-100" />
            <div class="h-8 w-20 animate-pulse rounded-md bg-gray-100" />
            <div class="ml-auto h-8 w-32 animate-pulse rounded-md bg-gray-100" />
        </div>
        <DataTableSkeleton :columns="5" />
    </div>

    <!-- Error: settings fetch failed -->
    <div
        v-else-if="error"
        class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 shadow-sm"
    >
        {{ error }}
    </div>

    <!-- Main table — only after settings loaded and visibility initialised -->
    <DataTable
        v-else-if="endpoint && visibilityReady"
        ref="tableRef"
        :endpoint="endpoint"
        :columns="columns"
        :visible-columns="visibleColumns.map(c => c.key)"
        :extra-params="extraParams"
        :searchable="settings.searchable"
        :sticky="tableSticky"
        :max-height="tableMaxHeight"
        :storage-key="storageKey"
    >
        <!--
            Forward ALL parent slots through to DataTable.
            Named column slots from the parent override the component map below.
        -->
        <template v-for="(_, name) in $slots" :key="name" #[name]="slotProps">
            <slot :name="name" v-bind="slotProps ?? {}" />
        </template>

        <!--
            Column picker — always wired internally, no parent setup needed.
            Placed in #toolbar-columns slot of DataTable (inside the pill group).
        -->
        <template #toolbar-columns>
            <!-- Allow parent to override toolbar-columns entirely if needed -->
            <slot name="toolbar-columns">
                <DataTableColumnPicker
                    :columns="columns"
                    :visible-keys="visibleKeys"
                    :required-keys="requiredKeys"
                    @toggle="toggleColumn"
                />
            </slot>
        </template>

        <!--
            Auto-refresh countdown ring injected before (or after) any parent
            toolbar-trailing content. SVG ring depletes as the timer counts down.
        -->
        <template #toolbar-trailing>
            <slot name="toolbar-trailing" />

            <div
                v-if="refreshSeconds && countdown !== null"
                class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                :title="`Auto-refresh in ${countdown}s`"
            >
                <!-- Depleting ring: starts full, empties as countdown approaches 0 -->
                <svg class="h-4 w-4 -rotate-90" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="8" stroke="#e5e7eb" stroke-width="2.5" />
                    <circle
                        cx="12" cy="12" r="8"
                        stroke="#3b82f6"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        :stroke-dasharray="50.27"
                        :stroke-dashoffset="50.27 * (1 - countdown / refreshSeconds)"
                        style="transition: stroke-dashoffset 0.9s linear"
                    />
                </svg>
                <span>{{ countdown }}s</span>
            </div>
        </template>

        <!--
            Dynamic cell rendering — handles columns with a component map AND
            columns that are inline-editable (even without a component).
            InlineEditCell always wraps editable columns so parent-provided named
            slots still get the pencil/popover behaviour (slot content goes inside
            InlineEditCell as the display value, not around it).
        -->
        <template
            v-for="col in managedColumns"
            :key="'cell-' + col.key"
            #[col.key]="{ row, value }"
        >
            <!-- Editable: InlineEditCell wraps whatever renders the cell value -->
            <InlineEditCell
                v-if="editableFields?.[col.key]"
                :row="row"
                :value="value"
                :field="col.key"
                :config="editableFields[col.key]"
                :endpoint="`${apiBase}/${resource}/${row.id}/field`"
                :extra-params="extraParams"
                @saved="onRowUpdated"
            >
                <!-- Display content: parent slot wins, then component map, then plain text -->
                <slot :name="col.key" :row="row" :value="value">
                    <component v-if="col._component" :is="col._component" :value="value" :row="row" :meta="col._meta" />
                    <span v-else>{{ value ?? '—' }}</span>
                </slot>
            </InlineEditCell>

            <!-- Non-editable: forward parent slot or component map -->
            <slot v-else :name="col.key" :row="row" :value="value">
                <component
                    v-if="col._component"
                    :is="col._component"
                    :value="value"
                    :row="row"
                    :meta="col._meta"
                />
            </slot>
        </template>
    </DataTable>
</template>
