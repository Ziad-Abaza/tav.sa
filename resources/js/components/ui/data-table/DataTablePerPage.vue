<script setup>
const props = defineProps({
    perPage: { type: Number, required: true },
    options: { type: Array, default: () => [10, 15, 25, 50, 100] },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['change'])

function onChange(e) {
    emit('change', parseInt(e.target.value, 10))
}
</script>

<template>
    <div class="inline-flex items-center">
        <div
            class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-2 text-sm
                   shadow-sm transition
                   dark:border-gray-700 dark:bg-gray-800"
        >
            <span class="text-gray-500 dark:text-gray-400 border-r border-gray-300 pe-2">
                Per page
            </span>

            <select
                :value="perPage"
                :disabled="disabled"
                @change="onChange"
            class="fi-select-input block border-none bg-transparent py-1.5 pe-8 text-base text-gray-950 transition duration-75 focus:ring-0 disabled:text-gray-500 disabled:[-webkit-text-fill-color:theme(colors.gray.500)] dark:text-white dark:disabled:text-gray-400 dark:disabled:[-webkit-text-fill-color:theme(colors.gray.400)] sm:text-sm sm:leading-6 [&_optgroup]:bg-white [&_optgroup]:dark:bg-gray-900 [&_option]:bg-white [&_option]:dark:bg-gray-900 ps-3"
            >
                <option
                    v-for="opt in options"
                    :key="opt"
                    :value="opt"
                >
                    {{ opt }}
                </option>
            </select>
        </div>
    </div>
</template>