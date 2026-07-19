<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Handle, useVueFlow, useNode } from "@vue-flow/core";
const { removeNodes, nodes, addNodes } = useVueFlow();

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

// Add emit to communicate with parent component
const emit = defineEmits(["update:isValid"]);
const { toObject, edges } = useVueFlow();

const isExpanded = ref(true);
const showCharacterWarning = ref(false);
const isValid = ref(false);
const node = useNode();
const mergeFields = ref([]);

const output = ref(props.data.output?.[0] || { reply_text: "" });
const message = ref(output.value.reply_text || "");

function getMergeFields(chatType) {
    fetch(`/${tenantSubdomain}/load-mergefields/${chatType}`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },
        body: JSON.stringify({ type: chatType }),
    })
        .then((response) => response.json())

        .then((data) => {
            if (Array.isArray(data)) {
                mergeFields.value = data;
            } else {
                const errorMessage = "Expected an array but got unexpected data.";
                showNotification(errorMessage, "danger");
                mergeFields.value = [];
            }
        })
        .catch((error) => {
            const errorMessage = error.message || "An error occurred while fetching merge fields.";
            showNotification(errorMessage, "danger");
        });
}

let tributeInstance = null;

function handleTributeEvent() {
    setTimeout(() => {
        if (typeof window.Tribute === "undefined") {
            console.warn("Tribute.js is not loaded.");
            return;
        }

        if (!tributeInstance) {
            tributeInstance = new window.Tribute({
                trigger: "@",
                values: mergeFields.value,
            });
        }
        const elements = document.querySelectorAll(".mentionable");
        elements.forEach((el, index) => {
            if (el.getAttribute("data-tribute") !== "true") {
                tributeInstance.attach(el);
                el.setAttribute("data-tribute", "true");
            }
        });
    }, 1000);
}

function findRelationTypeFromSource() {
    const allNodes = toObject().nodes;
    // Find the specific node by ID or type (customize this condition as needed)
    const sourceNode = allNodes.find((node) => node.type === "trigger");
    if (
        sourceNode &&
        sourceNode.data.output &&
        sourceNode.data.output.length > 0
    ) {
        const relationType = sourceNode.data.output[0].rel_type;
        getMergeFields(relationType);
    }
}

// In your setup function, add robust validation and connection tracking
function validateNode() {
    // Check if message is empty or only whitespace
    const isMessageValid = message.value.trim().length > 0;

    // Check if message exceeds character limit
    const isWithinCharLimit = message.value.length <= 1000;

    // Node is valid if message is not empty and within character limit
    isValid.value = isMessageValid && isWithinCharLimit;

    // Update node data with validation state
    props.data.isValid = isValid.value;
    props.data.errorMessage = !isMessageValid
        ? "Message text is required"
        : !isWithinCharLimit
        ? "Message exceeds 1000 character limit"
        : "";

    // Always emit validation status to parent
    emit("update:isValid", isValid.value);

    return isValid.value;
}

function updateNodeData() {
    // Update the output array in WhatsMark format
    props.data.output = [
        {
            reply_text: message.value,
        },
    ];

    // Check if we should show character warning
    if (characterCount.value > 1000) {
        showCharacterWarning.value = true;
        // Auto-hide after 3 seconds
        setTimeout(() => {
            showCharacterWarning.value = false;
        }, 3000);
    } else {
        showCharacterWarning.value = false;
    }

    // Validate node after updating data
    validateNode();
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

// Calculate character count
const characterCount = computed(() => {
    return message.value.length;
});

// Determine character count status
const countStatus = computed(() => {
    if (characterCount.value > 1000) return "text-danger-500"; // Over limit
    if (characterCount.value > 800) return "text-warning-500"; // Approaching limit
    return "text-gray-400"; // Normal
});

// Set node styling based on selection and validation state
const nodeClasses = computed(() => {
    return `flow-node text-message-node relative ${
        props.selected ? "selected" : ""
    } ${
        !isValid.value
            ? "border-danger-300 shadow-danger-100 dark:border-danger-700 dark:shadow-danger-900/30"
            : ""
    } transition-all duration-200`;
});

// Watch edges for changes to connections
watch(
    edges,
    () => {
        validateNode(); // Re-validate when edges change

        // Explicitly emit the validation state to ensure parent component is aware
        emit("update:isValid", isValid.value);
    },
    { deep: true }
);

// Close menus if clicked outside
onMounted(() => {
    // Validate on mount
    validateNode();

    findRelationTypeFromSource();
});
</script>

<template>
    <div class="h-full w-full">
        <Handle
            type="target"
            position="left"
            :class="[
                '!h-4 !w-4 !border-2 !border-white z-10',
                isValid ? '!bg-info-500' : '!bg-danger-500',
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
                        ? 'bg-gradient-to-r from-info-500 to-sky-500'
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
                                    ? 'bg-info-100 text-info-600 dark:bg-info-900/50 dark:text-info-300'
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
                                <path
                                    d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                                ></path>
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span
                                class="text-sm font-medium text-gray-800 dark:text-gray-200"
                                >{{ data.label || "Text Message" }}</span
                            >
                            <span
                                v-if="!isValid"
                                class="text-xs text-danger-500 dark:text-danger-400"
                                >Required field</span
                            >
                        </div>
                    </div>

                    <div class="node-actions flex space-x-1">
                        <button
                            @click="toggleExpand"
                            class="node-action-btn transform rounded-md border border-transparent bg-white p-1.5 text-gray-500 shadow-sm transition-all duration-300 ease-in-out hover:scale-105 hover:border-gray-200 hover:bg-gray-50 hover:text-info-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-info-400"
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
                            class="node-action-btn transform rounded-md border border-transparent bg-white p-1.5 text-gray-500 shadow-sm transition-all duration-300 ease-in-out hover:scale-105 hover:border-gray-200 hover:bg-gray-50 hover:text-info-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-gray-600 dark:hover:text-info-400"
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
                        {{
                            props.data.errorMessage ||
                            "Message text is required"
                        }}
                    </div>
                </div>

                <!-- Node Content -->
                <div v-show="isExpanded" class="node-content space-y-4">
                    <!-- Character Warning Alert -->
                    <div
                        v-if="showCharacterWarning"
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
                            Message exceeds 1000 character limit
                        </div>
                    </div>

                    <div class="node-field">
                        <label
                            class="node-field-label mb-1.5 flex items-center text-xs font-medium"
                            :class="[
                                isValid
                                    ? 'text-gray-700 dark:text-gray-300'
                                    : 'text-danger-600 dark:text-danger-400',
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
                                    isValid
                                        ? 'text-info-500'
                                        : 'text-danger-500',
                                ]"
                            >
                                <path
                                    d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                                ></path>
                            </svg>
                            Message Text
                            <span class="ml-1 text-danger-500">*</span>
                        </label>
                        <textarea
                            :id="`message-${id}`"
                            v-model="message"
                            @input="updateNodeData"
                            @focus="handleTributeEvent"
                            class="mentionable block w-full resize-none rounded-md bg-white px-3 py-2 text-sm caret-info-500 shadow-sm focus:ring focus:ring-opacity-50 dark:bg-gray-700 dark:text-gray-200"
                            :class="{
                                'border-danger-300 caret-danger-500 focus:border-danger-500 focus:ring-danger-500 dark:border-danger-700':
                                    !isValid || characterCount > 1000,
                                'border-gray-300 caret-info-500 focus:border-info-500 focus:ring-info-200 dark:border-gray-600':
                                    isValid && characterCount <= 1000,
                            }"
                            placeholder="Enter your message text here..."
                            rows="5"
                        ></textarea>

                        <div
                            class="mt-3 flex items-center justify-between text-xs"
                        >
                            <div class="flex items-center">
                                <span
                                    :class="countStatus"
                                    class="font-mono dark:text-gray-300"
                                >
                                    {{ characterCount }}/1000
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
