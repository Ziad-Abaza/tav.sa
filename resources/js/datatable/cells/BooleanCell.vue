<script setup>
/**
 * BooleanCell — renders a boolean as a checkmark (true) or dash (false/null).
 * Useful for read-only boolean columns that don't need an interactive toggle.
 */
defineProps({
    value: { default: false },
    row: { type: Object, required: true },
})
</script>

<template>
    <span v-if="value" class="inline-flex text-green-600">
        <!-- Checkmark icon -->
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
    </span>
    <span v-else class="text-xs text-gray-400">—</span>
</template>
