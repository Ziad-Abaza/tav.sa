<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { Handle, useVueFlow, useNode } from "@vue-flow/core";
const { removeNodes, nodes, addNodes, edges } = useVueFlow();

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

// Add emit to communicate validation state to parent component
const emit = defineEmits(["update:isValid"]);

const showInstructions = ref(false);
const instructionSections = ref({
    createAssistant: true,
    syncDocs: false,

});
// Function to toggle instruction sections
function toggleInstructionSection(section) {
    instructionSections.value[section] = !instructionSections.value[section];
}


// Extract data from Whatsmark output structure
const output = ref(
    props.data.output?.[0] || {
        personal_assistant: "",
    }
);

const aiModel = ref(output.value.personal_assistant || "");
const node = useNode();
const errors = ref({
    aiModel: false,
});
const isExpanded = ref(true);

// Personal assistant options - you'll need to load this from your API or pass as prop
const aiAssistantOptions = personalAssistant || [];

// Computed property for overall validation state
const isValid = computed(() => {
    // AI model is required
    if (!aiModel.value) {
        return false;
    }

    return true;
});

function updateNodeData() {
    props.data.output = [
        {
            personal_assistant: aiModel.value,
        },
    ];

    // Validate the form whenever data is updated
    validateForm();
}

function handleClickDelete() {
    removeNodes(node.id);
}

function handleClickDuplicate() {
    const { type, position, data } = node.node;

    // Create a new node ID
    const newNodeId = `node-${Date.now()}`;

    // Create a deep copy of the data
    const newData = JSON.parse(JSON.stringify(data));

    // Make sure the new node has its validation state properly set
    newData.isValid = false; // Start with invalid state to force validation

    const newNode = {
        id: newNodeId,
        type,
        position: {
            x: position.x + 100, // Move it slightly to make it visible
            y: position.y + 100,
        },
        data: newData,
    };

    // Add the new node
    addNodes(newNode);
}

function toggleExpand() {
    isExpanded.value = !isExpanded.value;
}

// Function to validate the form and update errors
function validateForm() {
    // Reset errors
    errors.value = {
        aiModel: false,
    };

    // Check if AI model is selected (required)
    if (!aiModel.value) {
        errors.value.aiModel = true;
    }

    // Update validation state
    const valid = isValid.value;

    // Update node data with validation state
    props.data.isValid = valid;
    props.data.errorMessage = !aiModel.value
        ? "AI Personal Assistant is required"
        : "";

    // Emit validation status to parent component
    emit("update:isValid", valid);

    return valid;
}

// Set node styling based on selection and validation state
const nodeClasses = computed(() => {
    return `ai-assistant-node relative ${props.selected ? "selected" : ""} ${
        !isValid.value
            ? "border-danger-300 shadow-danger-100 dark:border-danger-700 dark:shadow-danger-900/30"
            : ""
    } transition-all duration-200`;
});

// Watch for changes in form fields
watch(
    [aiModel],
    () => {
        updateNodeData();
    },
    { deep: true }
);

// Watch for validation changes
watch(
    isValid,
    (newVal) => {
        emit("update:isValid", newVal);
    },
    { immediate: true }
);

// Initial validation on mount
onMounted(() => {
    // Initialize with existing data if available
    if (props.data.output?.[0]?.personal_assistant) {
        aiModel.value = props.data.output[0].personal_assistant;
    }

    validateForm();
    emit("update:isValid", isValid.value);
});
</script>

<template>
    <div class="h-full w-full">
        <Handle
            type="target"
            position="left"
            :class="[
                'z-10 !h-4 !w-4 !border-2 !border-white',
                isValid ? '!bg-cyan-500' : '!bg-danger-500',
            ]"
        />

        <div
            :class="[
                nodeClasses,
                'overflow-hidden rounded-lg border-2 bg-white shadow-lg transition-all duration-200 hover:shadow-xl dark:bg-gray-800',
                isValid
                    ? 'border-gray-200 dark:border-gray-700'
                    : 'border-danger-300 dark:border-danger-700',
            ]"
            style="min-width: 280px; max-width: 320px"
        >
            <!-- Node type indicator - gradient bar -->
            <div
                :class="[
                    'h-1.5',
                    isValid
                        ? 'bg-gradient-to-r from-cyan-500 to-info-600'
                        : 'bg-gradient-to-r from-danger-500 to-orange-500',
                ]"
            ></div>

            <div class="p-4">
                <!-- Node Header -->
                <div class="node-header mb-3 flex items-center justify-between">
                    <div class="node-title flex items-center">
                        <div
                            :class="[
                                'node-icon mr-3 rounded-lg p-2 shadow-sm',
                                isValid
                                    ? 'bg-cyan-100 text-cyan-600 dark:bg-cyan-900/50 dark:text-cyan-300'
                                    : 'bg-danger-100 text-danger-600 dark:bg-danger-900/50 dark:text-danger-300',
                            ]"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-4 w-4"
                            >
                                <rect
                                    x="3"
                                    y="3"
                                    width="18"
                                    height="18"
                                    rx="2"
                                    ry="2"
                                ></rect>
                                <line x1="3" y1="9" x2="21" y2="9"></line>
                                <line x1="9" y1="21" x2="9" y2="9"></line>
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span
                                class="text-sm font-medium text-gray-800 dark:text-gray-200"
                                >{{ data.label || "AI Assistant" }}</span
                            >
                            <span
                                v-if="!isValid"
                                class="text-xs text-danger-500 dark:text-danger-400"
                                >Required field missing</span
                            >
                        </div>
                    </div>

                    <div class="node-actions flex space-x-1">
                        <button
                            @click="toggleExpand"
                            class="node-action-btn transform rounded-md border border-transparent bg-white p-1.5 text-gray-500 shadow-sm transition-all duration-300 ease-in-out hover:scale-105 hover:border-gray-200 hover:bg-gray-50 hover:text-cyan-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-cyan-400"
                            :title="isExpanded ? 'Collapse' : 'Expand'"
                        >
                            <svg
                                v-if="isExpanded"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-4 w-4 transform transition-transform duration-300"
                            >
                                <polyline points="18 15 12 9 6 15"></polyline>
                            </svg>
                            <svg
                                v-else
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-4 w-4 transform transition-transform duration-300"
                            >
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <button
                            v-on:click="handleClickDuplicate"
                            class="node-action-btn transform rounded-md border border-transparent bg-white p-1.5 text-gray-500 shadow-sm transition-all duration-300 ease-in-out hover:scale-105 hover:border-gray-200 hover:bg-gray-50 hover:text-cyan-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-cyan-400"
                            title="Copy node"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="h-4 w-4"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"
                                />
                            </svg>
                        </button>
                        <button
                            v-on:click="handleClickDelete"
                            class="node-action-btn transform rounded-md border border-transparent bg-white p-1.5 text-gray-500 shadow-sm transition-all duration-300 ease-in-out hover:scale-105 hover:border-danger-200 hover:bg-danger-50 hover:text-danger-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-danger-800/50 dark:hover:bg-danger-900/30 dark:hover:text-danger-400"
                            title="Delete node"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-4 w-4"
                            >
                                <path d="M3 6h18"></path>
                                <path
                                    d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"
                                ></path>
                                <path
                                    d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"
                                ></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Validation Error Message (only shown when not valid) -->
                <div
                    v-if="!isValid && isExpanded"
                    class="mb-3 rounded-md border border-danger-200 bg-danger-50 p-3 text-sm text-danger-600 dark:border-danger-800/50 dark:bg-danger-900/30 dark:text-danger-400"
                >
                    <div class="flex">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="mr-2 h-5 w-5 text-danger-500"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <div>
                            <div class="font-medium">
                                Please fix the following errors:
                            </div>
                            <ul class="mt-1 list-inside list-disc">
                                <li v-if="errors.aiModel">
                                    AI Personal Assistant is required
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Node Content -->
                <div v-show="isExpanded" class="node-content space-y-4">
                    <!-- Character Warning Alert for Footer -->

                    <!-- AI Personal Assistant Field (Required) -->
                    <div class="node-field">
                        <label
                            class="node-field-label mb-1.5 flex items-center text-xs font-medium"
                            :class="[
                                errors.aiModel
                                    ? 'text-danger-600 dark:text-danger-400'
                                    : 'text-gray-700 dark:text-gray-300',
                            ]"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="mr-1.5 h-3.5 w-3.5"
                                :class="[
                                    errors.aiModel
                                        ? 'text-danger-500'
                                        : 'text-cyan-500',
                                ]"
                            >
                                <polyline points="16 18 22 12 16 6"></polyline>
                                <polyline points="8 6 2 12 8 18"></polyline>
                            </svg>
                            AI Personal Assistant
                            <span class="ml-1 text-danger-500">*</span>
                        </label>
                        <div class="relative">
                            <v-select
                                v-model="aiModel"
                                :options="aiAssistantOptions"
                                label="name"
                                :reduce="(option) => option.id"
                                @update:modelValue="updateNodeData"
                                placeholder="Select AI Personal Assistant"
                                class="vue-select-custom w-full"
                                :class="{
                                    'border-danger-300': errors.aiModel,
                                    'border-gray-300': !errors.aiModel,
                                }"
                            ></v-select>

                            <p
                                v-if="errors.aiModel"
                                class="mt-1 text-xs text-danger-500"
                            >
                                Please select an AI Personal Assistant
                            </p>
                        </div>
                        <!-- Instructions Section -->
                        <!-- Instructions Accordion - Only visible when v-select is open -->
                        <transition
                            enter-active-class="transition-all duration-300 ease-out"
                            enter-from-class="opacity-0 transform scale-95 -translate-y-2"
                            enter-to-class="opacity-100 transform scale-100 translate-y-0"
                            leave-active-class="transition-all duration-200 ease-in"
                            leave-from-class="opacity-100 transform scale-100 translate-y-0"
                            leave-to-class="opacity-0 transform scale-95 -translate-y-2"
                        >
                            <div
                                class="mt-3 rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-800/50 dark:bg-blue-900/20"
                            >
                                <!-- Create Assistant Section -->
                                <div
                                    class="border-b border-blue-200 dark:border-blue-700 last:border-b-0"
                                >
                                    <button
                                        @click="
                                            toggleInstructionSection(
                                                'createAssistant'
                                            )
                                        "
                                        class="flex w-full items-center justify-between p-3 text-left hover:bg-blue-100 dark:hover:bg-blue-800/30 transition-colors duration-150"
                                    >
                                        <div class="flex items-center">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="mr-2 h-4 w-4 text-blue-500 dark:text-blue-400 flex-shrink-0"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                                ></path>
                                                <circle
                                                    cx="9"
                                                    cy="7"
                                                    r="4"
                                                ></circle>
                                                <path
                                                    d="M22 21v-2a4 4 0 0 0-3-3.87"
                                                ></path>
                                                <path
                                                    d="M16 3.13a4 4 0 0 1 0 7.75"
                                                ></path>
                                            </svg>
                                            <span
                                                class="text-xs font-semibold text-blue-700 dark:text-blue-300"
                                                >Create Assistant</span
                                            >
                                        </div>
                                        <svg
                                            :class="[
                                                'h-4 w-4 text-blue-500 dark:text-blue-400 transition-transform duration-200',
                                                instructionSections.createAssistant
                                                    ? 'rotate-180'
                                                    : '',
                                            ]"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 9l-7 7-7-7"
                                            ></path>
                                        </svg>
                                    </button>
                                    <div
                                        v-if="
                                            instructionSections.createAssistant
                                        "
                                        class="px-3 pb-3 text-xs text-blue-700 dark:text-blue-300"
                                    >
                                        <ul class="space-y-1 leading-relaxed">
                                            <li>
                                                <strong
                                                    >1. Select AI Model:</strong
                                                >
                                                Choose your preferred AI
                                                assistant from the dropdown
                                            </li>
                                            <li>
                                                <strong
                                                    >2. Configure
                                                    Settings:</strong
                                                >
                                                Set up assistant instructions
                                                and temperature
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Upload Documentation Section -->
                                <div
                                    class="border-b border-blue-200 dark:border-blue-700 last:border-b-0"
                                >
                                    <button
                                        @click="
                                            toggleInstructionSection(
                                                'uploadDocs'
                                            )
                                        "
                                        class="flex w-full items-center justify-between p-3 text-left hover:bg-blue-100 dark:hover:bg-blue-800/30 transition-colors duration-150"
                                    >
                                        <div class="flex items-center">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="mr-2 h-4 w-4 text-blue-500 dark:text-blue-400 flex-shrink-0"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                                                ></path>
                                                <polyline
                                                    points="14,2 14,8 20,8"
                                                ></polyline>
                                                <line
                                                    x1="16"
                                                    y1="13"
                                                    x2="8"
                                                    y2="13"
                                                ></line>
                                                <line
                                                    x1="16"
                                                    y1="17"
                                                    x2="8"
                                                    y2="17"
                                                ></line>
                                                <polyline
                                                    points="10,9 9,9 8,9"
                                                ></polyline>
                                            </svg>
                                            <span
                                                class="text-xs font-semibold text-blue-700 dark:text-blue-300"
                                                >Upload Documentation</span
                                            >
                                        </div>
                                        <svg
                                            :class="[
                                                'h-4 w-4 text-blue-500 dark:text-blue-400 transition-transform duration-200',
                                                instructionSections.uploadDocs
                                                    ? 'rotate-180'
                                                    : '',
                                            ]"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 9l-7 7-7-7"
                                            ></path>
                                        </svg>
                                    </button>
                                    <div
                                        v-if="instructionSections.uploadDocs"
                                        class="px-3 pb-3 text-xs text-blue-700 dark:text-blue-300"
                                    >
                                        <ul class="space-y-1 leading-relaxed">
                                            <li>
                                                <strong
                                                    >• Supported
                                                    Formats:</strong
                                                >
                                                PDF, DOC, TXT, MD files
                                            </li>
                                            <li>
                                                <strong>• File Size:</strong>
                                                Maximum 10MB per document
                                            </li>
                                            <li>
                                                <strong
                                                    >• Knowledge Base:</strong
                                                >
                                                Documents train your assistant's
                                                responses
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Sync Documentation Section -->
                                <div
                                    class="border-b border-blue-200 dark:border-blue-700 last:border-b-0"
                                >
                                    <button
                                        @click="
                                            toggleInstructionSection('syncDocs')
                                        "
                                        class="flex w-full items-center justify-between p-3 text-left hover:bg-blue-100 dark:hover:bg-blue-800/30 transition-colors duration-150 rounded-b-lg"
                                    >
                                        <div class="flex items-center">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="mr-2 h-4 w-4 text-blue-500 dark:text-blue-400 flex-shrink-0"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <polyline
                                                    points="23 4 23 10 17 10"
                                                ></polyline>
                                                <polyline
                                                    points="1 20 1 14 7 14"
                                                ></polyline>
                                                <path
                                                    d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"
                                                ></path>
                                            </svg>
                                            <span
                                                class="text-xs font-semibold text-blue-700 dark:text-blue-300"
                                                >Sync Documentation</span
                                            >
                                        </div>
                                        <svg
                                            :class="[
                                                'h-4 w-4 text-blue-500 dark:text-blue-400 transition-transform duration-200',
                                                instructionSections.syncDocs
                                                    ? 'rotate-180'
                                                    : '',
                                            ]"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 9l-7 7-7-7"
                                            ></path>
                                        </svg>
                                    </button>
                                    <div
                                        v-if="instructionSections.syncDocs"
                                        class="px-3 pb-3 text-xs text-blue-700 dark:text-blue-300"
                                    >
                                        <ul class="space-y-1 leading-relaxed">
                                            <li>
                                                <strong>• Auto-Sync:</strong>
                                                Keep documentation updated by
                                                cron job
                                            </li>

                                            <li>
                                                <strong
                                                    >• Real-time
                                                    Updates:</strong
                                                >
                                                Assistant learns from latest
                                                content
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
