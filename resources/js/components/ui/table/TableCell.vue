<script setup>
import { cn } from '@app/lib/utils'
import { inject } from 'vue'

const props = defineProps({
    class: { type: String, default: '' },
    colspan: { type: Number, default: undefined },
    frozen: { type: Boolean, default: false },
})

// Injected from DataTable — falls back to 'py-3' when used standalone
const densityClass = inject('tableDensityClass', () => 'py-3')
</script>

<template>
    <td :colspan="colspan"
        :class="cn('px-4 align-middle text-sm', densityClass(), frozen && 'sticky left-0 z-10 bg-white shadow-[1px_0_0_0_#e5e7eb]', props.class)">
        <slot />
    </td>
</template>
