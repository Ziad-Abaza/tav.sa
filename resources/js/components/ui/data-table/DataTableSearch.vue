<script setup>
import { ref } from 'vue'

const props = defineProps({
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Search...' },
})

const emit = defineEmits(['search'])

const value = ref('')
let debounceTimer = null

function onInput(e) {
    value.value = e.target.value
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        emit('search', value.value)
    }, 350)
}
</script>

<template>
    <div class="relative">
        <svg
            class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
        >
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
        </svg>
        <input
            type="text"
            :value="value"
            :disabled="disabled"
            :placeholder="placeholder"
            class="h-9 w-full md:w-64 rounded-md border border-gray-300 bg-white dark:bg-slate-800 dark:border-gray-600 pl-9 pr-3 text-sm shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
            @input="onInput"
        />
    </div>
</template>
