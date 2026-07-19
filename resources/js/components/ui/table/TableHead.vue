<script setup>
import { cn } from '@app/lib/utils'

const props = defineProps({
    class: { type: String, default: '' },
    frozen: { type: Boolean, default: false },
})
</script>

<template>
    <th
        :class="
            cn(
                'h-10 px-4 text-left align-middle text-xs font-semibold uppercase tracking-wide text-gray-500',
                frozen && 'sticky left-0 z-10 bg-white shadow-[1px_0_0_0_#e5e7eb]',
                props.class,
            )
        "
    >
        <slot />
    </th>
</template>
