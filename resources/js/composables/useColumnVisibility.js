/**
 * Column visibility composable.
 * Persists visible column keys to localStorage so preferences survive page reloads.
 *
 * Usage:
 *   const { visibleColumns, isVisible, toggleColumn } = useColumnVisibility(
 *     allColumns,
 *     'contacts_table_columns',
 *     ['name'] // always-visible, not toggleable
 *   )
 */
import { computed, ref } from 'vue'

export function useColumnVisibility(columns, storageKey, requiredKeys = []) {
    const allKeys = columns.map(c => c.key)

    function readStorage() {
        try {
            const raw = localStorage.getItem(storageKey)
            if (raw) {
                const parsed = JSON.parse(raw)
                if (Array.isArray(parsed) && parsed.length > 0) {
                    // Always include required keys even if they were stored as hidden
                    const merged = new Set([...requiredKeys, ...parsed.filter(k => allKeys.includes(k))])
                    return merged
                }
            }
        } catch {
            // ignore parse errors
        }
        // Default: all columns visible
        return new Set(allKeys)
    }

    function writeStorage(keysSet) {
        try {
            localStorage.setItem(storageKey, JSON.stringify([...keysSet]))
        } catch {
            // ignore storage errors (private browsing etc.)
        }
    }

    const visibleKeys = ref(readStorage())

    const visibleColumns = computed(() =>
        columns.filter(c => visibleKeys.value.has(c.key))
    )

    function toggleColumn(key) {
        if (requiredKeys.includes(key)) return // can't hide required columns
        const next = new Set(visibleKeys.value)
        if (next.has(key)) {
            // Only hide if there are still at least 1 non-required column visible
            const nonRequiredVisible = [...next].filter(k => !requiredKeys.includes(k))
            if (nonRequiredVisible.length <= 1) return // don't allow hiding the last visible column
            next.delete(key)
        } else {
            next.add(key)
        }
        visibleKeys.value = next
        writeStorage(next)
    }

    function isVisible(key) {
        return visibleKeys.value.has(key)
    }

    function resetToDefaults() {
        const all = new Set(allKeys)
        visibleKeys.value = all
        writeStorage(all)
    }

    return {
        visibleKeys,
        visibleColumns,
        toggleColumn,
        isVisible,
        resetToDefaults,
    }
}
