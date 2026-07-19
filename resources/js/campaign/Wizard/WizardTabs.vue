<template>
    <div class="flex gap-4 items-center overflow-x-auto">
        <template v-for="(tab, index) in tabs" :key="index">
         <button
    type="button"
    class="flex flex-1 items-center justify-center gap-1 min-w-max md:w-auto text-sm font-semibold rounded-lg border p-2 md:px-4 md:py-2 transition-all"
    :class="{
        'bg-primary-600 text-white border-primary-600 dark:bg-primary-500 dark:text-white dark:border-primary-500':
            index === activeTabIndex && tab.isValid,

        'bg-danger-500 text-white border-danger-600 dark:bg-danger-500 dark:text-white dark:border-danger-500':
            index === activeTabIndex && !tab.isValid,

        'bg-primary-100 text-primary-700 border-primary-300 dark:bg-primary-900 dark:text-primary-200 dark:border-primary-700':
            index !== activeTabIndex && tab.isValid,

        'bg-danger-50 text-danger-700 border-danger-300 hover:bg-danger-100 dark:bg-danger-900/20 dark:text-danger-400 dark:border-danger-700 dark:hover:bg-danger-900/30':
            index !== activeTabIndex && !tab.isValid,
    }"
    @click="$emit('change', tab)"
>
    <component
        :is="tab.isValid ? AkCheck : BsExclamationTriangle"
        class="w-4 h-4 shrink-0"
    />

    <span class="whitespace-nowrap">
        {{ tab.label }}
    </span>
</button>

        </template>
    </div>
</template>

<script setup>
import { BsExclamationTriangle, AkCheck } from "@kalimahapps/vue-icons";
import { computed } from "vue";
const props = defineProps({
    tabs: Array,
    activeTab: Object,
});

const activeTabIndex = computed(() =>
    props.tabs.findIndex((t) => t.label === props.activeTab.label),
);
</script>
