<script setup>
import { ref, computed, watch } from "vue";
import { BsPatchExclamation } from "@kalimahapps/vue-icons";
// Props
const props = defineProps({
    templateName: {
        type: String,
        default: "",
    },
    relType: {
        type: String,
        default: "",
    },
    templateId: {
        type: String,
        default: "",
    },
    relationTypes: {
        type: Object,
        default: () => ({}),
    },
    templates: {
        type: Array,
        default: () => [],
    },
    replyTypes: {
        type: Object,
        default: () => ({}),
    },
    hasReachedLimit: {
        type: Boolean,
        default: false,
    },
    tenantSubdomain: {
        type: String,
        default: "",
    },
    // Validation Error Props
    templateNameError: {
        type: String,
        default: null,
    },
    relTypeError: {
        type: String,
        default: null,
    },
    templateIdError: {
        type: String,
        default: null,
    },
    replyTypeError: {
        type: String,
        default: null,
    },
});
// Emits
const emit = defineEmits([
    "update:templateName",
    "update:relType",
    "update:templateId",
    "templateChange",
    "tributeEvent",
]);

// Computed properties
const localTemplateName = computed({
    get: () => props.templateName,
    set: (value) => emit("update:templateName", value),
});

const localRelType = computed({
    get: () => props.relType,
    set: (value) => emit("update:relType", value),
});

const localTemplateId = computed({
    get: () => props.templateId,
    set: (value) => emit("update:templateId", value),
});

// Options for v-select
const relationTypeOptions = computed(() => {
    return Object.entries(props.relationTypes).map(([key, label]) => ({
        value: key,
        label: label.charAt(0).toUpperCase() + label.slice(1),
    }));
});

const handleTemplateChange = (value) => {
    localTemplateId.value = value;
    emit("templateChange", value);
};

const handleRelTypeChange = () => {
    emit("tributeEvent");
};

// Watchers
watch(
    () => props.relType,
    () => {
        handleRelTypeChange();
    },
);
// replaces localTemplateId
const selectedTemplateId = ref(null);
const templateIdError = ref(false);

// all data you were reading from data-*
const headerText = ref("");
const bodyData = ref("");
const footerData = ref("");
const buttonsData = ref([]);
const headerFormat = ref("");
const headerParamsCount = ref(0);
const bodyParamsCount = ref(0);
const footerParamsCount = ref(0);
const category = ref("");

// when selection changes
watch(selectedTemplateId, (templateId) => {
    if (!templateId) return;

    const template = props.templates.find((t) => t.template_id === templateId);

    if (!template) return;

    // 🔥 SAME DATA AS data-* (but reactive & clean)
    headerText.value = template.header_data_text;
    bodyData.value = template.body_data;
    footerData.value = template.footer_data;
    buttonsData.value =
        typeof template.buttons_data === "string"
            ? JSON.parse(template.buttons_data)
            : template.buttons_data || [];

    headerFormat.value = template.header_data_format;
    headerParamsCount.value = template.header_params_count;
    bodyParamsCount.value = template.body_params_count;
    footerParamsCount.value = template.footer_params_count;
    category.value = template.category || "";
});
</script>

<template>
    <div
        class="rounded-lg shadow-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700"
    >
        <div
            class="border-b border-slate-200 p-4 dark:border-slate-600 flex items-center gap-4"
        >
            <div class="p-2 rounded-full bg-primary-100 dark:bg-primary-900">
                <BsPatchExclamation class="h-6 w-6 text-primary-600" />
            </div>

            <div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-300">
                    Basic Information
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Enter campaign details and select template
                </p>
            </div>
        </div>
        <!-- Card Content -->
        <div class="px-6 py-4 space-y-4">
            <!-- Campaign Name -->
            <div>
                <div class="flex items-center justify-start mb-1">
                    <span class="text-danger-500 mr-1">*</span>
                    <label
                        for="name"
                        class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                    >
                        Campaign Name
                    </label>
                </div>
                <input
                    v-model="localTemplateName"
                    type="text"
                    id="name"
                    :class="[
                        'block w-full border-slate-300 rounded-md shadow-sm text-slate-900 sm:text-sm focus:ring-primary-500 focus:border-primary-500 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:placeholder-slate-500 dark:text-slate-200 dark:focus:ring-primary-500 dark:focus:border-primary-500',
                        templateNameError
                            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                            : '',
                    ]"
                    autocomplete="off"
                    placeholder="Enter campaign name"
                />
                <p v-if="templateNameError" class="text-red-500 text-sm mt-1">
                    {{ templateNameError }}
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Relation Type -->
                <div>
                    <div class="flex items-center justify-start mb-1">
                        <span class="text-danger-500 mr-1">*</span>
                        <label
                            for="rel_type"
                            class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Relation Type
                        </label>
                    </div>
                    <v-select
                        v-model="localRelType"
                        :options="relationTypeOptions"
                        label="label"
                        :reduce="(option) => option.value"
                        placeholder="Nothing Selected"
                        :clearable="true"
                        :searchable="true"
                        @update:modelValue="handleRelTypeChange"
                        :class="[
                            'vue-select-custom',
                            relTypeError ? 'border-red-300' : '',
                        ]"
                    />
                    <p v-if="relTypeError" class="text-red-500 text-sm mt-1">
                        {{ relTypeError }}
                    </p>
                </div>

                <!-- Template -->
                <div>
                    <div class="flex items-center justify-start mb-1">
                        <span class="text-danger-500 mr-1">*</span>
                        <label
                            for="template_id"
                            class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Template
                        </label>
                    </div>
                    <v-select
                        v-model="localTemplateId"
                        :options="templates"
                        label="template_name"
                        class="vue-select-custom"
                        :reduce="(t) => String(t.template_id)"
                        placeholder="Nothing Selected"
                        @update:modelValue="handleTemplateChange"
                    >
                        <template #option="{ template_name, language, template_type, header_data_format }">
                            <div class="flex items-center justify-between gap-2">
                                <span>{{ template_name }} ({{ language }})</span>
                                <span 
                                    v-if="template_type && template_type.toLowerCase() === 'carousel'"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400"
                                >
                                    Carousel
                                </span>
                                <span 
                                    v-else-if="header_data_format && header_data_format !== 'TEXT'"
                                    :class="[
                                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                        header_data_format === 'IMAGE' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400' :
                                        header_data_format === 'VIDEO' ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' :
                                        header_data_format === 'DOCUMENT' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400' :
                                        header_data_format === 'AUDIO' ? 'bg-pink-100 text-pink-800 dark:bg-pink-900/20 dark:text-pink-400' :
                                        'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400'
                                    ]"
                                >
                                    {{ header_data_format }}
                                </span>
                                <span 
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400"
                                >
                                    TEXT
                                </span>
                            </div>
                        </template>
                        <template #selected-option="{ template_name, language }">
                            {{ template_name }} ({{ language }})
                        </template>
                    </v-select>

                    <p v-if="templateIdError" class="text-red-500 text-sm mt-1">
                        {{ templateIdError }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
