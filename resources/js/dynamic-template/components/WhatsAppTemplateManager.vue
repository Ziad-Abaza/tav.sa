<template>
    <div class="min-h-screen bg-gray-50 dark:bg-transparent">
        <!-- Loading State -->
        <div
            v-if="isLoading"
            class="flex items-center justify-center min-h-screen"
        >
            <div
                class="animate-spin rounded-full h-32 w-32 border-b-2 border-primary-600"
            ></div>
        </div>

        <!-- Error State -->
        <div
            v-else-if="loadError"
            class="flex items-center justify-center min-h-screen"
        >
            <div
                class="bg-red-50 border border-red-200 rounded-lg p-6 max-w-md"
            >
                <div class="flex items-center">
                    <svg
                        class="w-6 h-6 text-red-500 mr-3"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        ></path>
                    </svg>
                    <div>
                        <h3 class="text-red-800 font-semibold">
                            {{ t("error_loading_template") }}
                        </h3>
                        <p class="text-red-600 text-sm mt-1">{{ loadError }}</p>
                    </div>
                </div>
                <button
                    @click="retryLoad"
                    class="mt-4 bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700"
                >
                    {{ t("retry") }}
                </button>
            </div>
        </div>

        <!-- Template Editor -->
        <TemplateEditor
            v-else
            :template="selectedTemplate"
            :categories="categories"
            :languages="languages"
            @close="closeEditor"
            @save="handleSave"
            @back="backToTemplate"
            :isSubmitting="loading"
        />
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import TemplateEditor from "./TemplateEditor.vue";
import { useTemplateApi } from "../composables/useTemplateApi";
import { useTranslations } from "../composables/useTranslations";

// Initialize translations
const { t } = useTranslations();

const {
    categories,
    languages,
    getTemplate,
    createTemplate,
    updateTemplate,
    error,
    loading,
} = useTemplateApi();

// Component state
const selectedTemplate = ref(null);
const isLoading = ref(false);
const loadError = ref(null);

const closeEditor = () => {
    // Redirect back to template list or close modal
    const subdomain = window.subdomain;
    window.location.href = `/${subdomain}/dynamic-template`;
};

const backToTemplate = () => {
    // Redirect back to template list or close modal
    const subdomain = window.subdomain;
    window.location.href = `/${subdomain}/template`;
};
const handleSave = async (templateData) => {
    try {
        if (selectedTemplate.value && selectedTemplate.value.id) {
            // Update existing template
            await updateTemplate(selectedTemplate.value.id, templateData);
            showNotification("Template updated successfully.", "success");
            backToTemplate();
        } else {
            // Create new template
            await createTemplate(templateData);
            showNotification("Template created successfully.", "success");
            backToTemplate();
        }

        // Redirect to template list after successful save
        backToTemplate();
    } catch (error) {
        console.error("Error saving template:", error);
        const errorMessage =
            error.value || error.message || "Something went wrong.";
        showNotification(errorMessage, "danger");
    }
};

// FIXED: Improved template loading logic
const loadTemplateForEdit = async () => {
    isLoading.value = true;
    loadError.value = null;

    try {
        // Get template data from global variable set by Laravel
        const template = window.templateEdit;
        if (!template) {
            throw new Error(t("template_not_found"));
        }
        selectedTemplate.value = template;
    } catch (error) {
        console.error("Error loading template:", error);
        loadError.value = error.message || t("failed_to_load_template");
    } finally {
        isLoading.value = false;
    }
};

const retryLoad = () => {
    loadTemplateForEdit();
};

// Lifecycle
onMounted(async () => {
    await loadTemplateForEdit();
});
</script>
