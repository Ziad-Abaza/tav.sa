/**
 * Table density composable.
 * Controls row height (compact / default / comfortable).
 * Persists choice to localStorage per table instance.
 *
 * Usage:
 *   const { density, densityClass, cycleDensity } = useTableDensity('contacts_table_density')
 */
import { ref } from 'vue'

export const DENSITY_OPTIONS = ['compact', 'default', 'comfortable']

/** Tailwind padding class applied to TableCell */
export const DENSITY_CLASS_MAP = {
    compact: 'py-1',
    default: 'py-3',
    comfortable: 'py-5',
}

export const DENSITY_LABELS = {
    compact: 'Compact',
    default: 'Default',
    comfortable: 'Comfortable',
}

export function useTableDensity(storageKey) {
    function readStorage() {
        try {
            const raw = localStorage.getItem(storageKey)
            if (raw && DENSITY_OPTIONS.includes(raw)) return raw
        } catch {
            // ignore
        }
        return 'default'
    }

    function writeStorage(value) {
        try {
            localStorage.setItem(storageKey, value)
        } catch {
            // ignore
        }
    }

    const density = ref(readStorage())

    function setDensity(value) {
        if (!DENSITY_OPTIONS.includes(value)) return
        density.value = value
        writeStorage(value)
    }

    function cycleDensity() {
        const idx = DENSITY_OPTIONS.indexOf(density.value)
        const next = DENSITY_OPTIONS[(idx + 1) % DENSITY_OPTIONS.length]
        setDensity(next)
    }

    return {
        density,
        densityClass: () => DENSITY_CLASS_MAP[density.value],
        setDensity,
        cycleDensity,
    }
}
