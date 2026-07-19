<template>
    <div
        class="ml-6 bg-white dark:bg-slate-800 rounded-md border border-slate-300 dark:border-slate-700 flex flex-col"
    >
        <div class="border-b border-slate-300 p-4 dark:border-slate-600 flex items-center gap-4">
          
            <AkEyeOpen class="h-6 w-6 text-primary-500" />
          
            <div>
                <h3
                    class="text-lg font-semibold text-slate-700 dark:text-slate-100 mb-1"
                >
                    {{ title }}
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ description }}
                </p>
            </div>
        </div>
        <div class="p-6">
            <slot>
                <p class="text-slate-400 text-sm">
                    Select template to see preview
                </p>
            </slot>
        </div>
    </div>
</template>

<script setup>
import { AkEyeOpen } from "@kalimahapps/vue-icons";
defineProps({
    title: String,
    description: String,
    content: [String, Object],
});
</script>
