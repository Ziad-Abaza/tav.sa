<script setup>
import { computed } from 'vue'

const props = defineProps({
    value: { default: null }, // can be object OR string
    row: { type: Object, required: true },
})

const normalizedValue = computed(() => {
    if (!props.value) return null

    // If value is a string (e.g., "Paid")
    if (typeof props.value === 'string') {
        return {
            name: props.value,
            color: '#6B7280' // default gray color
        }
    }

    // If value is object (Proxy or normal object)
    if (typeof props.value === 'object') {
        return {
            name: props.value.name ?? '',
            color: props.value.color ?? '#6B7280'
        }
    }

    return null
})
</script>

<template>
    <span v-if="normalizedValue"
        class="inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium" :style="{
            backgroundColor: normalizedValue.color + '20',
            color: normalizedValue.color,
            borderColor: normalizedValue.color + '50',
            borderWidth: '1px',
        }">
        {{ normalizedValue.name }}
    </span>
    <span v-else class="text-xs text-gray-400">—</span>
</template>