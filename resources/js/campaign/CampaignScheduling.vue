<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { VueDatePicker } from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import { AkClock } from "@kalimahapps/vue-icons";

const props = defineProps({
    sendNow: {
        type: Boolean,
        default: true,
    },
    scheduledSendTime: {
        type: [String, Date, null],
        default: null,
    },
    dateFormat: {
        type: String,
        default: "dd-MM-yyyy",
    },
    timeFormat: {
        type: String,
        default: "24",
    },
    timezone: {
        type: String,
        default: "UTC",
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits([
    "update:sendNow",
    "update:scheduledSendTime",
    "update:errors",
]);

// Local state
const sendNowOption = ref(props.sendNow);
const scheduledTime = ref(props.scheduledSendTime);
const isDarkMode = ref(false);

// Check for dark mode
onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains("dark");

    // Watch for dark mode changes
    const observer = new MutationObserver(() => {
        isDarkMode.value = document.documentElement.classList.contains("dark");
    });

    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["class"],
    });
});

// Date picker configuration
const datePickerFormat = computed(() => {
    // Convert PHP date format to VueDatePicker format
    // Use a mapping approach to avoid conflicts during replacement
    const formatMap = {
        Y: "yyyy", // Year (4 digits)
        y: "yy", // Year (2 digits)
        F: "MMMM", // Month name (January-December)
        M: "MMM", // Month name (Jan-Dec)
        m: "MM", // Month (01-12)
        n: "M", // Month (1-12)
        d: "dd", // Day (01-31)
        j: "d", // Day (1-31)
        D: "EEE", // Day name (Mon-Sun)
        l: "EEEE", // Day name (Monday-Sunday)
    };

    let format = "";
    for (let i = 0; i < props.dateFormat.length; i++) {
        const char = props.dateFormat[i];
        format += formatMap[char] || char;
    }

    // Add time format
    if (props.timeFormat === "12") {
        format += " hh:mm a";
    } else {
        format += " HH:mm";
    }
    return format;
});

// Minimum date/time (current time + 1 minute)
const minDateTime = computed(() => {
    const now = new Date();
    now.setMinutes(now.getMinutes() + 1);
    return now;
});

// Watch for changes
watch(sendNowOption, (newValue) => {
    emit("update:sendNow", newValue);
    if (newValue) {
        // Clear scheduled time when switching to send now
        scheduledTime.value = null;
        emit("update:scheduledSendTime", null);
        // Clear error
        const newErrors = { ...props.errors };
        delete newErrors.scheduled_send_time;
        emit("update:errors", newErrors);
    }
});

watch(scheduledTime, (newValue) => {
    if (!sendNowOption.value && newValue) {
        emit("update:scheduledSendTime", newValue);
        // Clear error when a valid date is selected
        const newErrors = { ...props.errors };
        delete newErrors.scheduled_send_time;
        emit("update:errors", newErrors);
    }
});

// Validate scheduling
const validateScheduling = () => {
    if (!sendNowOption.value && !scheduledTime.value) {
        const newErrors = {
            ...props.errors,
            scheduled_send_time: "Please select a scheduled send time",
        };
        emit("update:errors", newErrors);
        return false;
    }
    return true;
};

// Expose validation method
defineExpose({
    validateScheduling,
});
</script>

<template>
    <div
        class="rounded-lg shadow-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700"
    >
        <!-- Header -->
        <div
            class="border-b border-slate-200 p-4 dark:border-slate-600 flex items-center gap-4"
        >
            <div class="p-2 rounded-full bg-primary-100 dark:bg-primary-900">
                <AkClock class="h-6 w-6 text-primary-600" />
            </div>

            <div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-300">
                    Scheduling
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Choose when to send your campaign
                </p>
            </div>
        </div>

        <!-- Send Options -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 px-6 py-4">
            <!-- Send Now Option -->
            <div
                class="relative group cursor-pointer"
                @click="sendNowOption = true"
            >
                <div
                    class="bg-gradient-to-br from-success-50 to-emerald-50 dark:from-success-900/20 dark:to-emerald-900/20 border rounded-xl p-4 transition-all duration-200"
                    :class="{
                        'ring-0 ring-success-500 bg-success-100 dark:bg-success-900/30 border-success-500':
                            sendNowOption,
                        'border-success-200 dark:border-success-800 hover:border-success-300':
                            !sendNowOption,
                    }"
                >
                    <!-- Header with Radio and Title -->
                    <div class="flex items-start gap-3 mb-3">
                        <div class="flex-shrink-0 mt-0.5">
                            <input
                                type="radio"
                                id="send_now"
                                name="send_option"
                                :checked="sendNowOption"
                                @change="sendNowOption = true"
                                class="w-4 h-4 text-success-600 border-2 border-gray-300 focus:ring-0 focus:ring-success-500 focus:ring-offset-0"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <label
                                for="send_now"
                                class="block text-base font-semibold text-gray-900 dark:text-gray-100 cursor-pointer"
                            >
                                Send Immediately
                            </label>
                            <p
                                class="text-sm text-gray-600 dark:text-gray-400 mt-1"
                            >
                                Campaign will start immediately after creation
                            </p>
                        </div>
                    </div>

                    <!-- Feature Badge -->
                    <div class="flex items-center justify-between">
                        <div
                            class="flex items-center text-success-600 dark:text-success-400"
                        >
                            <div
                                class="w-5 h-5 bg-success-100 dark:bg-success-800 rounded-full flex items-center justify-center mr-2"
                            >
                                <svg
                                    class="w-3 h-3"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </div>
                            <span class="text-sm font-medium"
                                >Instant Delivery</span
                            >
                        </div>
                        <div class="text-right">
                            <div
                                class="w-8 h-8 bg-success-600 rounded-full flex items-center justify-center"
                            >
                                <svg
                                    class="w-4 h-4 text-white"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule Later Option -->
            <div
                class="relative group cursor-pointer"
                @click="sendNowOption = false"
            >
                <div
                    class="bg-gradient-to-br from-info-50 to-primary-50 dark:from-info-900/20 dark:to-primary-900/20 border rounded-xl p-4 transition-all duration-200"
                    :class="{
                        'ring-0 ring-primary-500 bg-info-100 dark:bg-info-900/30 border-primary-500':
                            !sendNowOption,
                        'border-info-200 dark:border-info-800 hover:border-info-300':
                            sendNowOption,
                    }"
                >
                    <!-- Header with Radio and Title -->
                    <div class="flex items-start gap-3 mb-3">
                        <div class="flex-shrink-0 mt-0.5">
                            <input
                                type="radio"
                                id="schedule_later"
                                name="send_option"
                                :checked="!sendNowOption"
                                @change="sendNowOption = false"
                                class="w-4 h-4 text-primary-600 border-2 border-gray-300 focus:ring-0 focus:ring-primary-500 focus:ring-offset-0"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <label
                                for="schedule_later"
                                class="block text-base font-semibold text-gray-900 dark:text-gray-100 cursor-pointer"
                            >
                                Schedule for Later
                            </label>
                            <p
                                class="text-sm text-gray-600 dark:text-gray-400 mt-1"
                            >
                                Choose a specific date and time to send
                            </p>
                        </div>
                    </div>

                    <!-- Feature Badge -->
                    <div class="flex items-center justify-between">
                        <div
                            class="flex items-center text-primary-600 dark:text-primary-400"
                        >
                            <div
                                class="w-5 h-5 bg-primary-100 dark:bg-primary-800 rounded-full flex items-center justify-center mr-2"
                            >
                                <svg
                                    class="w-3 h-3"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                            <span class="text-sm font-medium"
                                >Perfect Timing</span
                            >
                        </div>
                        <div class="text-right">
                            <div
                                class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center"
                            >
                                <svg
                                    class="w-4 h-4 text-white"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scheduled Time Input -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="!sendNowOption" class="px-6 py-4">
                <label
                    for="scheduled_send_time"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3"
                >
                    Scheduled Send Time
                    <span class="text-danger-500">*</span>
                </label>
                <div class="relative">
                    <VueDatePicker
                        v-model="scheduledTime"
                        model-type="iso"
                        :formats="{
                            input: datePickerFormat,
                            preview: datePickerFormat,
                        }"
                        :enable-time-picker="true"
                        :is-24="props.timeFormat === '24'"
                        :min-date="minDateTime"
                        :timezone="timezone"
                        placeholder="Select date and time"
                        :dark="isDarkMode"
                        input-class-name="dp-custom-input"
                    >
                        <template #input-icon>
                            <svg
                                class="ml-2 w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                        </template>
                    </VueDatePicker>
                </div>
                <div
                    v-if="errors.scheduled_send_time"
                    class="mt-2 text-sm text-danger-600 dark:text-danger-400 flex items-center space-x-1"
                >
                    <svg
                        class="w-4 h-4"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    <span>{{ errors.scheduled_send_time }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Campaign will be sent at the specified time
                </p>
            </div>
        </transition>
    </div>
</template>

<style>
/* Custom styles for VueDatePicker to match application theme */
.dp__theme_light {
    --dp-background-color: #ffffff;
    --dp-text-color: #1f2937;
    --dp-hover-color: #f3f4f6;
    --dp-hover-text-color: #111827;
    --dp-hover-icon-color: #6366f1;
    --dp-primary-color: #6366f1;
    --dp-primary-text-color: #ffffff;
    --dp-secondary-color: #e5e7eb;
    --dp-border-color: #d1d5db;
    --dp-menu-border-color: #d1d5db;
    --dp-border-color-hover: #9ca3af;
    --dp-disabled-color: #f3f4f6;
    --dp-scroll-bar-background: #f3f4f6;
    --dp-scroll-bar-color: #9ca3af;
    --dp-success-color: #10b981;
    --dp-success-color-disabled: #86efac;
    --dp-icon-color: #6b7280;
    --dp-danger-color: #ef4444;
    --dp-highlight-color: rgba(99, 102, 241, 0.1);
}

.dp__theme_dark {
    --dp-background-color: #1f2937;
    --dp-text-color: #f3f4f6;
    --dp-hover-color: #374151;
    --dp-hover-text-color: #ffffff;
    --dp-hover-icon-color: #818cf8;
    --dp-primary-color: #6366f1;
    --dp-primary-text-color: #ffffff;
    --dp-secondary-color: #374151;
    --dp-border-color: #4b5563;
    --dp-menu-border-color: #4b5563;
    --dp-border-color-hover: #6b7280;
    --dp-disabled-color: #374151;
    --dp-scroll-bar-background: #374151;
    --dp-scroll-bar-color: #6b7280;
    --dp-success-color: #10b981;
    --dp-success-color-disabled: #065f46;
    --dp-icon-color: #9ca3af;
    --dp-danger-color: #ef4444;
    --dp-highlight-color: rgba(99, 102, 241, 0.2);
}

/* Custom input styling */
.dp-custom-input {
    width: 100%;
    padding: 0.625rem 2.5rem 0.625rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    background-color: #ffffff;
    color: #1f2937;
    transition: all 0.15s ease-in-out;
    cursor: pointer;
}

.dark .dp-custom-input {
    border-color: #4b5563;
    background-color: #1f2937;
    color: #f3f4f6;
}

.dp-custom-input:hover {
    border-color: #9ca3af;
}

.dark .dp-custom-input:hover {
    border-color: #6b7280;
}

.dp-custom-input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.dark .dp-custom-input:focus {
    border-color: #818cf8;
    box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.1);
}

/* Ensure the datepicker menu appears above other content */
.dp__menu {
    z-index: 9999 !important;
}
</style>
