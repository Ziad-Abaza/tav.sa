<script setup>
import { computed } from 'vue'
import { useTimezoneAgo, useTimezoneFormat } from '@app/composables/useTimezone'

const props = defineProps({
    value: { default: null }, // ISO string
    row: { type: Object, required: true },
})

/**
 * Relative time (1 week ago)
 */
const timeAgo = computed(() => {
    if (!props.value) return '—'
    return useTimezoneAgo(new Date(props.value)).value
})

/**
 * Absolute formatted time (Jan 16, 2026 14:30 IST)
 */
const formattedFull = useTimezoneFormat(
    computed(() => props.value),
    true
)
</script>

<template>
    <div v-if="value" class="relative group inline-block">
        <!-- Relative time -->
        <span class="text-sm text-gray-600 dark:text-gray-300">
            {{ timeAgo }}
        </span>

        <!-- Tooltip -->
        <div
            class="pointer-events-none absolute -top-9 left-1/2 z-50
                   -translate-x-1/2 opacity-0 scale-95 transition-all duration-150
                   group-hover:opacity-100 group-hover:scale-100"
        >
            <div
                class="relative whitespace-nowrap rounded-md
                       bg-gray-800 px-2.5 py-1.5 text-xs text-white shadow-md
                       dark:bg-gray-700 dark:text-gray-100"
            >
                {{ formattedFull }}

                <!-- Arrow -->
                <div
                    class="absolute left-1/2 top-[86%] h-2 w-2
                           -translate-x-1/2 rotate-45
                           bg-gray-800 dark:bg-gray-700"
                ></div>
            </div>
        </div>
    </div>

    <span v-else class="text-sm text-gray-400">—</span>
</template>