<script setup>
import { BsExclamationTriangle } from "@kalimahapps/vue-icons";

defineProps({
    /** Controls modal visibility */
    show: { type: Boolean, default: false },
    /** Modal heading */
    title: { type: String, default: "Delete" },
    /** Paragraph body text */
    message: { type: String, default: "Are you sure? This action cannot be undone." },
    /** Highlighted name/value shown in bold inside the message */
    itemName: { type: String, default: null },
    /** Cancel button label */
    cancelLabel: { type: String, default: "Cancel" },
    /** Confirm button label */
    confirmLabel: { type: String, default: "Delete" },
    /** Disable confirm button (e.g. while deleting) */
    isDeleting: { type: Boolean, default: false },
});

const emit = defineEmits(["confirm", "cancel"]);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[10000] overflow-y-auto"
        >
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-gray-500 opacity-75 dark:bg-gray-900 transition-opacity"
                    @click="emit('cancel')"
                />

                <!-- Modal panel -->
                <div class="relative bg-white dark:bg-slate-800 rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <!-- Icon -->
                        <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-danger-100 sm:mx-0 sm:size-12">
                            <BsExclamationTriangle class="w-7 h-7 text-danger-600" />
                        </div>

                        <!-- Text -->
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-base font-semibold text-slate-700 dark:text-slate-200">
                                {{ title }}
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ message }}
                                    <span
                                        v-if="itemName"
                                        class="font-semibold text-gray-700 dark:text-gray-200"
                                    >{{ itemName }}</span>{{ itemName ? '?' : '' }}
                                </p>
                                <!-- Allow extra content via slot -->
                                <slot />
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-5 sm:mt-4 flex justify-end items-center gap-3 bg-gray-100 dark:bg-gray-700 -mx-6 -mb-6 px-6 py-2 rounded-b-lg">
                        <button
                            type="button"
                            class="inline-flex justify-center rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-slate-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                            @click="emit('cancel')"
                        >
                            {{ cancelLabel }}
                        </button>
                        <button
                            type="button"
                            :disabled="isDeleting"
                            class="inline-flex justify-center items-center gap-2 rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
                            @click="emit('confirm')"
                        >
                            <svg
                                v-if="isDeleting"
                                class="animate-spin h-4 w-4 text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            {{ confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
