/**
 * Generic table filter state composable.
 * Manages active filter values, persists to localStorage, and exposes
 * a clean API for filter UI components to bind to.
 *
 * Usage:
 *   const { activeFilters, setFilter, clearFilter, clearAll, activeCount } =
 *     useTableFilters(['type', 'status_id', 'source_id'], 'contacts_table_filters')
 *
 *   // Pass to DataTable as extraParams:
 *   <DataTable ref="table" :extra-params="activeFilters" />
 */
import { computed, reactive } from 'vue'

export function useTableFilters(filterKeys, storageKey = null) {
    function readStorage() {
        if (!storageKey) return {}
        try {
            const raw = localStorage.getItem(storageKey)
            if (raw) {
                const parsed = JSON.parse(raw)
                if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                    // Only keep keys that are in filterKeys to avoid stale data
                    const cleaned = {}
                    for (const key of filterKeys) {
                        if (parsed[key] != null && parsed[key] !== '') {
                            cleaned[key] = parsed[key]
                        }
                    }
                    return cleaned
                }
            }
        } catch {
            // ignore parse errors
        }
        return {}
    }

    function writeStorage(filters) {
        if (!storageKey) return
        try {
            // Only persist non-empty values
            const toStore = {}
            for (const [k, v] of Object.entries(filters)) {
                if (v != null && v !== '') {
                    toStore[k] = v
                }
            }
            if (Object.keys(toStore).length > 0) {
                localStorage.setItem(storageKey, JSON.stringify(toStore))
            } else {
                localStorage.removeItem(storageKey)
            }
        } catch {
            // ignore storage errors
        }
    }

    // Initialize from localStorage
    const stored = readStorage()

    // Reactive filter state — only non-empty values are included so they can
    // be spread directly into DataTable's extraParams without sending empty strings
    const activeFilters = reactive({ ...stored })

    const activeCount = computed(() => {
        return Object.values(activeFilters).filter(v => v != null && v !== '').length
    })

    function setFilter(key, value) {
        if (value == null || value === '') {
            delete activeFilters[key]
        } else {
            activeFilters[key] = value
        }
        writeStorage(activeFilters)
    }

    function clearFilter(key) {
        delete activeFilters[key]
        writeStorage(activeFilters)
    }

    function clearAll() {
        for (const key of filterKeys) {
            delete activeFilters[key]
        }
        if (storageKey) {
            try {
                localStorage.removeItem(storageKey)
            } catch {
                // ignore
            }
        }
    }

    return {
        activeFilters,
        activeCount,
        setFilter,
        clearFilter,
        clearAll,
    }
}
