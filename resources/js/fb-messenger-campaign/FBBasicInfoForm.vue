<script setup>
import { computed } from "vue";
import { BsPatchExclamation } from "@kalimahapps/vue-icons";

const props = defineProps({
    campaignName: { type: String, default: "" },
    relType: { type: String, default: "" },
    templateId: { type: [String, Number, null], default: null },
    templates: { type: Array, default: () => [] },
    relationTypes: { type: Object, default: () => ({}) },
    campaignNameError: { type: String, default: null },
    relTypeError: { type: String, default: null },
    templateIdError: { type: String, default: null },
});

const emit = defineEmits([
    "update:campaignName",
    "update:relType",
    "update:templateId",
    "templateChange",
]);

const localCampaignName = computed({
    get: () => props.campaignName,
    set: (value) => emit("update:campaignName", value),
});

const localRelType = computed({
    get: () => props.relType,
    set: (value) => emit("update:relType", value),
});

const localTemplateId = computed({
    get: () => props.templateId,
    set: (value) => emit("update:templateId", value),
});

const relationTypeOptions = computed(() =>
    Object.entries(props.relationTypes).map(([key, label]) => ({
        value: key,
        label: String(label).charAt(0).toUpperCase() + String(label).slice(1),
    })),
);

function handleTemplateChange(value) {
    emit("update:templateId", value);
    emit("templateChange", value);
}
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
                    Enter campaign details and select a template
                </p>
            </div>
        </div>

        <div class="px-6 py-4 space-y-4">
            <!-- Campaign Name -->
            <div>
                <div class="flex items-center justify-start mb-1">
                    <span class="text-danger-500 mr-1">*</span>
                    <label
                        for="fb_campaign_name"
                        class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                    >
                        Campaign Name
                    </label>
                </div>
                <input
                    v-model="localCampaignName"
                    type="text"
                    id="fb_campaign_name"
                    maxlength="100"
                    autocomplete="off"
                    placeholder="Enter campaign name"
                    :class="[
                        'block w-full border-slate-300 rounded-md shadow-sm text-slate-900 sm:text-sm focus:ring-primary-500 focus:border-primary-500 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:placeholder-slate-500 dark:text-slate-200 dark:focus:ring-primary-500 dark:focus:border-primary-500',
                        campaignNameError
                            ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                            : '',
                    ]"
                />
                <p v-if="campaignNameError" class="text-red-500 text-sm mt-1">
                    {{ campaignNameError }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Relation Type -->
                <div>
                    <div class="flex items-center justify-start mb-1">
                        <span class="text-danger-500 mr-1">*</span>
                        <label
                            for="fb_rel_type"
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
                            for="fb_template_id"
                            class="block text-sm font-medium text-slate-700 dark:text-slate-300"
                        >
                            Template
                        </label>
                    </div>
                    <v-select
                        v-model="localTemplateId"
                        :options="templates"
                        label="name"
                        :reduce="(t) => t.id"
                        placeholder="Nothing Selected"
                        :class="[
                            'vue-select-custom',
                            templateIdError ? 'border-red-300' : '',
                        ]"
                        @update:modelValue="handleTemplateChange"
                    >
                        <template #option="{ name, content_type }">
                            <div class="flex items-center justify-between gap-2">
                                <span>{{ name }}</span>
                                <span
                                    v-if="content_type && content_type !== 'text'"
                                    :class="[
                                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                        content_type === 'image'
                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400'
                                            : content_type === 'video'
                                              ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400'
                                              : content_type === 'document'
                                                ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400',
                                    ]"
                                >
                                    {{ content_type.toUpperCase() }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400"
                                >
                                    TEXT
                                </span>
                            </div>
                        </template>
                        <template #selected-option="{ name }">
                            {{ name }}
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
