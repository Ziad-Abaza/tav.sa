<script setup>
import { ref, computed, watch, onMounted, nextTick } from "vue";
import {
    BxLoaderAlt,
    BsSearch,
    BsX,
    HiUserGroup,
} from "@kalimahapps/vue-icons";

const props = defineProps({
    relType: {
        type: String,
        required: true,
    },
    selectAll: {
        type: Boolean,
        default: false,
    },
    selectedContacts: {
        type: Array,
        default: () => [],
    },
    statusFilters: {
        type: Array,
        default: () => [],
    },
    sourceFilters: {
        type: Array,
        default: () => [],
    },
    groupFilters: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        required: true,
    },
    sources: {
        type: Array,
        required: true,
    },
    groups: {
        type: Array,
        required: true,
    },
    tenantSubdomain: {
        type: String,
        required: true,
    },
});

const emit = defineEmits([
    "update:selectAll",
    "update:selectedContacts",
    "update:statusFilters",
    "update:sourceFilters",
    "update:groupFilters",
    "contactCountChanged",
]);

// State
const contacts = ref([]);
const filteredContacts = ref([]);
const searchQuery = ref("");
const isLoading = ref(false);
const isSearching = ref(false);
const currentPage = ref(1);
const totalContacts = ref(0);
const hasMore = ref(true);
const listSelectAll = ref(false);
const isInSearchMode = ref(false);
const searchTotalCount = ref(0);
const contactContainer = ref(null);

// Dropdown states
const dropdownStates = ref({
    status: { open: false, search: "" },
    source: { open: false, search: "" },
    group: { open: false, search: "" },
});

// Computed
const hasAnyFilterSelected = computed(() => {
    return (
        props.statusFilters.length > 0 ||
        props.sourceFilters.length > 0 ||
        props.groupFilters.length > 0
    );
});

const recipientCount = computed(() => {
    if (props.selectAll) {
        return totalContacts.value;
    }
    return props.selectedContacts.length;
});

// Filter options
const getFilteredOptions = (type) => {
    const options =
        type === "status"
            ? props.statuses
            : type === "source"
              ? props.sources
              : props.groups;

    const searchTerm = dropdownStates.value[type].search.toLowerCase();
    if (!searchTerm) return options;

    return options.filter((opt) => opt.name.toLowerCase().includes(searchTerm));
};

const getDisplayText = (type) => {
    const filters =
        type === "status"
            ? props.statusFilters
            : type === "source"
              ? props.sourceFilters
              : props.groupFilters;

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
};

const getSelectedFilterCount = (type) => {
    return type === "status"
        ? props.statusFilters.length
        : type === "source"
          ? props.sourceFilters.length
          : props.groupFilters.length;
};

const isFilterSelected = (type, value) => {
    const filters =
        type === "status"
            ? props.statusFilters
            : type === "source"
              ? props.sourceFilters
              : props.groupFilters;
    return filters.includes(value);
};

// Methods
const toggleDropdown = (type) => {
    dropdownStates.value[type].open = !dropdownStates.value[type].open;
};

const closeDropdown = (type) => {
    dropdownStates.value[type].open = false;
};

const toggleFilterOption = (type, value) => {
    const filterProp =
        type === "status"
            ? "statusFilters"
            : type === "source"
              ? "sourceFilters"
              : "groupFilters";

    const currentFilters = props[filterProp];
    const index = currentFilters.indexOf(value);

    let newFilters;
    if (index > -1) {
        newFilters = currentFilters.filter((v) => v !== value);
    } else {
        newFilters = [...currentFilters, value];
    }

    emit(`update:${filterProp}`, newFilters);
};

const selectAllFilters = (type) => {
    const options =
        type === "status"
            ? props.statuses
            : type === "source"
              ? props.sources
              : props.groups;
    const allIds = options.map((opt) => opt.id);

    const filterProp =
        type === "status"
            ? "statusFilters"
            : type === "source"
              ? "sourceFilters"
              : "groupFilters";

    emit(`update:${filterProp}`, allIds);
};

const clearAllFilters = (type) => {
    const filterProp =
        type === "status"
            ? "statusFilters"
            : type === "source"
              ? "sourceFilters"
              : "groupFilters";

    emit(`update:${filterProp}`, []);
};

const loadContacts = async (page = 1, append = false) => {
    if (!props.relType || isLoading.value) return;

    isLoading.value = true;

    try {
        const response = await fetch(
            `/${props.tenantSubdomain}/campaigns/contacts-list`,
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content") || "",
                },
                body: JSON.stringify({
                    rel_type: props.relType,
                    status_ids: props.statusFilters,
                    source_ids: props.sourceFilters,
                    group_ids: props.groupFilters,
                    page: page,
                }),
            },
        );

        const result = await response.json();

        if (result.success) {
            if (append) {
                contacts.value = [...contacts.value, ...result.data];
            } else {
                contacts.value = result.data;
            }

            filteredContacts.value = contacts.value;
            totalContacts.value = result.total;
            hasMore.value = result.has_more;
            currentPage.value = result.current_page;

            emit("contactCountChanged", totalContacts.value);
        }
    } catch (error) {
        const errorMessage = error.message || "An error occurred while loading contacts.";
        showNotification(errorMessage, "danger");
   
    } finally {
        isLoading.value = false;
    }
};

const searchContacts = async () => {
    if (!searchQuery.value.trim() || !props.relType) {
        exitSearchMode();
        return;
    }

    isSearching.value = true;
    isInSearchMode.value = true;

    try {
        const response = await fetch(
            `/${props.tenantSubdomain}/campaigns/contacts-search`,
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content") || "",
                },
                body: JSON.stringify({
                    search: searchQuery.value,
                    rel_type: props.relType,
                    status_ids: props.statusFilters,
                    source_ids: props.sourceFilters,
                    group_ids: props.groupFilters,
                }),
            },
        );

        const result = await response.json();

        if (result.success) {
            filteredContacts.value = result.data;
            searchTotalCount.value = result.total;
        }
    } catch (error) {
        const errorMessage = error.message || "An error occurred while searching contacts.";
        showNotification(errorMessage, "danger");

    } finally {
        isSearching.value = false;
    }
};

const exitSearchMode = () => {
    isInSearchMode.value = false;
    searchTotalCount.value = 0;
    filteredContacts.value = contacts.value;
    searchQuery.value = "";
};

const handleSelectAllChange = () => {
    emit("update:selectAll", !props.selectAll);

    if (!props.selectAll) {
        emit("update:selectedContacts", []);
        listSelectAll.value = false;
    }
};

const toggleListSelectAll = () => {
    const newValue = !listSelectAll.value;
    listSelectAll.value = newValue;

    if (newValue) {
        const allIds = filteredContacts.value.map((c) => c.id);
        emit("update:selectedContacts", allIds);
    } else {
        emit("update:selectedContacts", []);
    }
};

const toggleContactSelection = (contactId) => {
    const index = props.selectedContacts.indexOf(contactId);
    let newSelected;

    if (index > -1) {
        newSelected = props.selectedContacts.filter((id) => id !== contactId);
    } else {
        newSelected = [...props.selectedContacts, contactId];
    }

    emit("update:selectedContacts", newSelected);
};

const checkScrollBottom = async (event) => {
    if (isInSearchMode.value || !hasMore.value || isLoading.value) return;

    const element = event.target;
    const threshold = 50;

    if (
        element.scrollHeight - element.scrollTop - element.clientHeight <
        threshold
    ) {
        await loadContacts(currentPage.value + 1, true);
    }
};

const updateContactCount = async () => {
    if (!props.relType) return;

    try {
        const response = await fetch(
            `/${props.tenantSubdomain}/campaigns/contacts-count`,
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content") || "",
                },
                body: JSON.stringify({
                    rel_type: props.relType,
                    status_ids: props.statusFilters,
                    source_ids: props.sourceFilters,
                    group_ids: props.groupFilters,
                }),
            },
        );

        const result = await response.json();

        if (result.success) {
            totalContacts.value = result.count;
            emit("contactCountChanged", result.count);
        }
    } catch (error) {
        const errorMessage = error.message || "An error occurred while counting contacts.";
        showNotification(errorMessage, "danger");
     
    }
};

// Watchers
watch(
    [
        () => props.relType,
        () => props.statusFilters,
        () => props.sourceFilters,
        () => props.groupFilters,
    ],
    () => {
        currentPage.value = 1;
        hasMore.value = true;
        emit("update:selectedContacts", []);
        listSelectAll.value = false;
        loadContacts(1);
        updateContactCount();
    },
);

watch(searchQuery, () => {
    if (searchQuery.value.trim()) {
        const timeoutId = setTimeout(() => {
            searchContacts();
        }, 500);

        return () => clearTimeout(timeoutId);
    } else {
        exitSearchMode();
    }
});

watch(
    () => props.selectedContacts.length,
    () => {
        listSelectAll.value =
            filteredContacts.value.length > 0 &&
            filteredContacts.value.every((c) =>
                props.selectedContacts.includes(c.id),
            );
    },
);

// Lifecycle
onMounted(() => {
    if (props.relType) {
        loadContacts();
        updateContactCount();
    }
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
            <!-- Select All Toggle -->
            <div class="rounded-lg p-3 border dark:border-slate-600">
                <div
                    class="flex flex-col sm:flex-row items-center justify-between gap-4"
                >
                    <div class="flex items-center space-x-3">
                        <input
                            type="checkbox"
                            id="select_all"
                            :checked="selectAll"
                            @change="handleSelectAllChange"
                            class="w-5 h-5 text-primary-600 border-2 border-gray-300 rounded focus:ring-primary-500"
                        />
                        <div>
                            <label
                                for="select_all"
                                class="text-base font-medium text-gray-900 dark:text-gray-300"
                            >
                                Select All Contacts
                            </label>
                            <p class="text-sm text-gray-500">
                                Automatically include all contacts matching
                                selected relation type
                            </p>
                        </div>
                    </div>
                    <div class="text-center sm:text-right">
                        <div
                            class="text-2xl font-bold text-primary-600 dark:text-primary-500"
                        >
                            {{ recipientCount }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-300">
                            contacts
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Status Filter -->
                <div class="relative">
                    <label
                        class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-300"
                    >
                        Filter by Status
                    </label>
                    <button
                        type="button"
                        @click="toggleDropdown('status')"
                        :disabled="selectAll"
                        class="relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    >
                        <span class="block truncate">{{
                            getDisplayText("status")
                        }}</span>
                        <span
                            class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none"
                        >
                            <svg
                                class="h-5 w-5 text-gray-400 transform transition-transform duration-200"
                                :class="{
                                    'rotate-180': dropdownStates.status.open,
                                }"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </span>
                    </button>

                    <div
                        v-show="dropdownStates.status.open"
                        @click.away="closeDropdown('status')"
                        class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm"
                    >
                        <div
                            class="px-3 py-2 border-b border-gray-200 dark:border-gray-600"
                        >
                            <input
                                type="text"
                                v-model="dropdownStates.status.search"
                                placeholder="Search statuses"
                                class="w-full px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-200"
                            />
                        </div>

                        <div
                            class="px-3 py-2 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700"
                        >
                            <div class="flex justify-between text-xs">
                                <button
                                    type="button"
                                    @click="selectAllFilters('status')"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                                >
                                    Select All
                                </button>
                                <button
                                    type="button"
                                    @click="clearAllFilters('status')"
                                    v-show="
                                        getSelectedFilterCount('status') > 0
                                    "
                                    class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                                >
                                    Clear All
                                </button>
                            </div>
                        </div>

                        <div
                            v-for="option in getFilteredOptions('status')"
                            :key="option.id"
                            class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
                            @click="toggleFilterOption('status', option.id)"
                        >
                            <div class="flex items-center">
                                <input
                                    type="checkbox"
                                    :checked="
                                        isFilterSelected('status', option.id)
                                    "
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-3"
                                />
                                <div class="flex-1">
                                    <div
                                        class="text-sm font-medium text-gray-900 dark:text-gray-200"
                                    >
                                        {{ option.name }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-show="getFilteredOptions('status').length === 0"
                            class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            No statuses found
                        </div>
                    </div>
                </div>

                <!-- Source Filter -->
                <div class="relative">
                    <label
                        class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-300"
                    >
                        Filter by Source
                    </label>
                    <button
                        type="button"
                        @click="toggleDropdown('source')"
                        :disabled="selectAll"
                        class="relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    >
                        <span class="block truncate">{{
                            getDisplayText("source")
                        }}</span>
                        <span
                            class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none"
                        >
                            <svg
                                class="h-5 w-5 text-gray-400 transform transition-transform duration-200"
                                :class="{
                                    'rotate-180': dropdownStates.source.open,
                                }"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </span>
                    </button>

                    <div
                        v-show="dropdownStates.source.open"
                        @click.away="closeDropdown('source')"
                        class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm"
                    >
                        <div
                            class="px-3 py-2 border-b border-gray-200 dark:border-gray-600"
                        >
                            <input
                                type="text"
                                v-model="dropdownStates.source.search"
                                placeholder="Search sources"
                                class="w-full px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-200"
                            />
                        </div>

                        <div
                            class="px-3 py-2 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700"
                        >
                            <div class="flex justify-between text-xs">
                                <button
                                    type="button"
                                    @click="selectAllFilters('source')"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                                >
                                    Select All
                                </button>
                                <button
                                    type="button"
                                    @click="clearAllFilters('source')"
                                    v-show="
                                        getSelectedFilterCount('source') > 0
                                    "
                                    class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                                >
                                    Clear All
                                </button>
                            </div>
                        </div>

                        <div
                            v-for="option in getFilteredOptions('source')"
                            :key="option.id"
                            class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
                            @click="toggleFilterOption('source', option.id)"
                        >
                            <div class="flex items-center">
                                <input
                                    type="checkbox"
                                    :checked="
                                        isFilterSelected('source', option.id)
                                    "
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-3"
                                />
                                <div class="flex-1">
                                    <div
                                        class="text-sm font-medium text-gray-900 dark:text-gray-200"
                                    >
                                        {{ option.name }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-show="getFilteredOptions('source').length === 0"
                            class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            No sources found
                        </div>
                    </div>
                </div>

                <!-- Group Filter -->
                <div class="relative">
                    <label
                        class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-300"
                    >
                        Filter by Groups
                    </label>
                    <button
                        type="button"
                        @click="toggleDropdown('group')"
                        :disabled="selectAll"
                        class="relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"
                    >
                        <span class="block truncate">{{
                            getDisplayText("group")
                        }}</span>
                        <span
                            class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none"
                        >
                            <svg
                                class="h-5 w-5 text-gray-400 transform transition-transform duration-200"
                                :class="{
                                    'rotate-180': dropdownStates.group.open,
                                }"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </span>
                    </button>

                    <div
                        v-show="dropdownStates.group.open"
                        @click.away="closeDropdown('group')"
                        class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm"
                    >
                        <div
                            class="px-3 py-2 border-b border-gray-200 dark:border-gray-600"
                        >
                            <input
                                type="text"
                                v-model="dropdownStates.group.search"
                                placeholder="Search groups"
                                class="w-full px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-200"
                            />
                        </div>

                        <div
                            class="px-3 py-2 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700"
                        >
                            <div class="flex justify-between text-xs">
                                <button
                                    type="button"
                                    @click="selectAllFilters('group')"
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                                >
                                    Select All
                                </button>
                                <button
                                    type="button"
                                    @click="clearAllFilters('group')"
                                    v-show="getSelectedFilterCount('group') > 0"
                                    class="text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                                >
                                    Clear All
                                </button>
                            </div>
                        </div>

                        <div
                            v-for="option in getFilteredOptions('group')"
                            :key="option.id"
                            class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
                            @click="toggleFilterOption('group', option.id)"
                        >
                            <div class="flex items-center">
                                <input
                                    type="checkbox"
                                    :checked="
                                        isFilterSelected('group', option.id)
                                    "
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mr-3"
                                />
                                <div class="flex-1">
                                    <div
                                        class="text-sm font-medium text-gray-900 dark:text-gray-200"
                                    >
                                        {{ option.name }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-show="getFilteredOptions('group').length === 0"
                            class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            No groups found
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact List -->
            <div
                :class="{
                    'opacity-50 pointer-events-none': selectAll,
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
                        <span class="mr-1">{{ selectedContacts.length }}</span>
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
                                type="text"
                                v-model="searchQuery"
                                placeholder="Search all contacts..."
                                autocomplete="off"
                                class="flex-1 text-xs border-0 bg-transparent focus:ring-0 text-gray-800 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400"
                            />

                            <BxLoaderAlt
                                v-if="isSearching"
                                class="w-4 h-4 animate-spin text-primary-600"
                            />

                            <button
                                v-if="searchQuery && isInSearchMode"
                                @click="exitSearchMode"
                                class="text-gray-400 hover:text-gray-600"
                            >
                                <BsX class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="flex items-center space-x-2">
                            <span
                                v-if="isInSearchMode && !isSearching"
                                class="text-xs text-primary-600 dark:text-primary-400 mr-2 bg-primary-50 dark:bg-primary-900/30 px-2 py-1 rounded"
                            >
                                {{ searchTotalCount }} search results
                            </span>

                            <span
                                v-if="
                                    !isInSearchMode &&
                                    !searchQuery &&
                                    !isLoading
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
                                    :disabled="selectAll"
                                    class="w-4 h-4 text-primary-600 border-gray-300 dark:border-gray-600 rounded"
                                />
                                <span>{{
                                    isInSearchMode
                                        ? "Select all search results"
                                        : "Select all listed"
                                }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Contact List -->
                    <div
                        class="max-h-80 overflow-y-auto scrollbar-visible"
                        @scroll="checkScrollBottom"
                        ref="contactContainer"
                    >
                        <!-- Loading State -->
                        <div
                            v-if="isLoading && contacts.length === 0"
                            class="flex items-center justify-center py-8 text-sm text-gray-500"
                        >
                            <BxLoaderAlt
                                class="w-5 h-5 animate-spin text-primary-600 mr-2"
                            />
                            Loading contacts...
                        </div>

                        <!-- Empty State -->
                        <div
                            v-else-if="
                                !isLoading && filteredContacts.length === 0
                            "
                            class="flex items-center justify-center py-8 text-sm text-gray-500"
                        >
                            <p>No contacts found</p>
                        </div>

                        <!-- Contact List Items -->
                        <div
                            v-else
                            class="divide-y divide-gray-100 dark:divide-gray-700"
                        >
                            <div
                                v-for="contact in filteredContacts"
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
                                            selectedContacts.includes(
                                                contact.id,
                                            )
                                        "
                                        class="w-4 h-4 text-primary-600 border-gray-300 rounded"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-sm font-medium text-gray-900 dark:text-gray-200 truncate"
                                        >
                                            {{ contact.firstname }}
                                            {{ contact.lastname }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400 truncate"
                                        >
                                            {{
                                              
                                                contact.phone ||
                                                "No phone"
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Load More Indicator -->
                        <div
                            v-if="isLoading && contacts.length > 0"
                            class="flex items-center justify-center py-4 text-sm text-gray-500"
                        >
                            <BxLoaderAlt
                                class="w-5 h-5 animate-spin text-primary-600 mr-2"
                            />
                            Loading more...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
