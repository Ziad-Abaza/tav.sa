<script setup>
import { cn } from '@app/lib/utils'

const props = defineProps({
    class: { type: String, default: '' },
})
</script>

<template>
    <tr
        :class="
            cn(
                'border-b transition-colors hover:bg-gray-50 dark:text-white dark:border-gray-600 dark:hover:bg-slate-700 data-[state=selected]:bg-gray-100',
                props.class,
            )
        "
    >
        <slot />
    </tr>
</template>
