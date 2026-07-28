<script setup>
import { ref, computed, onMounted } from "vue";
import { Handle, Position } from "@vue-flow/core";

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

const condition = ref(props.data.condition || { type: "contains", value: "" });
const isExpanded = ref(true);
const isVariableMenuOpen = ref(false);

// Available condition types
const conditionTypes = [
    {
        id: "contains",
        label: "Contains",
        description: "Checks if the message contains the specified text",
    },
    {
        id: "equals",
        label: "Equals",
        description: "Checks if the message exactly matches the specified text",
    },
    {
        id: "startsWith",
        label: "Starts With",
        description: "Checks if the message begins with the specified text",
    },
    {
        id: "endsWith",
        label: "Ends With",
        description: "Checks if the message ends with the specified text",
    },
    {
        id: "regex",
        label: "Regex Match",
        description: "Matches the message against a regular expression pattern",
    },
];

// Available variables for conditions
const availableVariables = [
    { label: "Customer Name", value: "{{customer_name}}" },
    { label: "Customer Phone", value: "{{customer_phone}}" },
    { label: "Last Message", value: "{{last_message}}" },
    { label: "Message Type", value: "{{message_type}}" },
];

function updateNodeData() {
    props.data.condition = condition.value;
}

function toggleExpand() {
    isExpanded.value = !isExpanded.value;
}

function toggleVariableMenu() {
    isVariableMenuOpen.value = !isVariableMenuOpen.value;
}

function insertVariable(variable) {
    condition.value.value += variable;
    updateNodeData();
    isVariableMenuOpen.value = false;
}

// Selected condition type
const selectedConditionType = computed(() => {
    return (
        conditionTypes.find((type) => type.id === condition.value.type) ||
        conditionTypes[0]
    );
});

// Example formatter for the condition preview
function formatExample() {
    const value = condition.value.value || "[value]";

    switch (condition.value.type) {
        case "contains":
            return `Message contains "${value}"`;
        case "equals":
            return `Message equals "${value}"`;
        case "startsWith":
            return `Message starts with "${value}"`;
        case "endsWith":
            return `Message ends with "${value}"`;
        case "regex":
            return `Regex pattern: "${value}"`;
        default:
            return "Configure condition";
    }
}

const nodeClasses = computed(() => {
    return `flow-node condition-node relative ${
        props.selected ? "selected" : ""
    } transition-all duration-200`;
});

// Close variable menu if clicked outside
onMounted(() => {
    document.addEventListener("click", (event) => {
        const variableMenu = document.getElementById(
            `variable-menu-${props.id}`
        );
        const variableButton = document.getElementById(
            `variable-btn-${props.id}`
        );

        if (
            variableMenu &&
            variableButton &&
            !variableMenu.contains(event.target) &&
            !variableButton.contains(event.target)
        ) {
            isVariableMenuOpen.value = false;
        }
    });
});
</script>

<template>
    <div :class="nodeClasses" style="min-width: 280px; max-width: 320px">
        <!-- Node type indicator -->
        <div class="node-type-indicator"></div>

        <!-- Connection Handles -->
        <Handle type="target" position="top" class="h-2 w-2" />

        <div class="node-container">
            <!-- Node Header -->
            <div class="node-header">
                <div class="node-title">
                    <div class="node-icon bg-warning-100 text-warning-600">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-3.5 w-3.5"
                        >
                            <path
                                d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"
                            ></path>
                        </svg>
                    </div>
                    <span>{{ data.label }}</span>
                </div>

                <div class="node-actions">
                    <button
                        @click="toggleExpand"
                        class="node-action-btn"
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
                            class="h-4 w-4"
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
                            class="h-4 w-4"
                        >
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <div class="flex items-end">
                        <button
                            @click="$emit('copy', id)"
                            class="node-action-btn"
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
                            @click="$emit('delete', id)"
                            class="node-action-btn delete-btn"
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
            </div>

            <!-- Node Content -->
            <div v-show="isExpanded" class="node-content">
                <!-- Condition preview -->
                <div
                    class="mb-3 flex items-center rounded-md border border-warning-100 bg-warning-50 p-2 text-sm text-warning-700"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="mr-1 h-4 w-4 text-warning-500"
                    >
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span>{{ formatExample() }}</span>
                </div>

                <!-- Condition Type -->
                <div class="node-field">
                    <label class="node-field-label">Condition Type</label>
                    <select
                        v-model="condition.type"
                        @change="updateNodeData"
                        class="node-input"
                    >
                        <option
                            v-for="type in conditionTypes"
                            :key="type.id"
                            :value="type.id"
                        >
                            {{ type.label }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ selectedConditionType.description }}
                    </p>
                </div>

                <!-- Condition Value -->
                <div class="node-field">
                    <label class="node-field-label">Value</label>
                    <div class="relative">
                        <input
                            v-model="condition.value"
                            @input="updateNodeData"
                            class="node-input pr-10"
                            placeholder="Enter condition value..."
                        />
                        <div class="absolute inset-y-0 right-0 flex pr-2">
                            <button
                                :id="`variable-btn-${id}`"
                                @click.stop="toggleVariableMenu"
                                class="flex h-full items-center text-gray-500 hover:text-info-600"
                                title="Insert variable"
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
                                    <path d="M4 4h16v16H4z"></path>
                                    <path d="M4 12h16"></path>
                                    <path d="M12 4v16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Variable Menu -->
                    <div
                        v-show="isVariableMenuOpen"
                        :id="`variable-menu-${id}`"
                        class="absolute right-0 z-10 mt-1 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5"
                    >
                        <div class="py-1">
                            <div
                                v-for="variable in availableVariables"
                                :key="variable.value"
                                @click="insertVariable(variable.value)"
                                class="flex cursor-pointer items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                            >
                                {{ variable.label }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Handle for the YES branch -->
        <div
            class="absolute -bottom-7 left-1/2 z-10 flex -translate-x-1/2 transform flex-col items-center"
        >
            <Handle
                type="source"
                position="bottom"
                id="yes"
                class="h-2 w-2 border-success-500 bg-white"
            />
            <div
                class="mt-1 rounded bg-success-100 px-2 py-0.5 text-xs font-medium text-success-700"
            >
                Yes
            </div>
        </div>

        <!-- Handle for the NO branch -->
        <div
            class="absolute -right-7 top-1/2 z-10 flex -translate-y-1/2 transform items-center"
        >
            <Handle
                type="source"
                position="right"
                id="no"
                class="h-2 w-2 border-danger-500 bg-white"
            />
            <div
                class="ml-1 rounded bg-danger-100 px-2 py-0.5 text-xs font-medium text-danger-700"
            >
                No
            </div>
        </div>
    </div>
</template>
