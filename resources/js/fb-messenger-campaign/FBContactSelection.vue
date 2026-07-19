<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from "vue";
import axios from "axios";
import { BsSearch, BxLoaderAlt } from "@kalimahapps/vue-icons";
import { HiUserGroup } from "@kalimahapps/vue-icons";

const props = defineProps({
    selectAll: { type: Boolean, default: false },
    selectedContacts: { type: Array, default: () => [] },
    statusFilters: { type: Array, default: () => [] },
    sourceFilters: { type: Array, default: () => [] },
    groupFilters: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    tenantSubdomain: { type: String, required: true },
});

const emit = defineEmits([
    "update:selectAll",
    "update:selectedContacts",
    "update:statusFilters",
    "update:sourceFilters",
    "update:groupFilters",
    "contactCountChanged",
]);

// Local state
const contacts = ref([]);
const contactsLoading = ref(false);
const contactsPage = ref(1);
const contactsHasMore = ref(true);
const contactSearch = ref("");
const contactSearching = ref(false);
const isSearchMode = ref(false);
const listSelectAll = ref(false);
const searchTotalCount = ref(0);
const totalContacts = ref(0);
const contactContainer = ref(null);
const statusDropdownRef = ref(null);
const sourceDropdownRef = ref(null);
const groupDropdownRef = ref(null);

const localSelectAll = computed({
    get: () => props.selectAll,
    set: (val) => emit("update:selectAll", val),
});

const localSelectedContacts = computed({
    get: () => props.selectedContacts,
    set: (val) => emit("update:selectedContacts", val),
});

const localStatusFilters = computed({
    get: () => props.statusFilters,
    set: (val) => emit("update:statusFilters", val),
});

const localSourceFilters = computed({
    get: () => props.sourceFilters,
    set: (val) => emit("update:sourceFilters", val),
});

const localGroupFilters = computed({
    get: () => props.groupFilters,
    set: (val) => emit("update:groupFilters", val),
});

// Dropdown states
const dropdownStates = ref({
    status: { open: false, search: "" },
    source: { open: false, search: "" },
    group: { open: false, search: "" },
});

// ─── Contacts API ─────────────────────────────────────────────────────────────

async function loadContacts(page = 1, append = false) {
    if (contactsLoading.value) return;
    contactsLoading.value = true;

    try {
        const response = await axios.post(
            `/${props.tenantSubdomain}/facebook-messenger/campaign/contacts-list`,
            {
                page,
                status_ids: localStatusFilters.value,
                source_ids: localSourceFilters.value,
                group_ids: localGroupFilters.value,
            },
        );

        if (response.data.success) {
            contacts.value = append
                ? [...contacts.value, ...response.data.data]
                : response.data.data;
            totalContacts.value = response.data.total;
            contactsHasMore.value = response.data.has_more;
            contactsPage.value = response.data.current_page;
        }
    } catch (e) {
        console.error("[FBContactSelection] loadContacts error:", e);
    } finally {
        contactsLoading.value = false;
    }
}

async function searchContacts() {
    if (!contactSearch.value.trim()) {
        isSearchMode.value = false;
        loadContacts();
        return;
    }

    contactSearching.value = true;
    isSearchMode.value = true;

    try {
        const response = await axios.post(
            `/${props.tenantSubdomain}/facebook-messenger/campaign/contacts-search`,
            {
                search: contactSearch.value,
                status_ids: localStatusFilters.value,
                source_ids: localSourceFilters.value,
                group_ids: localGroupFilters.value,
            },
        );

        if (response.data.success) {
            contacts.value = response.data.data;
            searchTotalCount.value =
                response.data.total ?? contacts.value.length;
        }
    } catch (e) {
        console.error("[FBContactSelection] searchContacts error:", e);
    } finally {
        contactSearching.value = false;
    }
}

async function updateContactCount() {
    try {
        const response = await axios.post(
            `/${props.tenantSubdomain}/facebook-messenger/campaign/contacts-count`,
            {
                select_all: localSelectAll.value,
                contact_ids: localSelectedContacts.value,
                status_ids: localStatusFilters.value,
                source_ids: localSourceFilters.value,
                group_ids: localGroupFilters.value,
            },
        );
        totalContacts.value = response.data.count;
        emit("contactCountChanged", response.data.count);
    } catch (e) {
        console.error("[FBContactSelection] updateContactCount error:", e);
    }
}

// ─── Handlers ─────────────────────────────────────────────────────────────────

function handleSelectAllChange() {
    const newVal = !localSelectAll.value;
    emit("update:selectAll", newVal);
    if (newVal) emit("update:selectedContacts", []);
    updateContactCount();
}

function toggleDropdown(type) {
    Object.keys(dropdownStates.value).forEach((key) => {
        if (key !== type) dropdownStates.value[key].open = false;
    });
    dropdownStates.value[type].open = !dropdownStates.value[type].open;
}

function closeDropdown(type) {
    dropdownStates.value[type].open = false;
    dropdownStates.value[type].search = "";
}

function getFilteredOptions(type) {
    const options =
        type === "status"
            ? props.statuses
            : type === "source"
              ? props.sources
              : props.groups;
    const searchTerm = dropdownStates.value[type].search.toLowerCase();
    if (!searchTerm) return options;
    return options.filter((opt) => opt.name.toLowerCase().includes(searchTerm));
}

function getDisplayText(type) {
    const filters =
        type === "status"
            ? localStatusFilters.value
            : type === "source"
              ? localSourceFilters.value
              : localGroupFilters.value;
    if (filters.length === 0) {
        return type === "status"
            ? "All Statuses"
            : type === "source"
              ? "All Sources"
              : "All Groups";
    }
    const options =
        type === "status"
            ? props.statuses
            : type === "source"
              ? props.sources
              : props.groups;
    if (filters.length === 1) {
        const selected = options.find((opt) => opt.id === filters[0]);
        return selected ? selected.name : "Select...";
    }
    return `${filters.length} selected`;
}

function getSelectedFilterCount(type) {
    return type === "status"
        ? localStatusFilters.value.length
        : type === "source"
          ? localSourceFilters.value.length
          : localGroupFilters.value.length;
}

function isFilterSelected(type, id) {
    const filters =
        type === "status"
            ? localStatusFilters.value
            : type === "source"
              ? localSourceFilters.value
              : localGroupFilters.value;
    return filters.includes(id);
}

function toggleFilterOption(type, id) {
    if (type === "status") {
        const idx = localStatusFilters.value.indexOf(id);
        emit(
            "update:statusFilters",
            idx > -1
                ? localStatusFilters.value.filter((f) => f !== id)
                : [...localStatusFilters.value, id],
        );
    } else if (type === "source") {
        const idx = localSourceFilters.value.indexOf(id);
        emit(
            "update:sourceFilters",
            idx > -1
                ? localSourceFilters.value.filter((f) => f !== id)
                : [...localSourceFilters.value, id],
        );
    } else {
        const idx = localGroupFilters.value.indexOf(id);
        emit(
            "update:groupFilters",
            idx > -1
                ? localGroupFilters.value.filter((f) => f !== id)
                : [...localGroupFilters.value, id],
        );
    }
}

function selectAllFilters(type) {
    const options =
        type === "status"
            ? props.statuses
            : type === "source"
              ? props.sources
              : props.groups;
    const ids = options.map((opt) => opt.id);
    if (type === "status") emit("update:statusFilters", ids);
    else if (type === "source") emit("update:sourceFilters", ids);
    else emit("update:groupFilters", ids);
}

function clearAllFilters(type) {
    if (type === "status") emit("update:statusFilters", []);
    else if (type === "source") emit("update:sourceFilters", []);
    else emit("update:groupFilters", []);
}

function toggleListSelectAll() {
    if (!listSelectAll.value) {
        const visibleIds = contacts.value.map((c) => c.id);
        emit("update:selectedContacts", [
            ...new Set([...localSelectedContacts.value, ...visibleIds]),
        ]);
    } else {
        const visibleIds = contacts.value.map((c) => c.id);
        emit(
            "update:selectedContacts",
            localSelectedContacts.value.filter(
                (id) => !visibleIds.includes(id),
            ),
        );
    }
    listSelectAll.value = !listSelectAll.value;
}

function exitSearchMode() {
    contactSearch.value = "";
    isSearchMode.value = false;
    loadContacts();
}

function toggleContactSelection(contactId) {
    const index = localSelectedContacts.value.indexOf(contactId);
    if (index > -1) {
        emit(
            "update:selectedContacts",
            localSelectedContacts.value.filter((id) => id !== contactId),
        );
    } else {
        emit("update:selectedContacts", [
            ...localSelectedContacts.value,
            contactId,
        ]);
    }
}

function handleContactScroll(event) {
    if (isSearchMode.value || !contactsHasMore.value || contactsLoading.value)
        return;
    const el = event.target;
    if (el.scrollHeight - el.scrollTop - el.clientHeight < 50) {
        loadContacts(contactsPage.value + 1, true);
    }
}

function handleDocumentClick(e) {
    if (statusDropdownRef.value && !statusDropdownRef.value.contains(e.target))
        closeDropdown("status");
    if (sourceDropdownRef.value && !sourceDropdownRef.value.contains(e.target))
        closeDropdown("source");
    if (groupDropdownRef.value && !groupDropdownRef.value.contains(e.target))
        closeDropdown("group");
}

// ─── Watchers ─────────────────────────────────────────────────────────────────

let searchTimeout = null;
watch(contactSearch, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => searchContacts(), 400);
});

watch(
    [
        () => props.statusFilters,
        () => props.sourceFilters,
        () => props.groupFilters,
    ],
    () => {
        contactsPage.value = 1;
        loadContacts();
        updateContactCount();
    },
);

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(() => {
    loadContacts();
    updateContactCount();
    document.addEventListener("click", handleDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
    <div
        class="rounded-lg shadow-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700"
    >
        <div
            class="border-b border-slate-200 p-4 dark:border-slate-600 flex items-center gap-4"
        >
            <div class="p-2 rounded-full bg-primary-100 dark:bg-primary-900">
                <HiUserGroup class="h-6 w-6 text-primary-600" />
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-300">
                    Contact Selection
                </h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Choose your target audience
                </p>
            </div>
        </div>

        <div class="px-6 py-4 space-y-4">
            <!-- Select All -->
            <div class="rounded-lg p-3 border dark:border-slate-600">
                <div
                    class="flex flex-col sm:flex-row items-center justify-between gap-4"
                >
                    <div class="flex items-center space-x-3">
                        <input
                            type="checkbox"
                            id="fb_select_all"
                            :checked="localSelectAll"
                            @change="handleSelectAllChange"
                            class="w-5 h-5 text-primary-600 border-2 border-gray-300 rounded focus:ring-primary-500"
                        />
                        <div>
                            <label
                                for="fb_select_all"
                                class="text-base font-medium text-gray-900 dark:text-gray-300"
                            >
                                Select All Contacts
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Automatically include all contacts with Facebook
                                Messenger
                            </p>
                        </div>
                    </div>
                    <span
                        class="text-lg font-semibold text-primary-600 dark:text-primary-400"
                    >
                        {{ totalContacts }}
                        <span class="text-sm font-normal text-gray-500"
                            >contacts</span
                        >
                    </span>
                </div>
            </div>

            <!-- Filter Dropdowns -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Status Filter -->
                <div class="relative" ref="statusDropdownRef">
                    <label
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        >Filter by Status</label
                    >
                    <button
                        type="button"
                        @click="toggleDropdown('status')"
                        class="w-full flex items-center justify-between rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        <span class="truncate">{{
                            getDisplayText("status")
                        }}</span>
                        <svg
                            class="w-4 h-4 ml-2 transition-transform duration-200"
                            :class="{
                                'rotate-180': dropdownStates.status.open,
                            }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <span
                        v-if="getSelectedFilterCount('status') > 0"
                        class="absolute -top-1 -right-1 w-5 h-5 bg-primary-600 text-white text-xs rounded-full flex items-center justify-center"
                    >
                        {{ getSelectedFilterCount("status") }}
                    </span>
                    <div
                        v-show="dropdownStates.status.open"
                        class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-64 overflow-hidden"
                    >
                        <div
                            class="p-2 border-b border-gray-200 dark:border-gray-700"
                        >
                            <input
                                v-model="dropdownStates.status.search"
                                type="text"
                                placeholder="Search..."
                                class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                        <div
                            class="flex items-center justify-between p-2 border-b border-gray-200 dark:border-gray-700 text-xs"
                        >
                            <button
                                type="button"
                                @click="selectAllFilters('status')"
                                class="text-primary-600 hover:text-primary-700 font-medium"
                            >
                                Select All
                            </button>
                            <button
                                type="button"
                                @click="clearAllFilters('status')"
                                class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            >
                                Clear All
                            </button>
                        </div>
                        <div class="overflow-y-auto max-h-40">
                            <div
                                v-for="option in getFilteredOptions('status')"
                                :key="option.id"
                                @click="toggleFilterOption('status', option.id)"
                                class="flex items-center px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        isFilterSelected('status', option.id)
                                    "
                                    class="w-4 h-4 text-primary-600 rounded mr-2"
                                    @click.stop
                                />
                                <span
                                    class="text-sm text-gray-700 dark:text-gray-300"
                                    >{{ option.name }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Source Filter -->
                <div class="relative" ref="sourceDropdownRef">
                    <label
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        >Filter by Source</label
                    >
                    <button
                        type="button"
                        @click="toggleDropdown('source')"
                        class="w-full flex items-center justify-between rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        <span class="truncate">{{
                            getDisplayText("source")
                        }}</span>
                        <svg
                            class="w-4 h-4 ml-2 transition-transform duration-200"
                            :class="{
                                'rotate-180': dropdownStates.source.open,
                            }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <span
                        v-if="getSelectedFilterCount('source') > 0"
                        class="absolute -top-1 -right-1 w-5 h-5 bg-primary-600 text-white text-xs rounded-full flex items-center justify-center"
                    >
                        {{ getSelectedFilterCount("source") }}
                    </span>
                    <div
                        v-show="dropdownStates.source.open"
                        class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-64 overflow-hidden"
                    >
                        <div
                            class="p-2 border-b border-gray-200 dark:border-gray-700"
                        >
                            <input
                                v-model="dropdownStates.source.search"
                                type="text"
                                placeholder="Search..."
                                class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                        <div
                            class="flex items-center justify-between p-2 border-b border-gray-200 dark:border-gray-700 text-xs"
                        >
                            <button
                                type="button"
                                @click="selectAllFilters('source')"
                                class="text-primary-600 hover:text-primary-700 font-medium"
                            >
                                Select All
                            </button>
                            <button
                                type="button"
                                @click="clearAllFilters('source')"
                                class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            >
                                Clear All
                            </button>
                        </div>
                        <div class="overflow-y-auto max-h-40">
                            <div
                                v-for="option in getFilteredOptions('source')"
                                :key="option.id"
                                @click="toggleFilterOption('source', option.id)"
                                class="flex items-center px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        isFilterSelected('source', option.id)
                                    "
                                    class="w-4 h-4 text-primary-600 rounded mr-2"
                                    @click.stop
                                />
                                <span
                                    class="text-sm text-gray-700 dark:text-gray-300"
                                    >{{ option.name }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Group Filter -->
                <div class="relative" ref="groupDropdownRef">
                    <label
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        >Filter by Groups</label
                    >
                    <button
                        type="button"
                        @click="toggleDropdown('group')"
                        class="w-full flex items-center justify-between rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        <span class="truncate">{{
                            getDisplayText("group")
                        }}</span>
                        <svg
                            class="w-4 h-4 ml-2 transition-transform duration-200"
                            :class="{ 'rotate-180': dropdownStates.group.open }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <span
                        v-if="getSelectedFilterCount('group') > 0"
                        class="absolute -top-1 -right-1 w-5 h-5 bg-primary-600 text-white text-xs rounded-full flex items-center justify-center"
                    >
                        {{ getSelectedFilterCount("group") }}
                    </span>
                    <div
                        v-show="dropdownStates.group.open"
                        class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-64 overflow-hidden"
                    >
                        <div
                            class="p-2 border-b border-gray-200 dark:border-gray-700"
                        >
                            <input
                                v-model="dropdownStates.group.search"
                                type="text"
                                placeholder="Search..."
                                class="w-full px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded focus:outline-none focus:ring-1 focus:ring-primary-500"
                            />
                        </div>
                        <div
                            class="flex items-center justify-between p-2 border-b border-gray-200 dark:border-gray-700 text-xs"
                        >
                            <button
                                type="button"
                                @click="selectAllFilters('group')"
                                class="text-primary-600 hover:text-primary-700 font-medium"
                            >
                                Select All
                            </button>
                            <button
                                type="button"
                                @click="clearAllFilters('group')"
                                class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            >
                                Clear All
                            </button>
                        </div>
                        <div class="overflow-y-auto max-h-40">
                            <div
                                v-for="option in getFilteredOptions('group')"
                                :key="option.id"
                                @click="toggleFilterOption('group', option.id)"
                                class="flex items-center px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        isFilterSelected('group', option.id)
                                    "
                                    class="w-4 h-4 text-primary-600 rounded mr-2"
                                    @click.stop
                                />
                                <span
                                    class="text-sm text-gray-700 dark:text-gray-300"
                                    >{{ option.name }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact List (only when not selectAll) -->
            <div
                :class="{
                    'opacity-50 pointer-events-none': localSelectAll,
                }"
            >
                <div class="flex items-center justify-between mb-4">
                    <h3
                        class="text-lg font-medium text-gray-900 dark:text-gray-300"
                    >
                        Select Contacts
                    </h3>
                    <span
                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 text-primary-800 dark:text-primary-500"
                    >
                        <span class="mr-1">{{
                            localSelectedContacts.length
                        }}</span>
                        selected
                    </span>
                </div>

                <div
                    class="border border-gray-200 dark:border-slate-600 rounded-xl overflow-hidden"
                >
                    <!-- Search Bar -->
                    <div
                        class="flex items-center justify-between bg-gray-50 dark:bg-gray-900 px-2 py-1 space-x-2"
                    >
                        <div class="flex items-center flex-1 space-x-2">
                            <BsSearch class="w-4 h-4 text-gray-400" />
                            <input
                                v-model="contactSearch"
                                type="text"
                                placeholder="Search all contacts..."
                                autocomplete="off"
                                class="flex-1 text-xs border-0 bg-transparent focus:ring-0 text-gray-800 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
                            />
                            <BxLoaderAlt
                                v-if="contactSearching"
                                class="w-4 h-4 animate-spin text-primary-600"
                            />
                            <button
                                v-if="contactSearch && isSearchMode"
                                @click="exitSearchMode"
                                class="text-gray-400 hover:text-gray-600"
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
                        </div>

                        <div class="flex items-center space-x-2">
                            <span
                                v-if="isSearchMode && !contactSearching"
                                class="text-xs text-primary-600 dark:text-primary-400 mr-2 bg-primary-50 dark:bg-primary-900/30 px-2 py-1 rounded"
                            >
                                {{ searchTotalCount }} search results
                            </span>
                            <span
                                v-if="
                                    !isSearchMode &&
                                    !contactSearch &&
                                    !contactsLoading
                                "
                                class="text-xs text-gray-500 dark:text-gray-400 mr-2"
                            >
                                {{ totalContacts }} total contacts
                            </span>
                            <label
                                class="flex items-center space-x-2 text-xs text-gray-600 dark:text-gray-300"
                            >
                                <input
                                    type="checkbox"
                                    v-model="listSelectAll"
                                    @click="toggleListSelectAll"
                                    class="w-4 h-4 text-primary-600 border-gray-300 dark:border-gray-600 rounded"
                                />
                                <span>{{
                                    isSearchMode
                                        ? "Select all search results"
                                        : "Select all listed"
                                }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Contact List -->
                    <div
                        class="max-h-80 overflow-y-auto"
                        @scroll="handleContactScroll"
                        ref="contactContainer"
                    >
                        <div
                            v-if="contactsLoading && contacts.length === 0"
                            class="flex items-center justify-center py-8 text-sm text-gray-500"
                        >
                            <BxLoaderAlt
                                class="w-5 h-5 animate-spin text-primary-600 mr-2"
                            />
                            Loading contacts...
                        </div>
                        <div
                            v-else-if="
                                !contactsLoading && contacts.length === 0
                            "
                            class="flex items-center justify-center py-8 text-sm text-gray-500"
                        >
                            <p>No contacts with Facebook Messenger found.</p>
                        </div>
                        <div
                            v-else
                            class="divide-y divide-gray-100 dark:divide-gray-700"
                        >
                            <div
                                v-for="contact in contacts"
                                :key="contact.id"
                                class="px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer flex items-center justify-between"
                                @click="toggleContactSelection(contact.id)"
                            >
                                <div
                                    class="flex items-center space-x-3 flex-1 min-w-0"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="
                                            localSelectedContacts.includes(
                                                contact.id,
                                            )
                                        "
                                        class="w-4 h-4 text-primary-600 border-gray-300 rounded"
                                        @click.stop
                                        @change="
                                            toggleContactSelection(contact.id)
                                        "
                                    />
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-sm font-medium text-gray-900 dark:text-gray-200 truncate"
                                        >
                                            {{ contact.name }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400 truncate"
                                        >
                                            {{
                                                contact.phone ||
                                                contact.facebook_psid
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-if="contactsLoading && contacts.length > 0"
                            class="flex items-center justify-center py-4 text-sm text-gray-500"
                        >
                            <BxLoaderAlt
                                class="w-4 h-4 animate-spin text-primary-600 mr-2"
                            />
                            Loading more...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
