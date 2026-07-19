/**
 * useTableSettings — fetches column definitions + table defaults from the backend.
 *
 * Endpoint: GET {apiBase}/table/{resource}/settings
 * Response: { columns[], per_page, default_sort, default_direction, searchable, sticky, max_height }
 *
 * apiBase examples:
 *   Tenant: "/{subdomain}/api"  → /{subdomain}/api/table/{resource}/settings
 *   Admin:  "/admin/api"        → /admin/api/table/{resource}/settings
 *
 * Module-level cache: settings are cached per "apiBase:resource" key and survive
 * Livewire navigations within the same browser session. Only a full page reload clears it.
 */
import axios from 'axios'
import { ref } from 'vue'

// Module-level — shared across all component instances, survives Livewire navigation
const _cache = new Map()

export function useTableSettings(resource, apiBase) {
    const settings = ref(null)
    const isReady = ref(false)
    const error = ref(null)

    async function load() {
        const cacheKey = `${apiBase}:${resource}`

        if (_cache.has(cacheKey)) {
            settings.value = _cache.get(cacheKey)
            isReady.value = true
            return
        }

        try {
            const { data } = await axios.get(`${apiBase}/table/${resource}/settings`)
            _cache.set(cacheKey, data)
            settings.value = data
        } catch (err) {
            console.error('[useTableSettings] failed to load settings:', err)
            error.value = 'Failed to load table settings'
        } finally {
            isReady.value = true
        }
    }

    load()

    return { settings, isReady, error }
}
