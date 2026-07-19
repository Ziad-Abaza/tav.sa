<script setup>
defineProps({
    value: { default: null },
    row: { type: Object, required: true },
})
</script>

<template>
    <div v-if="value" class="relative group inline-flex items-center">
        <!-- Avatar -->
        <img
            v-if="value.avatar"
            :src="value.avatar"
            :alt="value.firstname"
            class="h-7 w-7 rounded-full object-cover cursor-pointer"
        />

        <div
            v-else
            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-gray-300 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-200"
        >
            {{ (value.firstname || '?')[0].toUpperCase() }}
        </div>

        <!-- Tooltip -->
        <div
            class="pointer-events-none absolute -top-10 left-1/2 z-50 hidden -translate-x-1/2 group-hover:block"
        >
            <div
                class="relative rounded-md bg-gray-800 px-2.5 py-1.5 text-xs text-white shadow-md
                       dark:bg-gray-700 dark:text-gray-100 whitespace-nowrap"
            >
                {{ value.firstname }} {{ value.lastname }}

                <!-- Arrow -->
                <div
                    class="absolute left-1/2 top-[86%] h-2 w-2 -translate-x-1/2 rotate-45
                           bg-gray-800 dark:bg-gray-700"
                ></div>
            </div>
        </div>
    </div>

    <span v-else class="text-xs text-gray-400 dark:text-gray-500">
        Not assigned
    </span>
</template>