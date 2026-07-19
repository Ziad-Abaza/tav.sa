<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    /** All column definitions: [{ key, label }] */
    columns: { type: Array, required: true },
    /** Set of currently-visible column keys */
    visibleKeys: { type: Object, required: true }, // reactive Set
    /** Keys the user cannot toggle (always shown) */
    requiredKeys: { type: Array, default: () => [] },
})

const emit = defineEmits(['toggle'])

const open = ref(false)
const containerRef = ref(null)

function toggleColumn(key) {
    emit('toggle', key)
}

function handleClickOutside(e) {
    if (containerRef.value && !containerRef.value.contains(e.target)) {
        open.value = false
    }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', handleClickOutside))

const toggleableColumns = props.columns.filter(c => !props.requiredKeys.includes(c.key))
</script>

<template>
    <div ref="containerRef" class="relative">
        <!-- Trigger button — borderless when inside a pill group -->
        <button type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-l-md px-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 focus:outline-none dark:bg-slate-800 dark:text-white"
            @click="open = !open">
            <!-- Grid/columns icon -->
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 4H5a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V5a1 1 0 00-1-1zM19 4h-4a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V5a1 1 0 00-1-1zM9 14H5a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1v-4a1 1 0 00-1-1zM19 14h-4a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1v-4a1 1 0 00-1-1z" />
            </svg>
            Columns
        </button>

        <!-- Dropdown panel -->
        <div v-if="open"
            class="absolute right-0 top-full z-50 mt-1 min-w-[160px] rounded-md border border-gray-200 bg-white dark:border-gray-600 dark:bg-slate-800 py-1 shadow-lg">
            <div class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400">
                Toggle columns
            </div>
            <label v-for="col in toggleableColumns" :key="col.key"
                class="flex cursor-pointer items-center gap-2.5 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:hover:bg-slate-700 dark:text-white">
                <input type="checkbox" :checked="visibleKeys.has(col.key)"
                    class="h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 "
                    @change="toggleColumn(col.key)" />
                {{ col.label }}
            </label>
        </div>
    </div>
</template>
