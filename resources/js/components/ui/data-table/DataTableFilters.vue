<script setup>
/**
 * DataTable filter bar — renders as an inline toolbar segment.
 *
 * Place inside <DataTable #toolbar-leading> so it sits on the same row
 * as the per-page selector and the right-side controls.
 *
 * filterDefs shape:
 *   [{ key: 'type', label: 'Type', options: [{ value, label, color? }] }]
 */
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

const props = defineProps({
    filters: { type: Array, required: true },
    activeFilters: { type: Object, required: true },
    activeCount: { type: Number, default: 0 },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(["change", "clear", "clearAll"]);

const panelOpen = ref(false);
const containerRef = ref(null);

/** Active filter chips */
const activeChips = computed(() =>
    props.filters
        .filter(
            (f) =>
                props.activeFilters[f.key] != null &&
                props.activeFilters[f.key] !== "",
        )
        .map((f) => {
            const val = props.activeFilters[f.key];

            // ── Date filters have no options array ──
            if (f.type === "date") {
                return {
                    key: f.key,
                    filterLabel: f.label,
                    valueLabel: String(val),
                    color: null,
                };
            }

            const opt = f.options?.find((o) => String(o.value) === String(val));
            return {
                key: f.key,
                filterLabel: f.label,
                valueLabel: opt ? opt.label : String(val),
                color: opt?.color ?? null,
            };
        }),
);

function handleChange(key, value) {
    emit("change", key, value === "" ? null : value);
}
function handleClear(key) {
    emit("clear", key);
}
function handleClearAll() {
    panelOpen.value = false;
    emit("clearAll");
}

function handleClickOutside(e) {
    if (containerRef.value && !containerRef.value.contains(e.target)) {
        panelOpen.value = false;
    }
}
onMounted(() => document.addEventListener("mousedown", handleClickOutside));
onBeforeUnmount(() =>
    document.removeEventListener("mousedown", handleClickOutside),
);
</script>

<template>
    <!-- Wrapper — relative so the dropdown panel is positioned to this -->
    <div ref="containerRef" class="relative flex items-center gap-2">
        <!-- ── Filter toggle button ──────────────────────────────────────── -->
        <button type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-md border bg-white dark:bg-slate-800 dark:text-white dark:border-gray-600 px-2.5 text-sm font-medium shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
            :class="activeCount > 0
                ? 'border-blue-400 text-blue-600 hover:bg-blue-50'
                : 'border-gray-300 text-gray-600 hover:bg-gray-50'
                " @click="panelOpen = !panelOpen">
            <svg class="h-3.5 w-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
            </svg>
            Filters
            <span v-if="activeCount > 0"
                class="flex h-4 min-w-4 items-center justify-center rounded-full bg-blue-500 px-1 text-[10px] font-bold leading-none text-white">
                {{ activeCount }}
            </span>
            <svg class="h-3 w-3 flex-shrink-0 transition-transform duration-150" :class="panelOpen ? 'rotate-180' : ''"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- ── Active filter chips (inline, next to button) ──────────────── -->
        <div v-if="activeChips.length > 0" class="flex flex-wrap items-center gap-1">
            <span v-for="chip in activeChips" :key="chip.key"
                class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                <span v-if="chip.color" class="h-1.5 w-1.5 flex-shrink-0 rounded-full"
                    :style="{ backgroundColor: chip.color }" />
                {{ chip.filterLabel }}: {{ chip.valueLabel }}
                <button type="button"
                    class="-mr-0.5 ml-0.5 rounded-full p-0.5 text-blue-400 hover:bg-blue-100 hover:text-blue-700 focus:outline-none"
                    :aria-label="`Remove ${chip.filterLabel} filter`" @click="handleClear(chip.key)">
                    <svg class="h-2.5 w-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>
        </div>

        <!-- ── Dropdown filter panel ─────────────────────────────────────── -->
        <div v-if="panelOpen"
            class="absolute left-0 top-full z-50 mt-2 w-screen max-w-[65vw] sm:w-auto sm:min-w-[480px] rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-slate-800 p-4 shadow-xl">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div v-for="filter in filters" :key="filter.key" class="flex flex-col gap-1">
                    <label :for="`filter-${filter.key}`" class="text-xs font-medium text-gray-500 dark:text-white">
                        {{ filter.label }}
                    </label>

                    <input v-if="filter.type === 'date'" type="date" :id="`filter-${filter.key}`"
                        :value="activeFilters[filter.key] ?? ''" :disabled="loading"
                        class="h-9 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-700 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-500 shadow-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-400 disabled:cursor-not-allowed disabled:opacity-60"
                        @input="handleChange(filter.key, $event.target.value)" />

                    <!-- TOGGLE -->
                    <div v-else-if="filter.type === 'toggle'" class="flex py-2">
                        <button type="button" :disabled="loading"
                            @click="handleChange(filter.key, !activeFilters[filter.key])"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none"
                            :class="activeFilters[filter.key]
                                ? 'bg-blue-500'
                                : 'bg-gray-300'">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition duration-200"
                                :class="activeFilters[filter.key]
                                    ? 'translate-x-6'
                                    : 'translate-x-1'" />
                        </button>
                    </div>


                    <v-select v-else-if="filter.options" :options="filter.options" label="label"
                        :reduce="(opt) => opt.value" :modelValue="activeFilters[filter.key]" :disabled="loading"
                        placeholder="All" class="vue-select-custom"
                        @update:modelValue="handleChange(filter.key, $event)" />
                </div>
            </div>

            <!-- Footer -->
            <div v-if="activeCount > 0" class="mt-4 flex justify-end border-t border-gray-100 pt-3">
                <button type="button" class="text-xs font-medium text-red-500 hover:text-red-700"
                    @click="handleClearAll">
                    Clear all
                </button>
            </div>
        </div>
    </div>
</template>
