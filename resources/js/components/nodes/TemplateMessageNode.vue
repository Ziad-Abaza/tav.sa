<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Handle, useVueFlow, useNode } from "@vue-flow/core";
const { removeNodes, nodes, addNodes } = useVueFlow();
const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

const templateId = ref(props.data.templateId || "");
const templates = ref([]);
const selectedTemplate = ref(null);
const params = ref(props.data.params || {});
const isExpanded = ref(true);
const isLoading = ref(false);
const loadingError = ref("");
const availableVars = ref([]);
const node = useNode();
// Fetch available templates
onMounted(async () => {
    try {
        await loadTemplates();
        await loadAvailableVariables();
    } catch (error) {
        console.error("Error initializing template node:", error);
    }
});

async function loadTemplates() {
    try {
        isLoading.value = true;
        loadingError.value = "";

        const response = await fetch(
            `/${tenantSubdomain}/get-whatsapp-templates`
        );
        const data = await response.json();

        if (data.success) {
            templates.value = data.templates;

            if (templateId.value) {
                selectedTemplate.value =
                    templates.value.find((t) => t.id === templateId.value) ||
                    null;
                setupParamsFromTemplate();
            }
        } else {
            loadingError.value = data.message || "Failed to load templates";
        }
    } catch (error) {
        console.error("Error fetching templates:", error);
        loadingError.value = "Error loading templates. Please try again.";
    } finally {
        isLoading.value = false;
    }
}

async function loadAvailableVariables() {
    try {
        // Fetch available merge field variables for templates
        const response = await fetch(
            `/${tenantSubdomain}load-merge-fields/template`
        );
        const data = await response.json();
        if (Array.isArray(data)) {
            availableVars.value = data;
        }
    } catch (error) {
        console.error("Error fetching merge fields:", error);
    }
}

function onTemplateChange(e) {
    templateId.value = e.target.value;
    selectedTemplate.value =
        templates.value.find((t) => t.id === templateId.value) || null;
    setupParamsFromTemplate();
    updateNodeData();
}

function setupParamsFromTemplate() {
    if (!selectedTemplate.value) {
        params.value = {};
        return;
    }

    // Initialize params based on template structure
    const newParams = {};

    if (selectedTemplate.value.header_params_count) {
        newParams.header = Array(
            selectedTemplate.value.header_params_count
        ).fill("");
    }

    if (selectedTemplate.value.body_params_count) {
        newParams.body = Array(selectedTemplate.value.body_params_count).fill(
            ""
        );
    }

    if (selectedTemplate.value.footer_params_count) {
        newParams.footer = Array(
            selectedTemplate.value.footer_params_count
        ).fill("");
    }

    params.value = newParams;
}

function updateParam(section, index, value) {
    params.value[section][index] = value;
    updateNodeData();
}

function insertVariable(section, index, variable) {
    params.value[section][index] = variable;
    updateNodeData();
}

function updateNodeData() {
    props.data.templateId = templateId.value;
    props.data.params = params.value;
}

function toggleExpand() {
    isExpanded.value = !isExpanded.value;
}

const templatePreview = computed(() => {
    if (!selectedTemplate.value) return null;

    return {
        header: selectedTemplate.value.header_data_text || "",
        body: selectedTemplate.value.body_data || "",
        footer: selectedTemplate.value.footer_data || "",
        format: selectedTemplate.value.header_data_format || "TEXT",
    };
});
function handleClickDelete() {
    removeNodes(node.id);
}

function handleClickDuplicate() {
    const { type, position, data } = node.node;

    const newNode = {
        id: (nodes.value.length + 1).toString(),
        type,
        position: {
            x: position.x - 100,
            y: position.y - 100,
        },
        data: JSON.parse(JSON.stringify(data)), // Deep copy to prevent shared reference
    };

    addNodes(newNode);
}
const isValid = computed(() => {
    // Check if template is selected
    if (!selectedTemplate.value) return false;

    // Check if all required parameters are filled
    const checkParams = (section) => {
        if (!params.value[section]) return true;
        return params.value[section].every((param) => param.trim() !== "");
    };

    return (
        checkParams("header") && checkParams("body") && checkParams("footer")
    );
});

const nodeClasses = computed(() => {
    return `flow-node template-message-node relative ${
        props.selected ? "selected" : ""
    } ${
        !isValid.value && selectedTemplate.value ? "border-danger-300" : ""
    } transition-all duration-200`;
});

const hasHeaderParams = computed(
    () => params.value.header && params.value.header.length > 0
);
const hasBodyParams = computed(
    () => params.value.body && params.value.body.length > 0
);
const hasFooterParams = computed(
    () => params.value.footer && params.value.footer.length > 0
);
</script>

<template>
    <div class="h-full w-full">
        <!-- Connection Handles -->
        <Handle
            type="target"
            position="top"
            class="!h-3 !w-3 !border-2 !border-white !bg-gradient-to-r !from-success-500 !to-teal-500 !shadow-md !transition-transform !duration-300 hover:!scale-110 dark:!border-gray-800"
            style="top: -5px"
        />
        <div
            :class="[
                nodeClasses,
                'bg-white dark:bg-gray-800 shadow-lg transition-all duration-200 hover:shadow-xl rounded-lg overflow-hidden border-2',
            ]"
            style="min-width: 300px; max-width: 400px"
        >
            <!-- Node type indicator - gradient bar -->
            <div
                class="h-1.5 bg-gradient-to-r from-success-500 to-teal-500"
            ></div>

            <div class="node-container p-4">
                <!-- Node Header -->
                <div class="node-header flex items-center justify-between mb-3">
                    <div class="node-title flex items-center">
                        <div
                            class="node-icon bg-success-100 text-success-600 p-2 rounded-lg mr-3 shadow-sm dark:bg-success-900 dark:text-success-300"
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
                                    y="4"
                                    width="18"
                                    height="18"
                                    rx="2"
                                    ry="2"
                                ></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                        <span
                            class="font-medium text-gray-800 text-sm dark:text-gray-200"
                            >{{ data.label }}</span
                        >
                    </div>

                    <div class="node-actions flex space-x-1">
                        <button
                            @click="toggleExpand"
                            class="node-action-btn p-1.5 rounded-md bg-white hover:bg-gray-50 text-gray-500 hover:text-success-600 transition-all duration-300 ease-in-out transform hover:scale-105 border border-transparent hover:border-gray-200 shadow-sm dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300 dark:hover:text-success-400 dark:hover:border-gray-600"
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
                            class="node-action-btn p-1.5 rounded-md bg-white hover:bg-gray-50 text-gray-500 hover:text-success-600 transition-all duration-300 ease-in-out transform hover:scale-105 border border-transparent hover:border-gray-200 shadow-sm dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300 dark:hover:text-success-400 dark:hover:border-gray-600"
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
                            class="node-action-btn p-1.5 rounded-md bg-white hover:bg-danger-50 text-gray-500 hover:text-danger-600 transition-all duration-300 ease-in-out transform hover:scale-105 border border-transparent hover:border-danger-200 shadow-sm dark:bg-gray-700 dark:hover:bg-danger-900/30 dark:text-gray-300 dark:hover:text-danger-400 dark:hover:border-danger-800/50"
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

                <!-- Node Content -->
                <div v-show="isExpanded" class="node-content space-y-4">
                    <!-- Error Alert -->
                    <div
                        v-if="loadingError"
                        class="mb-4 flex items-center rounded-md border border-danger-200 bg-danger-50 p-3 text-sm text-danger-600 dark:border-danger-800/50 dark:bg-danger-900/30 dark:text-danger-400"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="mr-2 h-5 w-5 text-danger-500"
                        >
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        {{ loadingError }}
                    </div>

                    <!-- Loading state -->
                    <div v-if="isLoading" class="my-4 flex justify-center">
                        <svg
                            class="h-6 w-6 animate-spin text-success-500"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                    </div>

                    <!-- Template selection -->
                    <div class="node-field">
                        <label
                            class="node-field-label mb-1.5 flex items-center text-xs font-medium text-gray-700 dark:text-gray-300"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="mr-1.5 h-3.5 w-3.5 text-success-500"
                            >
                                <path
                                    d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                                ></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                            WhatsApp Template
                        </label>
                        <div class="relative">
                            <select
                                v-model="templateId"
                                @change="onTemplateChange"
                                class="block w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-success-500 focus:ring focus:ring-success-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                :disabled="isLoading"
                            >
                                <option value="" disabled>
                                    Select template
                                </option>
                                <option
                                    v-for="template in templates"
                                    :key="template.id"
                                    :value="template.id"
                                >
                                    {{ template.template_name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Template Preview -->
                    <div v-if="selectedTemplate" class="mb-3 mt-4">
                        <div
                            class="node-field-label mb-1.5 flex items-center text-xs font-medium text-gray-700 dark:text-gray-300"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="mr-1.5 h-3.5 w-3.5 text-success-500"
                            >
                                <rect
                                    x="2"
                                    y="3"
                                    width="20"
                                    height="14"
                                    rx="2"
                                    ry="2"
                                ></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                            Template Preview
                        </div>
                        <div
                            class="mt-1 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300"
                        >
                            <!-- Header -->
                            <div
                                v-if="templatePreview.header"
                                class="mb-2 font-medium"
                            >
                                {{ templatePreview.header }}
                                <span
                                    v-if="selectedTemplate.header_params_count"
                                    class="ml-1 text-xs font-normal text-success-500 dark:text-success-400"
                                >
                                    ({{
                                        selectedTemplate.header_params_count
                                    }}
                                    parameter<span
                                        v-if="
                                            selectedTemplate.header_params_count >
                                            1
                                        "
                                        >s</span
                                    >)
                                </span>
                            </div>

                            <!-- Body -->
                            <div
                                v-if="templatePreview.body"
                                class="mb-2 text-sm"
                            >
                                {{ templatePreview.body }}
                                <span
                                    v-if="selectedTemplate.body_params_count"
                                    class="ml-1 text-xs font-normal text-success-500 dark:text-success-400"
                                >
                                    ({{
                                        selectedTemplate.body_params_count
                                    }}
                                    parameter<span
                                        v-if="
                                            selectedTemplate.body_params_count >
                                            1
                                        "
                                        >s</span
                                    >)
                                </span>
                            </div>

                            <!-- Footer -->
                            <div
                                v-if="templatePreview.footer"
                                class="text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ templatePreview.footer }}
                                <span
                                    v-if="selectedTemplate.footer_params_count"
                                    class="ml-1 text-xs font-normal text-success-500 dark:text-success-400"
                                >
                                    ({{
                                        selectedTemplate.footer_params_count
                                    }}
                                    parameter<span
                                        v-if="
                                            selectedTemplate.footer_params_count >
                                            1
                                        "
                                        >s</span
                                    >)
                                </span>
                            </div>

                            <!-- Format Type Badge -->
                            <div class="mt-2 flex">
                                <span
                                    class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-medium text-success-800 shadow-sm dark:bg-success-900/50 dark:text-success-300"
                                >
                                    <span
                                        class="mr-1 h-1.5 w-1.5 rounded-full bg-success-500"
                                    ></span>
                                    {{ templatePreview.format }} Template
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Parameter inputs -->
                    <div
                        v-if="selectedTemplate"
                        class="params-container space-y-4"
                    >
                        <!-- Header parameters -->
                        <div v-if="hasHeaderParams" class="section">
                            <div
                                class="node-field-label mb-1.5 flex items-center text-xs font-medium text-gray-700 dark:text-gray-300"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="mr-1.5 h-3.5 w-3.5 text-success-500"
                                >
                                    <path
                                        d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"
                                    ></path>
                                    <polyline points="16 6 12 2 8 6"></polyline>
                                    <line x1="12" y1="2" x2="12" y2="15"></line>
                                </svg>
                                Header Parameters
                            </div>
                            <div
                                v-for="(param, index) in params.header"
                                :key="`header-${index}`"
                                class="param-input mt-1.5"
                            >
                                <div class="flex">
                                    <input
                                        :value="param"
                                        @input="
                                            (e) =>
                                                updateParam(
                                                    'header',
                                                    index,
                                                    e.target.value
                                                )
                                        "
                                        class="block w-full rounded-l-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-success-500 focus:ring focus:ring-success-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                        :class="{
                                            'border-danger-300 dark:border-danger-700':
                                                param.trim() === '' &&
                                                selectedTemplate,
                                        }"
                                        :placeholder="`Header parameter ${
                                            index + 1
                                        }`"
                                    />
                                    <div class="relative">
                                        <button
                                            @click="$event.stopPropagation()"
                                            class="flex items-center justify-center rounded-r-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500"
                                            title="Insert variable"
                                            :id="`variable-btn-header-${index}`"
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

                                        <!-- Variable dropdown -->
                                        <div
                                            class="absolute right-0 z-10 mt-1 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 dark:bg-gray-700 dark:ring-gray-600"
                                            :id="`variable-menu-header-${index}`"
                                            style="display: none"
                                        >
                                            <div
                                                v-for="variable in availableVars"
                                                :key="variable.value"
                                                class="cursor-pointer px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600"
                                                @click="
                                                    insertVariable(
                                                        'header',
                                                        index,
                                                        variable.value
                                                    )
                                                "
                                            >
                                                {{ variable.key }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Body parameters -->
                        <div v-if="hasBodyParams" class="section">
                            <div
                                class="node-field-label mb-1.5 flex items-center text-xs font-medium text-gray-700 dark:text-gray-300"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="mr-1.5 h-3.5 w-3.5 text-success-500"
                                >
                                    <path
                                        d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                                    ></path>
                                </svg>
                                Body Parameters
                            </div>
                            <div
                                v-for="(param, index) in params.body"
                                :key="`body-${index}`"
                                class="param-input mt-1.5"
                            >
                                <div class="flex">
                                    <input
                                        :value="param"
                                        @input="
                                            (e) =>
                                                updateParam(
                                                    'body',
                                                    index,
                                                    e.target.value
                                                )
                                        "
                                        class="block w-full rounded-l-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-success-500 focus:ring focus:ring-success-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                        :class="{
                                            'border-danger-300 dark:border-danger-700':
                                                param.trim() === '' &&
                                                selectedTemplate,
                                        }"
                                        :placeholder="`Body parameter ${
                                            index + 1
                                        }`"
                                    />
                                    <div class="relative">
                                        <button
                                            @click="$event.stopPropagation()"
                                            class="flex items-center justify-center rounded-r-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500"
                                            title="Insert variable"
                                            :id="`variable-btn-body-${index}`"
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

                                        <!-- Variable dropdown -->
                                        <div
                                            class="absolute right-0 z-10 mt-1 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 dark:bg-gray-700 dark:ring-gray-600"
                                            :id="`variable-menu-body-${index}`"
                                            style="display: none"
                                        >
                                            <div
                                                v-for="variable in availableVars"
                                                :key="variable.value"
                                                class="cursor-pointer px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600"
                                                @click="
                                                    insertVariable(
                                                        'body',
                                                        index,
                                                        variable.value
                                                    )
                                                "
                                            >
                                                {{ variable.key }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer parameters -->
                        <div v-if="hasFooterParams" class="section">
                            <div
                                class="node-field-label mb-1.5 flex items-center text-xs font-medium text-gray-700 dark:text-gray-300"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="mr-1.5 h-3.5 w-3.5 text-success-500"
                                >
                                    <line x1="8" y1="19" x2="8" y2="21"></line>
                                    <line x1="8" y1="13" x2="8" y2="17"></line>
                                    <line
                                        x1="16"
                                        y1="19"
                                        x2="16"
                                        y2="21"
                                    ></line>
                                    <line
                                        x1="16"
                                        y1="13"
                                        x2="16"
                                        y2="17"
                                    ></line>
                                    <line
                                        x1="12"
                                        y1="21"
                                        x2="12"
                                        y2="23"
                                    ></line>
                                    <line
                                        x1="12"
                                        y1="15"
                                        x2="12"
                                        y2="19"
                                    ></line>
                                    <path
                                        d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25"
                                    ></path>
                                </svg>
                                Footer Parameters
                            </div>
                            <div
                                v-for="(param, index) in params.footer"
                                :key="`footer-${index}`"
                                class="param-input mt-1.5"
                            >
                                <div class="flex">
                                    <input
                                        :value="param"
                                        @input="
                                            (e) =>
                                                updateParam(
                                                    'footer',
                                                    index,
                                                    e.target.value
                                                )
                                        "
                                        class="block w-full rounded-l-md border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-success-500 focus:ring focus:ring-success-200 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                        :class="{
                                            'border-danger-300 dark:border-danger-700':
                                                param.trim() === '' &&
                                                selectedTemplate,
                                        }"
                                        :placeholder="`Footer parameter ${
                                            index + 1
                                        }`"
                                    />
                                    <div class="relative">
                                        <button
                                            @click="$event.stopPropagation()"
                                            class="flex items-center justify-center rounded-r-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500"
                                            title="Insert variable"
                                            :id="`variable-btn-footer-${index}`"
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

                                        <!-- Variable dropdown -->
                                        <div
                                            class="absolute right-0 z-10 mt-1 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 dark:bg-gray-700 dark:ring-gray-600"
                                            :id="`variable-menu-footer-${index}`"
                                            style="display: none"
                                        >
                                            <div
                                                v-for="variable in availableVars"
                                                :key="variable.value"
                                                class="cursor-pointer px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600"
                                                @click="
                                                    insertVariable(
                                                        'footer',
                                                        index,
                                                        variable.value
                                                    )
                                                "
                                            >
                                                {{ variable.key }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Template Selected State -->
                    <div
                        v-if="
                            !selectedTemplate &&
                            !isLoading &&
                            templates.length > 0
                        "
                        class="mt-5 rounded-md bg-gray-50 py-6 text-center dark:bg-gray-800"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="mx-auto mb-3 h-10 w-10 text-gray-400 dark:text-gray-500"
                        >
                            <rect
                                x="3"
                                y="4"
                                width="18"
                                height="18"
                                rx="2"
                                ry="2"
                            ></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Select a template to configure parameters
                        </p>
                    </div>

                    <!-- No Templates Available -->
                    <div
                        v-if="
                            !isLoading &&
                            templates.length === 0 &&
                            !loadingError
                        "
                        class="mt-5 rounded-md bg-gray-50 py-6 text-center dark:bg-gray-800"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="mx-auto mb-3 h-10 w-10 text-gray-400 dark:text-gray-500"
                        >
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M8 15h8M9 9h.01M15 9h.01"></path>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No templates found. Templates must be created in
                            your WhatsApp Business Account.
                        </p>
                    </div>

                    <!-- Validation warning -->
                    <div
                        v-if="!isValid && selectedTemplate"
                        class="mt-4 rounded-md bg-danger-50 p-3 text-sm text-danger-600 border border-danger-200 dark:bg-danger-900/30 dark:text-danger-400 dark:border-danger-800/50"
                    >
                        <div class="flex">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5 mr-2 text-danger-500"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            Please fill all required template parameters.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    mounted() {
        // Add event listeners for variable dropdowns after component is mounted
        this.$nextTick(() => {
            // For each parameter type
            ["header", "body", "footer"].forEach((section) => {
                if (this.params[section]) {
                    // For each parameter in this section
                    this.params[section].forEach((_, index) => {
                        const btn = document.getElementById(
                            `variable-btn-${section}-${index}`
                        );
                        const menu = document.getElementById(
                            `variable-menu-${section}-${index}`
                        );

                        if (btn && menu) {
                            btn.addEventListener("click", (event) => {
                                event.stopPropagation();
                                menu.style.display =
                                    menu.style.display === "none"
                                        ? "block"
                                        : "none";
                            });

                            document.addEventListener("click", (event) => {
                                if (
                                    !btn.contains(event.target) &&
                                    !menu.contains(event.target)
                                ) {
                                    menu.style.display = "none";
                                }
                            });
                        }
                    });
                }
            });
        });
    },

    updated() {
        // Update event listeners when parameters change
        this.$nextTick(() => {
            ["header", "body", "footer"].forEach((section) => {
                if (this.params[section]) {
                    this.params[section].forEach((_, index) => {
                        const btn = document.getElementById(
                            `variable-btn-${section}-${index}`
                        );
                        const menu = document.getElementById(
                            `variable-menu-${section}-${index}`
                        );

                        if (btn && menu && !btn._hasListener) {
                            btn._hasListener = true;
                            btn.addEventListener("click", (event) => {
                                event.stopPropagation();
                                menu.style.display =
                                    menu.style.display === "none"
                                        ? "block"
                                        : "none";
                            });
                        }
                    });
                }
            });
        });
    },
};
</script>
