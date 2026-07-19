<template>
    <div class="w-full">
        <div class="preview-container rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 dark:bg-gray-800 overflow-hidden">

            <!-- Messenger chat background -->
            <div class="p-4 bg-gray-50 dark:bg-gray-900">

                <!-- Message bubble (mirrors WhatsAppPreview layout) -->
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow-sm border border-gray-200 dark:border-gray-600 overflow-hidden">

                    <!-- ── Media section ──────────────────────────────── -->
                    <div v-if="hasMedia">

                        <!-- IMAGE -->
                        <div v-if="templateData.content_type === 'image'">
                            <!-- Loaded image -->
                            <div v-if="templateData.media_url && !imageError" class="aspect-video bg-gray-100 dark:bg-gray-600">
                                <img
                                    :key="templateData.media_url"
                                    :src="templateData.media_url"
                                    alt="Image"
                                    class="w-full h-full object-cover"
                                    @error="onImageError"
                                />
                            </div>
                            <!-- Placeholder (no url OR load failed) -->
                            <div v-else class="aspect-video bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                <BsImage class="w-8 h-8 text-gray-400 dark:text-gray-300" />
                            </div>
                        </div>

                        <!-- VIDEO -->
                        <div v-else-if="templateData.content_type === 'video'">
                            <div v-if="templateData.media_url" class="aspect-video bg-gray-100 dark:bg-gray-600">
                                <video
                                    :src="templateData.media_url"
                                    class="w-full h-full object-cover"
                                    controls
                                ></video>
                            </div>
                            <div v-else class="aspect-video bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                <BsCameraVideo class="w-8 h-8 text-gray-400 dark:text-gray-300" />
                            </div>
                        </div>

                        <!-- DOCUMENT -->
                        <div v-else-if="templateData.content_type === 'document'" class="p-3 bg-gray-50 dark:bg-gray-600 border-b border-gray-100 dark:border-gray-500">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded flex items-center justify-center flex-shrink-0">
                                    <CaDocument class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">Document</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                        {{ mediaFilename || 'document.pdf' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- ── Message content ─────────────────────────────── -->
                    <div class="p-3">
                        <!-- Body text -->
                        <div v-if="processedMessageText.trim()" class="mb-2">
                            <div
                                class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed whitespace-pre-wrap"
                                v-html="formattedMessageText"
                            ></div>
                        </div>
                        <div v-else class="mb-2">
                            <p class="text-sm text-gray-400 dark:text-gray-500 italic">
                                Enter your message body...
                            </p>
                        </div>

                        <!-- Timestamp -->
                        <div class="flex justify-end">
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ currentTime }}</span>
                        </div>
                    </div>

                    <!-- ── Buttons (mirrors WhatsAppPreview button section) ── -->
                    <div
                        v-if="templateData.buttons && templateData.buttons.length"
                        class="border-t border-gray-100 dark:border-gray-600"
                    >
                        <button
                            v-for="(btn, index) in templateData.buttons"
                            :key="index"
                            class="w-full p-3 text-center border-b border-gray-100 dark:border-gray-600 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <component :is="getButtonIcon(btn.type)" class="w-4 h-4 text-blue-500 dark:text-blue-400" />
                                <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                                    {{ btn.title || 'Button' }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import {
    BsImage,
    BsCameraVideo,
    CaDocument,
    BsMusicNoteBeamed,
    CoChatBubble,
    BsGlobeAmericas,
} from '@kalimahapps/vue-icons';

const props = defineProps({
    templateData: {
        type: Object,
        default: () => ({}),
    },
    // Array of preview value strings for {{1}}, {{2}}, ...
    previewValues: {
        type: Array,
        default: () => [],
    },
});

// ── Media ─────────────────────────────────────────────────────────────────────
const hasMedia = computed(() =>
    props.templateData.content_type && props.templateData.content_type !== 'text'
);

const mediaFilename = computed(() => {
    if (!props.templateData.media_url) return '';
    return props.templateData.media_url.split('/').pop() ?? '';
});

// Track image load failure — reset when URL changes so a new URL gets a fresh attempt
const imageError = ref(false);
watch(() => props.templateData.media_url, () => { imageError.value = false; });

function onImageError() {
    imageError.value = true;
}

// ── Message text with {{1}} substitution ─────────────────────────────────────
const processedMessageText = computed(() => {
    if (!props.templateData.message_text) return '';
    let text = props.templateData.message_text;
    (props.previewValues || []).forEach((value, index) => {
        const placeholder = `{{${index + 1}}}`;
        const regex = new RegExp(placeholder.replace(/[{}]/g, '\\$&'), 'g');
        text = text.replace(regex, value || placeholder);
    });
    return text;
});

// ── Rich text formatting ──────────────────────────────────────────────────────
const formattedMessageText = computed(() => {
    if (!processedMessageText.value) return '';
    let f = processedMessageText.value;
    f = f.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>');
    f = f.replace(/_([^_\n]+)_/g, '<em>$1</em>');
    f = f.replace(/~([^~\n]+)~/g, '<s>$1</s>');
    f = f.replace(/```([^`]+)```/g, '<code style="background:#f3f4f6;padding:2px 4px;border-radius:3px;font-family:monospace;font-size:.9em">$1</code>');
    f = f.replace(/\n/g, '<br>');
    return f;
});

// ── Buttons ───────────────────────────────────────────────────────────────────
const getButtonIcon = (type) => {
    switch (type) {
        case 'web_url':  return BsGlobeAmericas;
        case 'postback':
        default:         return CoChatBubble;
    }
};

// ── Helpers ───────────────────────────────────────────────────────────────────
const currentTime = computed(() =>
    new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false })
);
</script>
