<script setup>
/**
 * ToggleCell — read-only toggle display component.
 *
 * IMPORTANT: This component is display-only. It does NOT make any API calls.
 * Interactive PATCH logic must live in the parent component via a named slot override.
 * Use this component for simple boolean display; override the slot when you need
 * click handlers (e.g. #is_opted_out, #is_enabled in ContactDataTable).
 *
 * Emits 'toggle' if used in a context where the parent handles it.
 */
defineProps({
    value: { default: false }, // boolean
    row: { type: Object, required: true },
    activeColor: { type: String, default: 'bg-green-500' },
})

const emit = defineEmits(['toggle'])
</script>

<template>
    <button type="button"
        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        :class="value ? activeColor : 'bg-gray-200'" role="switch" :aria-checked="String(!!value)"
        @click="emit('toggle', row)">
        <span
            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
            :class="value ? 'translate-x-4' : 'translate-x-0'" />
    </button>
</template>
