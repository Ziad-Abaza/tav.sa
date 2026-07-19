<script setup>
import { computed, nextTick, onMounted, watch } from "vue";
import { AkFile } from "@kalimahapps/vue-icons";

const props = defineProps({
    // Template data
    template: { type: Object, default: null },
    // Variable inputs array (indexed by variable position)
    variableInputs: { type: Array, default: () => [] },
    // Merge fields for tribute.js
    mergeFields: { type: Array, default: () => [] },
    // File upload state
    file: { type: [File, null], default: null },
    previewUrl: { type: String, default: "" },
    previewFileName: { type: String, default: "" },
    fileError: { type: String, default: "" },
});

const emit = defineEmits([
    "update:variableInputs",
    "update:file",
    "update:previewUrl",
    "update:previewFileName",
    "update:fileError",
]);

// ─── Computed ─────────────────────────────────────────────────────────────────

const contentType = computed(() => props.template?.content_type || "text");

const requiresMedia = computed(() =>
    ["image", "video", "document"].includes(contentType.value),
);

const contentTypeLabel = computed(() => {
    const labels = { image: "Image", video: "Video", document: "Document" };
    return labels[contentType.value] || "File";
});

const inputAccept = computed(() => {
    const acceptMap = {
        image: "image/*",
        video: "video/*",
        document: ".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt",
    };
    return acceptMap[contentType.value] || "";
});

const templateMediaUrl = computed(() => props.template?.media_url || null);

const detectedVariables = computed(() => {
    if (!props.template?.message_text) return [];
    const matches = props.template.message_text.match(/\{\{(\d+)\}\}/g);
    if (!matches) return [];
    return [...new Set(matches.map((m) => m.replace(/\{\{|\}\}/g, "")))]
        .sort((a, b) => parseInt(a) - parseInt(b));
});

const hasVariables = computed(() => detectedVariables.value.length > 0);

// ─── Methods ──────────────────────────────────────────────────────────────────

function updateVariableInput(index, value) {
    const newInputs = [...props.variableInputs];
    newInputs[index] = value;
    emit("update:variableInputs", newInputs);
}

function handleFileSelect(event) {
    const selectedFile = event.target.files[0];
    if (!selectedFile) return;

    const extension = selectedFile.name.split(".").pop().toLowerCase();
    const validExtensions = {
        image: ["jpg", "jpeg", "png", "gif", "webp"],
        video: ["mp4", "mov", "avi", "webm"],
        document: ["pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "txt"],
    };

    const allowedExts = validExtensions[contentType.value] || [];
    if (!allowedExts.includes(extension)) {
        emit(
            "update:fileError",
            `Invalid file type. Allowed: ${allowedExts.join(", ")}`,
        );
        return;
    }

    const maxSize = 25 * 1024 * 1024;
    if (selectedFile.size > maxSize) {
        emit("update:fileError", "File size must be less than 25MB");
        return;
    }

    emit("update:file", selectedFile);
    emit("update:previewFileName", selectedFile.name);
    emit("update:fileError", "");

    if (["image", "video"].includes(contentType.value)) {
        emit("update:previewUrl", URL.createObjectURL(selectedFile));
    } else {
        emit("update:previewUrl", "");
    }
}

function handleFileDrop(event) {
    event.preventDefault();
    const droppedFile = event.dataTransfer.files[0];
    if (droppedFile) {
        handleFileSelect({ target: { files: [droppedFile] } });
    }
}

function handleDragOver(event) {
    event.preventDefault();
}

function removeFile() {
    emit("update:file", null);
    emit("update:previewUrl", "");
    emit("update:previewFileName", "");
    emit("update:fileError", "");
}

function setupTribute() {
    if (typeof window.Tribute === "undefined") return;
    const tribute = new window.Tribute({
        trigger: "@",
        values: props.mergeFields,
        selectTemplate: (item) => `@{${item.original.value}}`,
        menuItemTemplate: (item) => item.original.key,
    });
    nextTick(() => {
        document.querySelectorAll(".fb-mentionable").forEach((el) => {
            if (!el.hasAttribute("data-tribute")) {
                tribute.attach(el);
                el.setAttribute("data-tribute", "true");
            }
        });
    });
}

onMounted(() => {
    nextTick(() => setupTribute());
});

watch(
    () => props.template,
    () => {
        nextTick(() => setupTribute());
    },
);
</script>

<template>
    <div
        class="rounded-lg shadow-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700"
    >
        <div
            class="border-b border-slate-200 p-4 dark:border-slate-600 flex items-center gap-4"
        >
            <div class="p-2 rounded-full bg-purple-100 dark:bg-purple-900">
                <AkFile class="h-6 w-6 text-purple-600" />
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-300">
                    Variables &amp; Files
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Map template variables and upload media
                </p>
            </div>
        </div>

        <div class="px-6 py-4 space-y-6">
            <!-- No template selected -->
            <div
                v-if="!template"
                class="bg-blue-50 border border-blue-200 dark:bg-blue-900/20 dark:border-blue-800 rounded-lg p-4"
            >
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    Please select a template in the Basic Information step first.
                </p>
            </div>

            <template v-else>
                <!-- File Upload Section -->
                <div v-if="requiresMedia">
                    <h3
                        class="text-sm font-medium text-gray-900 dark:text-gray-200 mb-3"
                    >
                        <span class="text-red-500">*</span> Upload
                        {{ contentTypeLabel }}
                    </h3>

                    <!-- Template has default media -->
                    <div
                        v-if="templateMediaUrl && !file && !previewUrl"
                        class="mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700"
                    >
                        <p
                            class="text-sm text-gray-600 dark:text-gray-400 mb-2"
                        >
                            Template default
                            {{ contentTypeLabel.toLowerCase() }}:
                        </p>
                        <div v-if="contentType === 'image'" class="max-w-xs">
                            <img
                                :src="templateMediaUrl"
                                alt="Template media"
                                class="rounded-lg max-h-32 object-cover"
                            />
                        </div>
                        <div
                            v-else-if="contentType === 'video'"
                            class="max-w-xs"
                        >
                            <video
                                :src="templateMediaUrl"
                                controls
                                class="rounded-lg max-h-32"
                            />
                        </div>
                        <div
                            v-else
                            class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                        >
                            <svg
                                class="w-5 h-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            <span>{{ templateMediaUrl.split("/").pop() }}</span>
                        </div>
                    </div>

                    <!-- Upload area -->
                    <div
                        class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center transition-colors"
                        @drop="handleFileDrop"
                        @dragover="handleDragOver"
                    >
                        <!-- No file yet -->
                        <div v-if="!previewUrl && !previewFileName">
                            <svg
                                class="mx-auto h-12 w-12 text-gray-400"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 48 48"
                            >
                                <path
                                    d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            <div class="mt-4">
                                <label class="cursor-pointer">
                                    <span
                                        class="text-primary-600 hover:text-primary-700 font-medium"
                                        >Upload a
                                        {{
                                            contentTypeLabel.toLowerCase()
                                        }}</span
                                    >
                                    <input
                                        type="file"
                                        class="hidden"
                                        :accept="inputAccept"
                                        @change="handleFileSelect"
                                    />
                                </label>
                                <span class="text-gray-500 dark:text-gray-400">
                                    or drag and drop</span
                                >
                            </div>
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400 mt-2"
                            >
                                Max file size: 25MB
                            </p>
                        </div>

                        <!-- File preview -->
                        <div v-else class="relative">
                            <button
                                @click="removeFile"
                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 z-10"
                                type="button"
                            >
                                <svg
                                    class="w-4 h-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                            <div
                                v-if="contentType === 'image' && previewUrl"
                                class="flex justify-center"
                            >
                                <img
                                    :src="previewUrl"
                                    alt="Preview"
                                    class="max-h-40 rounded-lg object-contain"
                                />
                            </div>
                            <div
                                v-else-if="contentType === 'video' && previewUrl"
                                class="flex justify-center"
                            >
                                <video
                                    :src="previewUrl"
                                    controls
                                    class="max-h-40 rounded-lg"
                                />
                            </div>
                            <div
                                v-else
                                class="flex items-center justify-center gap-3 py-4"
                            >
                                <svg
                                    class="w-8 h-8 text-gray-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                                <span
                                    class="text-sm text-gray-700 dark:text-gray-300"
                                    >{{ previewFileName }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <p v-if="fileError" class="mt-2 text-sm text-red-600">
                        {{ fileError }}
                    </p>
                    <p
                        v-if="templateMediaUrl"
                        class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Upload a new {{ contentTypeLabel.toLowerCase() }} to
                        override the template default.
                    </p>
                </div>

                <!-- No variables and no media -->
                <div
                    v-if="!hasVariables && !requiresMedia"
                    class="bg-blue-50 border border-blue-200 dark:bg-blue-900/20 dark:border-blue-800 rounded-lg p-4"
                >
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        This template has no variables or media requirements. You
                        can proceed to the next step.
                    </p>
                </div>

                <!-- Variables Section -->
                <div v-if="hasVariables" class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Map each template variable to a merge field. Type
                        <strong>@</strong> to select from available fields.
                    </p>

                    <div
                        v-for="(variable, index) in detectedVariables"
                        :key="variable"
                        class="space-y-1"
                    >
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            <span class="text-red-500">*</span> Variable
                            {{ variable }}
                        </label>
                        <input
                            :value="variableInputs[index]"
                            type="text"
                            class="fb-mentionable w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                            :placeholder="`Type @ to select a merge field for {{${variable}}}`"
                            @input="updateVariableInput(index, $event.target.value)"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Type @ to see available merge fields like
                            @contact_first_name
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
