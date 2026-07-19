<script setup>
import { ref, computed, watch, onMounted, nextTick } from "vue";
import axios from "axios";
import { BxLoaderAlt, BsFileRichtext } from "@kalimahapps/vue-icons";
import WizardContainer from "@/campaign/Wizard/WizardContainer.vue";
import WizardPreview from "@/campaign/Wizard/WizardPreview.vue";
import CampaignScheduling from "@/campaign/CampaignScheduling.vue";
import FBBasicInfoForm from "./FBBasicInfoForm.vue";
import FBContactSelection from "./FBContactSelection.vue";
import FBVariablesAndFiles from "./FBVariablesAndFiles.vue";

const props = defineProps({
    campaignId: { type: [String, Number], default: null },
    statusesData: { type: String, required: true },
    sourcesData: { type: String, required: true },
    groupsData: { type: String, required: true },
    tenantSubdomain: { type: String, required: true },
});

// Parse JSON props
const statuses = JSON.parse(props.statusesData || "[]");
const sources = JSON.parse(props.sourcesData || "[]");
const groups = JSON.parse(props.groupsData || "[]");

// ─── Loading / Error ──────────────────────────────────────────────────────────

const isLoading = ref(true);
const isSaving = ref(false);
const errorMsg = ref(null);

// ─── API data ─────────────────────────────────────────────────────────────────

const templates = ref([]);
const mergeFields = ref([]);
const relationTypes = ref({});
const systemSettings = ref({});

// ─── Wizard ───────────────────────────────────────────────────────────────────

const currentStepIndex = ref(0);

// ─── Basic Info ───────────────────────────────────────────────────────────────

const campaignName = ref("");
const relType = ref("");
const templateId = ref(null);

const selectedTemplate = computed(() =>
    templates.value.find((t) => t.id === templateId.value) || null,
);

const isEditMode = computed(() => !!props.campaignId);

// Flag to suppress watch resets while loadData is restoring saved campaign state
const isRestoringData = ref(false);

// ─── Contact Selection ────────────────────────────────────────────────────────

const selectAll = ref(false);
const selectedContacts = ref([]);
const statusFilters = ref([]);
const sourceFilters = ref([]);
const groupFilters = ref([]);
const contactCount = ref(0);

// ─── Variables & Files ────────────────────────────────────────────────────────

const variableInputs = ref([]);
const file = ref(null);
const previewUrl = ref("");
const previewFileName = ref("");
const fileError = ref("");

const detectedVariables = computed(() => {
    if (!selectedTemplate.value?.message_text) return [];
    const matches = selectedTemplate.value.message_text.match(/\{\{(\d+)\}\}/g);
    if (!matches) return [];
    return [...new Set(matches.map((m) => m.replace(/\{\{|\}\}/g, "")))]
        .sort((a, b) => parseInt(a) - parseInt(b));
});

const requiresMedia = computed(() =>
    ["image", "video", "document"].includes(
        selectedTemplate.value?.content_type || "",
    ),
);

watch(templateId, () => {
    if (isRestoringData.value) return;
    variableInputs.value = detectedVariables.value.map(() => "");
    file.value = null;
    previewUrl.value = "";
    previewFileName.value = "";
    fileError.value = "";
});

watch(
    detectedVariables,
    (vars) => {
        while (variableInputs.value.length < vars.length) {
            variableInputs.value.push("");
        }
    },
    { immediate: true },
);

// ─── Scheduling ───────────────────────────────────────────────────────────────

const sendNow = ref(true);
const scheduledSendTime = ref(null);
const schedulingErrors = ref({});

// ─── Validation errors ────────────────────────────────────────────────────────

const campaignNameError = ref(null);
const relTypeError = ref(null);
const templateIdError = ref(null);

// ─── Wizard tabs ──────────────────────────────────────────────────────────────

const wizardTabs = computed(() => {
    const isBasicInfoValid = !!(
        campaignName.value?.trim().length >= 3 &&
        relType.value &&
        templateId.value
    );

    const isContactSelectionValid =
        selectAll.value || selectedContacts.value.length > 0;

    const variablesOk =
        detectedVariables.value.length === 0 ||
        detectedVariables.value.every(
            (_, idx) => variableInputs.value[idx]?.trim(),
        );

    const fileOk =
        !requiresMedia.value ||
        file.value ||
        previewUrl.value ||
        selectedTemplate.value?.media_url;

    const isVariablesFilesValid = variablesOk && fileOk;

    let isSchedulingValid = sendNow.value;
    if (!sendNow.value) {
        if (scheduledSendTime.value) {
            isSchedulingValid = new Date(scheduledSendTime.value) > new Date();
        } else {
            isSchedulingValid = false;
        }
    }

    return [
        {
            id: "basic-info",
            label: "Basic Information",
            component: FBBasicInfoForm,
            isValid: isBasicInfoValid,
            props: {
                campaignName: campaignName.value,
                relType: relType.value,
                templateId: templateId.value,
                templates: templates.value,
                relationTypes: relationTypes.value,
                campaignNameError: campaignNameError.value,
                relTypeError: relTypeError.value,
                templateIdError: templateIdError.value,
                "onUpdate:campaignName": (v) => (campaignName.value = v),
                "onUpdate:relType": (v) => (relType.value = v),
                "onUpdate:templateId": (v) => (templateId.value = v),
                onTemplateChange: (v) => (templateId.value = v),
            },
        },
        {
            id: "contact-selection",
            label: "Contact Selection",
            component: FBContactSelection,
            isValid: isContactSelectionValid,
            props: {
                selectAll: selectAll.value,
                selectedContacts: selectedContacts.value,
                statusFilters: statusFilters.value,
                sourceFilters: sourceFilters.value,
                groupFilters: groupFilters.value,
                statuses,
                sources,
                groups,
                tenantSubdomain: props.tenantSubdomain,
                "onUpdate:selectAll": (v) => (selectAll.value = v),
                "onUpdate:selectedContacts": (v) => (selectedContacts.value = v),
                "onUpdate:statusFilters": (v) => (statusFilters.value = v),
                "onUpdate:sourceFilters": (v) => (sourceFilters.value = v),
                "onUpdate:groupFilters": (v) => (groupFilters.value = v),
                onContactCountChanged: (count) => (contactCount.value = count),
            },
        },
        {
            id: "variables-files",
            label: "Variables & Files",
            component: FBVariablesAndFiles,
            isValid: isVariablesFilesValid,
            props: {
                template: selectedTemplate.value,
                variableInputs: variableInputs.value,
                mergeFields: mergeFields.value,
                file: file.value,
                previewUrl: previewUrl.value,
                previewFileName: previewFileName.value,
                fileError: fileError.value,
                "onUpdate:variableInputs": (v) => (variableInputs.value = v),
                "onUpdate:file": (v) => (file.value = v),
                "onUpdate:previewUrl": (v) => (previewUrl.value = v),
                "onUpdate:previewFileName": (v) => (previewFileName.value = v),
                "onUpdate:fileError": (v) => (fileError.value = v),
            },
        },
        {
            id: "scheduling",
            label: "Scheduling",
            component: CampaignScheduling,
            isValid: isSchedulingValid,
            props: {
                sendNow: sendNow.value,
                scheduledSendTime: scheduledSendTime.value,
                dateFormat: systemSettings.value?.date_format || "dd-MM-yyyy",
                timeFormat: systemSettings.value?.time_format || "24",
                timezone: systemSettings.value?.timezone || "UTC",
                errors: schedulingErrors.value,
                "onUpdate:sendNow": (v) => (sendNow.value = v),
                "onUpdate:scheduledSendTime": (v) =>
                    (scheduledSendTime.value = v),
                "onUpdate:errors": (v) => (schedulingErrors.value = v),
            },
        },
    ];
});

// ─── Preview ──────────────────────────────────────────────────────────────────

const previewMessage = computed(() => {
    if (!selectedTemplate.value?.message_text) return "";
    let msg = selectedTemplate.value.message_text;
    detectedVariables.value.forEach((v, idx) => {
        const value = variableInputs.value[idx] || `Variable ${v}`;
        const previewVal = value.startsWith("@{") ? value : `${value}`;
        msg = msg.replace(`{{${v}}}`, previewVal);
    });
    return msg;
});

const previewData = computed(() => ({
    title: "Live Preview",
    description:
        "See how your Facebook Messenger message will look to recipients",
    content: null,
}));

// ─── Wizard navigation ────────────────────────────────────────────────────────

const canProceedToNextStep = computed(
    () => wizardTabs.value[currentStepIndex.value]?.isValid || false,
);

const canSave = computed(() => {
    if (!campaignName.value?.trim() || !relType.value || !templateId.value)
        return false;
    if (!selectAll.value && selectedContacts.value.length === 0) return false;
    if (!sendNow.value) {
        if (!scheduledSendTime.value) return false;
        if (new Date(scheduledSendTime.value) <= new Date()) return false;
    }
    return true;
});

function nextStep() {
    if (currentStepIndex.value < wizardTabs.value.length - 1) {
        currentStepIndex.value++;
    }
}

function previousStep() {
    if (currentStepIndex.value > 0) {
        currentStepIndex.value--;
    }
}

function handleStepChanged(step) {
    const index = wizardTabs.value.findIndex((tab) => tab.id === step.id);
    if (index !== -1) currentStepIndex.value = index;
}

// ─── Load Data ────────────────────────────────────────────────────────────────

async function loadData() {
    isLoading.value = true;
    errorMsg.value = null;

    try {
        const url = props.campaignId
            ? `/${props.tenantSubdomain}/facebook-messenger/campaign/data/${props.campaignId}`
            : `/${props.tenantSubdomain}/facebook-messenger/campaign/data`;

        const { data } = await axios.get(url);
        const d = data.data;

        templates.value = d.templates || [];
        mergeFields.value = d.mergeFields || [];
        relationTypes.value = d.relationTypes || {};
        systemSettings.value = d.systemSettings || {};

        if (d.campaign) {
            isRestoringData.value = true;
            campaignName.value = d.campaign.name;
            relType.value = d.campaign.rel_type || "";
            templateId.value = d.campaign.fb_template_id;
            selectAll.value = d.campaign.select_all ?? false;
            selectedContacts.value = d.campaign.selected_contact_ids || [];
            statusFilters.value = d.campaign.status_filters || [];
            sourceFilters.value = d.campaign.source_filters || [];
            groupFilters.value = d.campaign.group_filters || [];
            sendNow.value = d.campaign.send_now ?? true;
            scheduledSendTime.value = d.campaign.scheduled_send_time || null;
            variableInputs.value = d.campaign.body_params
                ? JSON.parse(d.campaign.body_params)
                : d.campaign.variables_json || [];

            if (d.campaign.media_url) {
                previewUrl.value = d.campaign.media_url;
                previewFileName.value =
                    d.campaign.media_filename || "Uploaded file";
            }
            await nextTick();
            isRestoringData.value = false;
        }
    } catch (e) {
        console.error("[FBCampaignManager] loadData error:", e);
        errorMsg.value =
            e.response?.data?.message || "Failed to load campaign data";
    } finally {
        isLoading.value = false;
    }
}

// ─── Save ─────────────────────────────────────────────────────────────────────

async function handleSave() {
    campaignNameError.value = null;
    relTypeError.value = null;
    templateIdError.value = null;

    if (!campaignName.value?.trim() || campaignName.value.trim().length < 3) {
        campaignNameError.value = "Campaign name must be at least 3 characters";
        currentStepIndex.value = 0;
        return;
    }
    if (!relType.value) {
        relTypeError.value = "Relation type is required";
        currentStepIndex.value = 0;
        return;
    }
    if (!templateId.value) {
        templateIdError.value = "Please select a template";
        currentStepIndex.value = 0;
        return;
    }
    if (!selectAll.value && selectedContacts.value.length === 0) {
        currentStepIndex.value = 1;
        showNotification("Please select at least one contact", "danger");
        return;
    }

    if (isSaving.value) return;
    isSaving.value = true;

    try {
        const formData = new FormData();
        formData.append("campaign_name", campaignName.value.trim());
        formData.append("rel_type", relType.value);
        formData.append("fb_template_id", templateId.value);
        formData.append("select_all", selectAll.value ? "1" : "0");
        formData.append("send_now", sendNow.value ? "1" : "0");

        if (scheduledSendTime.value) {
            formData.append("scheduled_send_time", scheduledSendTime.value);
        }

        formData.append("contact_ids", JSON.stringify(selectedContacts.value));
        formData.append("status_ids", JSON.stringify(statusFilters.value));
        formData.append("source_ids", JSON.stringify(sourceFilters.value));
        formData.append("group_ids", JSON.stringify(groupFilters.value));
        formData.append("variable_inputs", JSON.stringify(variableInputs.value));

        if (file.value) {
            formData.append("file", file.value);
        }

        if (props.campaignId) {
            formData.append("id", props.campaignId);
        }

        const response = await axios.post(
            `/${props.tenantSubdomain}/facebook-messenger/campaign`,
            formData,
            { headers: { "Content-Type": "multipart/form-data" } },
        );

        if (response.data.success) {
            showNotification(response.data.message, "success");
            if (response.data.redirect_url) {
                window.location.href = response.data.redirect_url;
            }
        } else {
            showNotification(
                response.data.message || "Failed to save campaign",
                "danger",
            );
        }
    } catch (e) {
        console.error("[FBCampaignManager] save error:", e);
        const errors = e.response?.data?.errors;
        if (errors) {
            if (errors.campaign_name)
                campaignNameError.value = errors.campaign_name[0];
            if (errors.rel_type) relTypeError.value = errors.rel_type[0];
            if (errors.fb_template_id)
                templateIdError.value = errors.fb_template_id[0];
            if (
                errors.campaign_name ||
                errors.rel_type ||
                errors.fb_template_id
            ) {
                currentStepIndex.value = 0;
            }
        }
        showNotification(
            e.response?.data?.message || "Failed to save campaign",
            "danger",
        );
    } finally {
        isSaving.value = false;
    }
}

function showNotification(message, type = "info") {
    window.dispatchEvent(
        new CustomEvent("notify", { detail: { message, type } }),
    );
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(() => {
    loadData();
});
</script>

<template>
    <div>
        <!-- Loading -->
        <div
            v-if="isLoading"
            class="flex items-center justify-center min-h-[400px]"
        >
            <BxLoaderAlt
                class="animate-spin rounded-full h-10 w-10 text-primary-600"
            />
        </div>

        <!-- Error -->
        <div
            v-else-if="errorMsg"
            class="rounded-lg bg-red-100 p-4 text-red-700 dark:bg-red-900/30 dark:text-red-400"
        >
            {{ errorMsg }}
        </div>

        <!-- Wizard Layout -->
        <div v-else>
            <div
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 items-start"
            >
                <!-- LEFT: Wizard -->
                <div class="lg:col-span-8 pb-24">
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

                <!-- RIGHT: Preview -->
                <div class="lg:col-span-4 md:col-span-1 col-span-1 pb-24">
                    <WizardPreview
                        :title="previewData.title"
                        :description="previewData.description"
                    >
                        <!-- Message preview when template selected -->
                        <div v-if="selectedTemplate">
                            <div
                                class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4"
                            >
                                <!-- Media preview -->
                                <div
                                    v-if="
                                        previewUrl ||
                                        selectedTemplate.media_url
                                    "
                                    class="mb-3"
                                >
                                    <img
                                        v-if="
                                            selectedTemplate.content_type ===
                                                'image' &&
                                            (previewUrl ||
                                                selectedTemplate.media_url)
                                        "
                                        :src="
                                            previewUrl ||
                                            selectedTemplate.media_url
                                        "
                                        class="w-full h-32 object-cover rounded-lg"
                                        alt="Media"
                                    />
                                    <video
                                        v-else-if="
                                            selectedTemplate.content_type ===
                                                'video' &&
                                            (previewUrl ||
                                                selectedTemplate.media_url)
                                        "
                                        :src="
                                            previewUrl ||
                                            selectedTemplate.media_url
                                        "
                                        class="w-full h-32 object-cover rounded-lg"
                                        controls
                                    />
                                    <div
                                        v-else-if="
                                            selectedTemplate.content_type ===
                                            'document'
                                        "
                                        class="bg-gray-200 dark:bg-gray-700 rounded-lg p-3 flex items-center gap-2"
                                    >
                                        <svg
                                            class="w-5 h-5 text-gray-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                                            />
                                        </svg>
                                        <span
                                            class="text-sm text-gray-600 dark:text-gray-400"
                                            >{{
                                                previewFileName || "Document"
                                            }}</span
                                        >
                                    </div>
                                </div>

                                <!-- Message text -->
                                <div
                                    class="bg-white dark:bg-gray-900 rounded-lg p-3 shadow-sm"
                                >
                                    <p
                                        class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap"
                                    >
                                        {{ previewMessage }}
                                    </p>
                                    <p
                                        class="text-xs text-gray-400 mt-2 text-right"
                                    >
                                        {{
                                            new Date().toLocaleTimeString([], {
                                                hour: "2-digit",
                                                minute: "2-digit",
                                            })
                                        }}
                                    </p>
                                </div>

                                <!-- Buttons preview -->
                                <div
                                    v-if="selectedTemplate.buttons?.length"
                                    class="mt-3 space-y-2"
                                >
                                    <button
                                        v-for="(btn, idx) in selectedTemplate.buttons"
                                        :key="idx"
                                        class="w-full py-2 text-sm font-medium text-blue-600 dark:text-blue-400 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg"
                                        type="button"
                                    >
                                        {{ btn.title || btn.text || btn }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Default placeholder -->
                        <div
                            v-else
                            class="text-center py-8 rounded-lg min-h-[400px] flex items-center justify-center"
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
