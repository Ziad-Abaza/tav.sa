<script setup>
import { ref, computed } from "vue";
import { Handle, useVueFlow, useNode } from "@vue-flow/core";
const { removeNodes, nodes, addNodes } = useVueFlow();
const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

const emoji = ref(props.data.emoji || "👍");
const messageId = ref(props.data.messageId || "");
const node = useNode();
// Common reaction emojis
const commonEmojis = [
    "👍",
    "👎",
    "❤️",
    "😂",
    "😮",
    "😢",
    "🙏",
    "👏",
    "🔥",
    "🎉",
];

function updateNodeData() {
    props.data.emoji = emoji.value;
    props.data.messageId = messageId.value;
}
function handleClickDelete() {
    removeNodes(node.id);
}

function handleClickDuplicate() {
    const { type, position, data } = node.node;

    const newNode = {
        id: (nodes.value.length + 1).toString(),
        type,
        position: {
            x: position.x - 100,
            y: position.y - 100,
        },
        data: JSON.parse(JSON.stringify(data)), // Deep copy to prevent shared reference
    };

    addNodes(newNode);
}

const nodeClasses = computed(() => {
    return `reaction-message-node p-3 rounded-lg border-2 ${
        props.selected ? "border-info-500" : "border-gray-200"
    } bg-white shadow`;
});
</script>

<template>
    <div :class="nodeClasses" style="min-width: 220px">
        <Handle type="target" position="top" class="h-2 w-2" />

        <div
            class="node-header mb-2 flex justify-between text-sm font-semibold text-gray-700"
        >
            <span>{{ data.label }}</span>
            <div class="flex items-end">
                <button
                    v-on:click="handleClickDuplicate"
                    class="node-action-btn"
                    title="Copy node"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="h-4 w-4"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75"
                        />
                    </svg>
                </button>
                <button
                    v-on:click="handleClickDelete"
                    class="node-action-btn delete-btn"
                    title="Delete node"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="h-4 w-4"
                    >
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="mb-3">
            <label class="mb-1 block text-xs font-medium text-gray-500"
                >Message ID</label
            >
            <input
                v-model="messageId"
                @input="updateNodeData"
                class="w-full rounded border p-2 text-sm focus:ring-2 focus:ring-info-500"
                placeholder="{{message_id}} or direct ID"
            />
            <p class="mt-1 text-xs text-gray-500">
                ID of the message to react to
            </p>
        </div>

        <div class="mb-3">
            <label class="mb-1 block text-xs font-medium text-gray-500"
                >Emoji Reaction</label
            >
            <input
                v-model="emoji"
                @input="updateNodeData"
                class="w-full rounded border p-2 text-center text-sm text-xl focus:ring-2 focus:ring-info-500"
                maxlength="2"
            />
        </div>

        <div class="emoji-picker mb-3 flex flex-wrap gap-2">
            <button
                v-for="(e, index) in commonEmojis"
                :key="index"
                @click="
                    emoji = e;
                    updateNodeData();
                "
                class="flex h-8 w-8 items-center justify-center rounded text-xl hover:bg-gray-100"
            >
                {{ e }}
            </button>
        </div>

        <Handle type="source" position="bottom" class="h-2 w-2" />
    </div>
</template>
