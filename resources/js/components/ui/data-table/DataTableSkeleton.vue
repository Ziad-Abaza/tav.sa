<script setup>
defineProps({
    /** Number of columns to render shimmer cells for */
    columns: { type: Number, required: true },
    /** Number of skeleton rows to show */
    rows: { type: Number, default: 8 },
})
</script>

<template>
    <tbody>
        <tr
            v-for="r in rows"
            :key="r"
            class="border-b border-gray-100 last:border-0"
        >
            <td
                v-for="c in columns"
                :key="c"
                class="px-4 py-3"
            >
                <div
                    class="h-4 animate-pulse rounded bg-gray-200 dark:bg-slate-600"
                    :style="{ width: c === 1 ? '60%' : `${40 + ((r * c * 13) % 40)}%` }"
                />
            </td>
        </tr>
    </tbody>
</template>
