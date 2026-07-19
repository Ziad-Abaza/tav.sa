<script setup>
import { cn } from '@app/lib/utils'

const props = defineProps({
    class: { type: String, default: '' },
    sticky: { type: Boolean, default: false },
})
</script>

<template>
    <thead :class="cn('[&_tr]:border-b', sticky && 'sticky top-0 z-20 bg-white', props.class)">
        <slot />
    </thead>
</template>
