<script setup>
import { onMounted, watch, computed, ref } from "vue";
import BasicInfoForm from "./BasicInfoForm.vue";
import ContactSelection from "./ContactSelection.vue";
import CampaignScheduling from "./CampaignScheduling.vue";
import VariablesAndFiles from "./VariablesAndFiles.vue";
import CampaignPreview from "./CampaignPreview.vue";
import CarouselCards from "./carousel/CarouselCards.vue";
import CarouselPreview from "./carousel/CarouselPreview.vue";
import WizardContainer from "./Wizard/WizardContainer.vue";
import WizardPreview from "./Wizard/WizardPreview.vue";
import { useCampaignApi } from "./composables/useCampaignApi.js";
import { BsFileRichtext, BxLoaderAlt } from "@kalimahapps/vue-icons";
const props = defineProps({
    campaignId: {
        type: [String, Number],
        default: null,
    },
    statusesData: {
        type: String,
        required: true,
    },
    sourcesData: {
        type: String,
        required: true,
    },
    groupsData: {
        type: String,
        required: true,
    },
    tenantSubdomain: {
        type: String,
        required: true,
    },
});
// Wizard state
const currentStepIndex = ref(0);

// Contact selection state
const selectAll = ref(false);
const selectedContacts = ref([]);
const statusFilters = ref([]);
const sourceFilters = ref([]);
const groupFilters = ref([]);
const contactCount = ref(0);

// Scheduling state
const sendNow = ref(true);
const scheduledSendTime = ref(null);
const schedulingErrors = ref({});

// Parse JSON props
const statuses = JSON.parse(props.statusesData || "[]");
const sources = JSON.parse(props.sourcesData || "[]");
const groups = JSON.parse(props.groupsData || "[]");

// Use the composable
const {
    // State
    tenantSubdomain,
    templates,
    mergeFields,
    relationTypes,
    metaExtensions,
    systemSettings,

    // Form State
    templateName,
    relType,
    templateId,
    headerInputs,
    bodyInputs,
    footerInputs,
    file,
    filename,

    // Carousel State
    cardsJson,
    cardVariables,
    cardErrors,
    cardMedia,
    bodyVariables,
    bodyErrors,

    // Template State
    templateSelected,
    templateHeader,
    templateBody,
    templateFooter,
    buttons,
    inputType,
    inputAccept,
    headerParamsCount,
    bodyParamsCount,
    footerParamsCount,
    templateCategory,

    // Preview State
    previewUrl,
    previewFileName,
    previewType,

    // Upload State
    isUploading,
    progress,

    // Validation State
    headerInputErrors,
    bodyInputErrors,
    footerInputErrors,
    fileError,

    // Basic Form Validation Errors
    templateNameError,
    relTypeError,
    templateIdError,

    // Loading State
    isLoading,
    isSaving,

    // Computed
    isEditMode,
    hasReachedLimit,
    // Methods
    handleTemplateChange,
    resetTemplateState,
    handleFilePreview,
    removeFile,
    validateInputs,
    handleSave,
    save,
    loadData,
    initTomSelect,
} = useCampaignApi(
    props,
    {
        selectAll,
        selectedContacts,
        statusFilters,
        sourceFilters,
        groupFilters,
    },
    {
        sendNow,
        scheduledSendTime,
    },
    (stepIndex) => {
        // Navigation callback to change step when validation errors occur
        currentStepIndex.value = stepIndex;
    },
);

const selectedTemplateType = computed(() => {
    if (!templates.value || !templateId.value) return "";
    const selected = templates.value.find(
        (t) => String(t.template_id) === String(templateId.value),
    );
    return selected && selected.template_type
        ? String(selected.template_type).toLowerCase()
        : "";
});


// Wizard tabs configuration
const wizardTabs = computed(() => {
    // Calculate validation inline for reactivity
    const isBasicInfoValid = !!(templateName.value && relType.value && templateId.value);
    const isContactSelectionValid = selectAll.value || selectedContacts.value.length > 0;

    // Variables & Files validation
    let isVariablesFilesValid = true;

    if (headerParamsCount.value > 0) {
        for (let i = 0; i < headerParamsCount.value; i++) {

            if (!headerInputs.value[i] || headerInputs.value[i].trim() === '') {
                isVariablesFilesValid = false;
                break;
            }
        }
    }
    if (isVariablesFilesValid && bodyParamsCount.value > 0) {
        for (let i = 0; i < bodyParamsCount.value; i++) {
            if (!bodyInputs.value[i] || bodyInputs.value[i].trim() === '') {
                isVariablesFilesValid = false;
                break;
            }
        }
    }
    if (isVariablesFilesValid && footerParamsCount.value > 0) {
        for (let i = 0; i < footerParamsCount.value; i++) {
            if (!footerInputs.value[i] || footerInputs.value[i].trim() === '') {
                isVariablesFilesValid = false;
                break;
            }
        }
    }
    if (isVariablesFilesValid && inputType.value && (inputType.value === 'image' || inputType.value === 'video' || inputType.value === 'document')) {
        if (!file.value && !previewUrl.value) {
            isVariablesFilesValid = false;
        }
    }


    // Carousel cards validation
    let isCarouselValid = true;
    if (cardsJson.value && cardsJson.value.length > 0) {
        let cards = [];
        if (typeof cardsJson.value === "string") {
            try {
                cards = JSON.parse(cardsJson.value);
            } catch (e) {
                isCarouselValid = false;
            }
        } else if (Array.isArray(cardsJson.value)) {
            cards = cardsJson.value;
        }

        if (isCarouselValid) {
            for (let i = 0; i < cards.length; i++) {
                const card = cards[i];
                if (!card.components || !Array.isArray(card.components)) continue;

                const headerComponent = card.components.find(c => c.type === 'HEADER');
                if (headerComponent && (headerComponent.format === 'IMAGE' || headerComponent.format === 'VIDEO')) {
                    if (!cardMedia.value || !cardMedia.value[i]) {
                        isCarouselValid = false;
                        break;
                    }
                }

                const bodyComponent = card.components.find(c => c.type === 'BODY');
                if (bodyComponent && bodyComponent.text) {
                    const variables = bodyComponent.text.match(/\{\{(\d+)\}\}/g);
                    if (variables) {
                        for (const variable of variables) {
                            const varNum = variable.replace(/[{}]/g, '');
                            if (!cardVariables.value[i] || !cardVariables.value[i][varNum] || cardVariables.value[i][varNum].trim() === '') {
                                isCarouselValid = false;
                                break;
                            }
                        }
                    }
                    if (!isCarouselValid) break;
                }
            }
        }

        if (isCarouselValid && templateBody.value && templateBody.value.trim()) {
            const bodyVars = templateBody.value.match(/\{\{(\d+)\}\}/g);
            if (bodyVars) {
                for (const variable of bodyVars) {
                    const varNum = variable.replace(/[{}]/g, '');
                    if (!bodyVariables.value[varNum] || bodyVariables.value[varNum].trim() === '') {
                        isCarouselValid = false;
                        break;
                    }
                }
            }
        }
    }

    // Scheduling validation
    let isSchedulingValid = sendNow.value;
    if (!sendNow.value) {
        if (scheduledSendTime.value) {
            const scheduledDate = new Date(scheduledSendTime.value);
            const now = new Date();
            isSchedulingValid = scheduledDate > now;
        } else {
            isSchedulingValid = false;
        }
    }

    const tabs = [
        {
            id: "basic-info",
            label: "Basic Information",
            component: BasicInfoForm,
            isValid: isBasicInfoValid,
            props: {
                templateName: templateName.value,
                relType: relType.value,
                templateId: templateId.value,
                templates: templates.value,
                relationTypes: relationTypes.value,
                hasReachedLimit: hasReachedLimit.value,
                tenantSubdomain: tenantSubdomain,
                templateNameError: templateNameError.value,
                relTypeError: relTypeError.value,
                templateIdError: templateIdError.value,
                "onUpdate:templateName": (value) =>
                    (templateName.value = value),
                "onUpdate:relType": (value) => (relType.value = value),
                "onUpdate:templateId": (value) => (templateId.value = value),
                onTemplateChange: handleTemplateChange,
            },
        },
    ];

    // Add contact selection - always show in tabs
    tabs.push({
        id: "contact-selection",
        label: "Contact Selection",
        component: ContactSelection,
        isValid: isContactSelectionValid,
        props: {
                relType: relType.value,
                selectAll: selectAll.value,
                selectedContacts: selectedContacts.value,
                statusFilters: statusFilters.value,
                sourceFilters: sourceFilters.value,
                groupFilters: groupFilters.value,
                statuses: statuses,
                sources: sources,
                groups: groups,
                tenantSubdomain: tenantSubdomain,
                "onUpdate:selectAll": (value) => (selectAll.value = value),
                "onUpdate:selectedContacts": (value) =>
                    (selectedContacts.value = value),
                "onUpdate:statusFilters": (value) =>
                    (statusFilters.value = value),
                "onUpdate:sourceFilters": (value) =>
                    (sourceFilters.value = value),
                "onUpdate:groupFilters": (value) =>
                    (groupFilters.value = value),
                onContactCountChanged: (count) => (contactCount.value = count),
            },
    });

    // Add template-specific tabs - always show
    if (selectedTemplateType.value === "carousel") {
        // Carousel template tab
        tabs.push({
            id: "carousel-cards",
            label: "Carousel Cards",
            component: CarouselCards,
            isValid: isCarouselValid,
            props: {
                    cardsJson: cardsJson.value,
                    cardVariables: cardVariables.value,
                    cardErrors: cardErrors.value,
                    cardMedia: cardMedia.value,
                    mergeFields: mergeFields.value,
                    templateBodyData: templateBody.value,
                    bodyVariables: bodyVariables.value,
                    bodyErrors: bodyErrors.value,
                    "onUpdate:cardVariables": (value) =>
                        (cardVariables.value = value),
                    "onUpdate:cardErrors": (value) =>
                        (cardErrors.value = value),
                    "onUpdate:cardMedia": (value) => (cardMedia.value = value),
                    "onUpdate:bodyVariables": (value) =>
                        (bodyVariables.value = value),
                    "onUpdate:bodyErrors": (value) =>
                        (bodyErrors.value = value),
            },
        });
    } else if (selectedTemplateType.value === "header") {
        // Regular template tab
        tabs.push({
            id: "variables-files",
            label: "Variables & Files",
            component: VariablesAndFiles,
            isValid: isVariablesFilesValid,
            props: {
                    templateSelected: true,
                    inputType: inputType.value,
                    headerParamsCount: headerParamsCount.value,
                    bodyParamsCount: bodyParamsCount.value,
                    footerParamsCount: footerParamsCount.value,
                    headerInputs: headerInputs.value,
                    bodyInputs: bodyInputs.value,
                    footerInputs: footerInputs.value,
                    headerInputErrors: headerInputErrors.value,
                    bodyInputErrors: bodyInputErrors.value,
                    footerInputErrors: footerInputErrors.value,
                    fileError: fileError.value,
                    previewUrl: previewUrl.value,
                    previewFileName: previewFileName.value,
                    inputAccept: inputAccept.value,
                    metaExtensions: metaExtensions.value,
                    isUploading: isUploading.value,
                    progress: progress.value,
                    mergeFields: mergeFields.value,
                    templateCategory: templateCategory.value,
                    "onUpdate:headerInputs": (value) =>
                        (headerInputs.value = value),
                    "onUpdate:bodyInputs": (value) =>
                        (bodyInputs.value = value),
                    "onUpdate:footerInputs": (value) =>
                        (footerInputs.value = value),
                    onFilePreview: handleFilePreview,
                    onRemoveFile: removeFile,
            },
        });
    }

    // Add scheduling step - always show
    tabs.push({
        id: "scheduling",
        label: "Scheduling",
        component: CampaignScheduling,
        isValid: isSchedulingValid,
        props: {
                sendNow: sendNow.value,
                scheduledSendTime: scheduledSendTime.value,
                dateFormat:
                    systemSettings.value?.date_format || "dd-MM-yyyy",
                timeFormat: systemSettings.value?.time_format || "24",
                timezone: systemSettings.value?.timezone || "UTC",
                is24Hour: systemSettings.value?.is24Hour || false,
                errors: schedulingErrors.value,
                "onUpdate:sendNow": (value) => (sendNow.value = value),
                "onUpdate:scheduledSendTime": (value) =>
                    (scheduledSendTime.value = value),
            "onUpdate:errors": (value) => (schedulingErrors.value = value),
        },
    });

    return tabs;
});

// Preview data configuration
const previewData = computed(() => {

    return {
        title: "Live Preview",
        description:
            selectedTemplateType.value === "carousel"
                ? "Preview your carousel template with cards and variables"
                : "See how your WhatsApp message will look to recipients",
        content: null,
    };
});

// Wizard navigation methods
const nextStep = () => {
    if (currentStepIndex.value < wizardTabs.value.length - 1) {
        currentStepIndex.value++;
    }
};

const previousStep = () => {
    if (currentStepIndex.value > 0) {
        currentStepIndex.value--;
    }
};

const handleStepChanged = (step) => {
    const index = wizardTabs.value.findIndex((tab) => tab.id === step.id);
    if (index !== -1) {
        currentStepIndex.value = index;
    }
};

// Check if scheduled time is valid (not in the past)
const isScheduledTimeValid = computed(() => {
    if (sendNow.value) return true;
    if (!scheduledSendTime.value) return false;

    const scheduledDate = new Date(scheduledSendTime.value);
    const now = new Date();

    return scheduledDate > now;
});

// Step validation
const canProceedToNextStep = computed(() => {
    const currentStep = wizardTabs.value[currentStepIndex.value];
    return currentStep?.isValid || false;
});

// Can save validation
const canSave = computed(() => {
    if (!templateName.value || !relType.value || !templateId.value) {
        return false;
    }
    if (!selectAll.value && selectedContacts.value.length === 0) {
        return false;
    }
    // Validate scheduled time is not in the past
    if (!sendNow.value && !isScheduledTimeValid.value) {
        return false;
    }
    return true;
});

// Lifecycle Hooks
onMounted(() => {
    loadData();
});

// Watch for template changes
watch(templateId, (newValue) => {
    const templateSelect = document.querySelector("#template_id");
    if (templateSelect) {
        handleTemplateChange({ target: templateSelect });
    }
});
</script>

<template>
    <div>
        <!-- Loading State -->
        <div
            v-if="isLoading"
            class="flex items-center justify-center min-h-screen"
        >
            <BxLoaderAlt
                class="animate-spin rounded-full h-10 w-10 text-primary-600"
            />
        </div>
        <!-- Wizard Layout -->
        <div v-else>
            <div
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 items-start"
            >
                <!-- LEFT SIDE -->
                <div class="lg:col-span-8 pb-24">
                    <!-- Wizard Steps -->
                    <WizardContainer
                        :tabs="wizardTabs"
                        :preview="previewData"
                        :currentStepIndex="currentStepIndex"
                        :canProceedToNextStep="Boolean(canProceedToNextStep)"
                        :canSave="Boolean(canSave)"
                        :isSaving="Boolean(isSaving)"
                        :isEditMode="Boolean(isEditMode)"
                        @step-changed="handleStepChanged"
                        @next="nextStep"
                        @previous="previousStep"
                        @save="handleSave"
                    />
                </div>

                <!-- RIGHT SIDE - PREVIEW -->
                <div class="lg:col-span-4 md:col-span-1 col-span-1 pb-24">
                    <WizardPreview
                        :title="previewData.title"
                        :description="previewData.description"
                    >
                        <!-- Template Preview Component -->
                        <CampaignPreview
                            v-if="selectedTemplateType === 'header'"
                            :templateSelected="true"
                            :inputType="inputType"
                            :previewUrl="previewUrl"
                            :previewFileName="previewFileName"
                            :templateHeader="templateHeader"
                            :templateBody="templateBody"
                            :templateFooter="templateFooter"
                            :headerInputs="headerInputs"
                            :bodyInputs="bodyInputs"
                            :buttons="buttons"
                            :isSaving="isSaving"
                            :isUploading="isUploading"
                            :hasReachedLimit="hasReachedLimit"
                            :isEditMode="isEditMode"
                        />

                        <!-- Carousel Preview Component -->
                        <CarouselPreview
                            v-else-if="selectedTemplateType === 'carousel'"
                            :cardsJson="cardsJson"
                            :cardVariables="cardVariables"
                            :cardMedia="cardMedia"
                            :templateBodyData="templateBody"
                            :bodyVariables="bodyVariables"
                            :isSaving="isSaving"
                            :isUploading="false"
                            :hasReachedLimit="hasReachedLimit"
                            :isEditMode="isEditMode"
                        />

                        <!-- Default Preview -->
                        <div
                            v-else
                            class="text-center py-8 rounded-lg min-h-[400px]"
                            style="
                                background-image: url(&quot;/img/chat/whatsapp_light_bg.png&quot;);
                            "
                        >
                            <div
                                class="bg-white dark:bg-gray-700 rounded-lg p-4 max-w-sm mx-auto"
                            >
                                <BsFileRichtext
                                    class="mx-auto h-12 w-12 text-gray-400 mb-4"
                                />
                                <p class="text-slate-400 text-sm">
                                    Select a template to see preview
                                </p>
                            </div>
                        </div>
                    </WizardPreview>
                </div>
            </div>
        </div>
    </div>
</template>
