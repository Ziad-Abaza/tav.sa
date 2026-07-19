/**
 * DataTable composable.
 * Ported from wm-saas-v2 (originally adapted from Concord CRM).
 * Manages search/sort/paginate state + axios requests with AbortController cancellation.
 *
 * Reactivity note: Paginator is a plain class — Vue's ref() does NOT track mutations
 * to class properties. We keep currentPage and perPage as separate refs, and replace
 * the entire collection ref on each load so Vue re-renders.
 *
 * extraParams reactivity: if options.extraParams is a Vue reactive object (e.g. from
 * useTableFilters), a watcher auto-reloads the table when filter values change,
 * without the parent needing to call reload() manually.
 */
import axios from 'axios'
import { computed, ref, watch } from 'vue'
import { Paginator } from '@app/services/Paginator'

export function useDataTable(options) {
    const settingsDefault = window.tablePaginationSettings?.current ?? 10
    const perPageOptions = window.tablePaginationSettings?.options?.filter(v => v !== 'all').map(Number) ?? [10, 50, 100, 500, 1000]

    // Read per-page setting from localStorage if available
    function readPerPageFromStorage() {
        if (!options.storageKey) return settingsDefault
        try {
            const stored = localStorage.getItem(`${options.storageKey}_per_page`)
            if (stored) {
                const parsed = parseInt(stored, 10)
                if (perPageOptions.includes(parsed)) {
                    return parsed
                }
            }
        } catch {
            // ignore parse errors
        }
        return settingsDefault
    }
    
    // Save per-page setting to localStorage
    function savePerPageToStorage(value) {
        if (!options.storageKey) return
        try {
            localStorage.setItem(`${options.storageKey}_per_page`, String(value))
        } catch {
            // ignore storage errors
        }
    }

    const initialPerPage = readPerPageFromStorage()
    const collection = ref(new Paginator({ per_page_options: perPageOptions, per_page: initialPerPage }))
    const isLoading = ref(false)
    const search = ref(options.initialState?.q ?? '')
    const sort = ref(options.initialState?.sort ?? null)
    const direction = ref(options.initialState?.direction ?? 'asc')

    // Keep pagination state as plain refs so computed() can track them
    const currentPage = ref(1)
    const perPage = ref(initialPerPage)

    let cancelController = null

    const queryParams = computed(() => ({
        page: currentPage.value,
        per_page: perPage.value,
        q: search.value || undefined,
        sort: sort.value || undefined,
        direction: sort.value ? direction.value : undefined,
        // Spread extraParams — supports both plain objects and Vue reactive objects
        ...(options.extraParams ?? {}),
    }))

    async function load() {
        if (cancelController) {
            cancelController.abort()
        }
        cancelController = new AbortController()

        isLoading.value = true

        try {
            const { data } = await axios.get(options.endpoint, {
                params: queryParams.value,
                signal: cancelController.signal,
            })

            // Replace the entire Paginator instance so Vue's ref() detects the change
            // and re-renders. Mutating class properties in-place doesn't trigger reactivity.
            const next = new Paginator({ per_page_options: perPageOptions, per_page: perPage.value })
            next.setState(data)
            collection.value = next
        } catch (error) {
            if (error instanceof Error && error.name === 'CanceledError') {
                return
            }
            if (error.name === 'AbortError' || error.code === 'ERR_CANCELED') {
                return
            }
            console.error('[useDataTable] load error:', error)
        } finally {
            isLoading.value = false
        }
    }

    function setSort(column) {
        if (sort.value === column) {
            direction.value = direction.value === 'asc' ? 'desc' : 'asc'
        } else {
            sort.value = column
            direction.value = 'asc'
        }
        currentPage.value = 1
        load()
    }

    function setSearch(value) {
        search.value = value
        currentPage.value = 1
        load()
    }

    function setPage(page) {
        currentPage.value = page
        load()
    }

    function setPerPage(value) {
        perPage.value = value
        savePerPageToStorage(value)
        currentPage.value = 1
        load()
    }

    function reload() {
        load()
    }

    /**
     * Optimistically update a single row in the collection by ID.
     * Replaces the entire Paginator instance so Vue's ref() detects the change.
     * Called after a successful inline field edit.
     */
    function updateRowData(id, updatedFields) {
        const items = collection.value.state.data
        const idx = items.findIndex(r => String(r.id) === String(id))
        if (idx === -1) return
        const updatedItems = items.map((r, i) => i === idx ? { ...r, ...updatedFields } : r)
        const next = new Paginator({ per_page_options: perPageOptions, per_page: perPage.value })
        next.setState({ ...collection.value.state, data: updatedItems })
        collection.value = next
    }

    // Watch extraParams only (not all of queryParams) to avoid triggering double-loads
    // when sort/search/page change (those already call load() directly).
    // Skip setup if extraParams is a plain static object with no reactive identity.
    if (options.extraParams) {
        // Track the extraParams object itself for deep changes.
        // 'flush: post' fires after the current render cycle, so any pending DOM updates
        // complete before we re-fetch. AbortController cancels stale requests on rapid changes.
        watch(
            () => options.extraParams,
            () => {
                currentPage.value = 1
                load()
            },
            { deep: true, flush: 'post' }
        )
    }

    // Initial load
    load()

    return {
        collection,
        isLoading,
        search,
        sort,
        direction,
        queryParams,
        load,
        reload,
        updateRowData,
        setSort,
        setSearch,
        setPage,
        setPerPage,
    }
}
