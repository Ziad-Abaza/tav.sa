<script setup>
import { BaseEdge, EdgeLabelRenderer, getBezierPath, useVueFlow } from '@vue-flow/core';
import { computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
  id: {
    type: String,
    required: true,
  },
  sourceX: {
    type: Number,
    required: true,
  },
  sourceY: {
    type: Number,
    required: true,
  },
  targetX: {
    type: Number,
    required: true,
  },
  targetY: {
    type: Number,
    required: true,
  },
  sourcePosition: {
    type: String,
    required: true,
  },
  targetPosition: {
    type: String,
    required: true,
  },
  markerEnd: {
    type: [String, Object],
    required: false,
    default: undefined,
  },
  style: {
    type: Object,
    required: false,
    default: () => ({}),
  },
  label: {
    type: String,
    required: false,
    default: '',
  },
  data: {
    type: Object,
    required: false,
    default: () => ({}),
  },
  labelStyle: {
    type: Object,
    required: false,
    default: () => ({}),
  },
  labelBgStyle: {
    type: Object,
    required: false,
    default: () => ({}),
  },
  labelShowBg: {
    type: Boolean,
    required: false,
    default: true,
  },
 
});

const { removeEdges } = useVueFlow();

// Get path parameters
const edgePath = computed(() => {
  const [path, labelX, labelY] = getBezierPath({
    sourceX: props.sourceX,
    sourceY: props.sourceY,
    sourcePosition: props.sourcePosition,
    targetX: props.targetX,
    targetY: props.targetY,
    targetPosition: props.targetPosition,
  });

  return { path, labelX, labelY };
});

// Enhanced edge styling with green color
const edgeStyle = computed(() => ({
  stroke: '#616078', 
  strokeWidth: 1,
  fill: 'none',
  ...props.style,
}));

// Generate edge ID for testing and debugging
const edgeId = computed(() => `edge-${props.id}`);

// Handle edge deletion
function deleteEdge(event) {
  event.stopPropagation();
  removeEdges([props.id]);
}


</script>

<script>
export default {
  inheritAttrs: false,
};
</script>

<template>
  <BaseEdge 
    :id="edgeId" 
    :path="edgePath.path" 
    :style="edgeStyle" 
    :marker-end="markerEnd"
   
  />

  <!-- Edge Label -->
  <EdgeLabelRenderer v-if="label">
    <div
      :style="{
        pointerEvents: 'all',
        position: 'absolute',
        transform: `translate(-50%, -50%) translate(${edgePath.labelX}px, ${edgePath.labelY}px)`,
        backgroundColor: labelShowBg ? '#ffffff' : 'transparent',
        padding: labelShowBg ? '2px 4px' : '0',
        borderRadius: '4px',
        fontSize: '10px',
        fontWeight: '500',
        ...labelBgStyle,
      }"
      class="edge-label nodrag nopan"
    >
      <div :style="labelStyle">{{ label }}</div>
    </div>
  </EdgeLabelRenderer>

  <!-- Edge Controls -->
  <EdgeLabelRenderer>
    <div
      :style="{
        pointerEvents: 'all',
        position: 'absolute',
        transform: `translate(-50%, 0%) translate(${edgePath.labelX}px, ${edgePath.labelY - 15}px)`,
      }"
      class="nodrag nopan"
    >
      <div class="edge-controls" :class="{ visible: true }">
        <button class="edge-delete-button" v-on:click="deleteEdge">
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
              d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
            />
          </svg>
        </button>
      </div>
    </div>
  </EdgeLabelRenderer>
</template>

<style scoped>


.edge-controls {
  display: flex;
  align-items: center;
  opacity: 0;
  transition: opacity 0.2s;
  background-color: white;
  border-radius: 4px;
  padding: 2px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
}

.dark .edge-controls {
  background-color: #1f2937; /* Tailwind's gray-800 */
  border: 1px solid #374151; /* Tailwind's gray-700 */
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
}

.edge-controls.visible,
.edge-controls:hover {
  opacity: 1;
}

.edge-delete-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 4px;
  background-color: #fee2e2;
  color: #ef4444;
  transition: all 0.2s;
}

.edge-delete-button:hover {
  background-color: #fecaca;
  color: #dc2626;
}

.dark .edge-delete-button {
  background-color: #7f1d1d; /* dark red background */
  color: #fecaca;
}

.dark .edge-delete-button:hover {
  background-color: #991b1b;
  color: #fee2e2;
}

.edge-label {
  background-color: white;
  padding: 2px 5px;
  border-radius: 4px;
  font-size: 10px;
  text-align: center;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
  pointer-events: none;
}

.dark .edge-label {
  background-color: #1f2937; /* Tailwind gray-800 */
  color: #f9fafb; /* Tailwind gray-50 */
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}
</style>