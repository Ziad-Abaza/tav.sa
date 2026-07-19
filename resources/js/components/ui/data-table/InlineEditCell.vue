<script setup>
import { BsPencil } from '@kalimahapps/vue-icons';
/**
 * InlineEditCell — wraps any table cell with hover-to-edit behaviour.
 *
 * On hover: shows a pencil icon next to the cell value.
 * On click:  opens a small popover with the appropriate input type.
 * On save:   PATCHes /{resource}/{id}/field with { field, value } and emits 'saved'
 *            with the updated row returned by the server.
 *
 * Config shape (editableFields[colKey]):
 *   {
 *     type:      'select' | 'text'       — input type
 *     field:     'status_id'              — DB field sent in PATCH (defaults to col key)
 *     valueFrom: 'status.id'             — dot-path to read current value from row (defaults to col key)
 *     options:   [{ value, label }]      — for type='select'
 *   }
 */
import { ref, watch, nextTick } from 'vue'
import axios from 'axios'

const props = defineProps({
    row:      { type: Object, required: true },
    value:    { default: null },
    field:    { type: String, required: true },
    config:   { type: Object, required: true },
    /** PATCH endpoint, e.g. /sub/api/contacts/42/field */
    endpoint: { type: String, required: true },
    extraParams: { type: Object, default: () => ({}) }
})

const emit = defineEmits(['saved'])

const isOpen   = ref(false)
const isSaving = ref(false)
const editValue = ref(null)
const errorMsg  = ref(null)
const wrapperRef = ref(null)
const inputRef   = ref(null)

// ─── Helpers ──────────────────────────────────────────────────────────────────

/** Read current value from row using dot-path notation (e.g. 'status.id'). */
function getCurrentValue() {
    const path = props.config.valueFrom ?? props.field
    return String(path).split('.').reduce((obj, k) => obj?.[k] ?? null, props.row)
}

// ─── Open / Close ─────────────────────────────────────────────────────────────

function open() {
    editValue.value = getCurrentValue()
    errorMsg.value  = null
    isOpen.value    = true
}

function close() {
    isOpen.value = false
}

function onOutsideClick(e) {
    if (wrapperRef.value && !wrapperRef.value.contains(e.target)) close()
}

watch(isOpen, (opened) => {
    if (opened) {
        nextTick(() => {
            document.addEventListener('mousedown', onOutsideClick, { capture: true })
            inputRef.value?.focus()
        })
    } else {
        document.removeEventListener('mousedown', onOutsideClick, { capture: true })
    }
})

// ─── Save ─────────────────────────────────────────────────────────────────────

async function save() {
    if (isSaving.value) return
    isSaving.value = true
    errorMsg.value = null
    try {
        const patchField = props.config.field ?? props.field
        const payload = {field: patchField,value: editValue.value,...props.extraParams,   }

        const { data } = await axios.patch(props.endpoint, payload)

        emit('saved', data.row)
        close()
    } catch (err) {
        errorMsg.value = err.response?.data?.message ?? 'Failed to save'
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div ref="wrapperRef" class="group/iefield relative inline-flex min-w-0 items-center gap-1">
        <!-- Current cell content (passed from parent table slot) -->
        <slot />

        <!-- Pencil trigger — visible only on row/cell hover -->
        <button
            v-if="!isOpen"
            type="button"
            title="Edit"
            class="invisible shrink-0 rounded-md p-1 border text-gray-400 hover:bg-white hover:text-blue-600 group-hover/iefield:visible dark:hover:bg-gray-700 dark:hover:text-blue-400"
            @click.stop="open"
        >
            <BsPencil class="h-4 w-4" />
        </button>
        
        <!-- Popover ─────────────────────────────────────────────────────────── -->
        <div
            v-if="isOpen"
            class="absolute left-0 top-full z-50 mt-1 w-52 rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-700 dark:bg-gray-800"
            @click.stop
        >
            <!-- Error message -->
            <p v-if="errorMsg" class="mb-2 text-xs text-red-500">{{ errorMsg }}</p>

            <!-- Select -->
            <select
                v-if="config.type === 'select'"
                ref="inputRef"
                v-model="editValue"
                :disabled="isSaving"
                class="block w-full rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            >
                <option v-for="opt in config.options" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>

            <!-- Text -->
            <input
                v-else
                ref="inputRef"
                v-model="editValue"
                type="text"
                :disabled="isSaving"
                class="block w-full rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                @keydown.enter.prevent="save"
                @keydown.esc.prevent="close"
            />

            <!-- Actions -->
            <div class="mt-2 flex items-center justify-end gap-1.5">
                <button
                    type="button"
                    :disabled="isSaving"
                    class="rounded px-4 py-2 text-xs text-gray-500 hover:bg-gray-100 disabled:opacity-60 dark:hover:bg-gray-700"
                    @click="close"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="isSaving"
                    class="inline-flex items-center gap-1 rounded bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                    @click="save"
                >
                    <svg v-if="isSaving" class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                    </svg>
                    Save
                </button>
            </div>
        </div>
    </div>
</template>
