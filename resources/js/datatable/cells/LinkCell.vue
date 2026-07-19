<script setup>
/**
 * LinkCell — renders a value as a clickable link.
 *
 * Requires column meta:
 *   urlPath  {string}   Route path template, e.g. "contacts/contact"
 *   urlKey   {string}   Row field used as the URL ID, defaults to "id"
 *   labelKeys {string[]} Optional row fields to join as label (e.g. ["firstname","lastname"])
 *                        Falls back to `value` when not provided.
 *
 * URL built as: /{subdomain}/{urlPath}/{row[urlKey]}
 * subdomain is injected via provide/inject from ResourceDataTable.
 */
import { computed, inject, unref } from 'vue'

const props = defineProps({
    value: { default: null },
    row: { type: Object, required: true },
    meta: { type: Object, default: () => ({}) },
})

const subdomain = inject('subdomain', '')

const href = computed(() => {
    const urlKey = props.meta?.urlKey ?? 'id'
    const urlPath = props.meta?.urlPath ?? ''
    return `/${unref(subdomain)}/${urlPath}/${props.row[urlKey]}`
})

const label = computed(() => {
    const keys = props.meta?.labelKeys
    if (keys?.length) {
        return keys.map(k => props.row[k]).filter(Boolean).join(' ') || '—'
    }
    return props.value ?? '—'
})
</script>

<template>
    <a
        :href="href"
        class="text-sm text-gray-700 dark:text-white hover:underline"
    >{{ label }}</a>
</template>
