<script setup>
import { cn } from '@app/lib/utils'

const props = defineProps({
    class: { type: String, default: '' },
    /** When set, enables vertical scroll with sticky header. E.g. "500px" or "60vh". */
    maxHeight: { type: String, default: null },
})
</script>

<template>
    <div
        class="border border-gray-200 dark:border-gray-600 rounded-md relative w-full overflow-x-auto dark:bg-slate-800"
        :class="maxHeight && 'overflow-y-auto'"
        :style="maxHeight ? { maxHeight } : {}"
    > 
        <table :class="cn('w-full caption-bottom text-sm', props.class)">
            <slot />
        </table>
    </div>
</template>
