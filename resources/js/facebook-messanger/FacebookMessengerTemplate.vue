<template>
    <facebook-messenger-template-editor
        :subdomain="subdomain"
        :template="template"
    />
</template>

<script setup>
import FacebookMessengerTemplateEditor from './FacebookMessengerTemplateEditor.vue';

const subdomain = window.subdomain ?? '';
const template = window.fbTemplateData ?? null;
</script>
