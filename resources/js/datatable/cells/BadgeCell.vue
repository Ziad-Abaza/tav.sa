<script setup>
/**
 * BadgeCell — renders a string value as a colored pill badge.
 *
 * Color map (extend as needed):
 *   customer → green   lead → yellow   guest/null → gray
 *
 * Falls back to gray for any unknown value.
 */
const props = defineProps({
    value: { default: null },
    row: { type: Object, required: true },
})

const COLOR_MAP = {
    customer: 'bg-success-100 text-success-800 dark:text-success-400 dark:bg-success-900/20',
    lead: 'bg-primary-100 text-primary-800 dark:text-primary-400 dark:bg-primary-900/20',
    guest: 'bg-warning-100 text-warning-800 dark:text-warning-400 dark:bg-warning-900/20',
}

function colorClass(val) {
    return COLOR_MAP[val] ?? 'bg-gray-100 text-gray-700'
}
</script>

<template>
    <span
        class="inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium capitalize"
        :class="colorClass(value)"
    >
        {{ value || '—' }}
    </span>
</template>
