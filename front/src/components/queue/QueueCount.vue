<script setup lang="ts">
import { computed } from 'vue'

import { queueCountTexts } from '@/lib/useQueueCount'

/**
 * How many players wait in the open queue, so that a player joins it: a box in the tile "Play" of the home, or a line
 * above the button that joins the queue (`inline`). Marked live while somebody waits.
 */
const props = defineProps<{ count: number; inline?: boolean }>()

const texts = computed(() => queueCountTexts(props.count))
</script>

<template>
  <div class="queue-count" :class="{ inline, live: count > 0 }" role="status">
    <span class="dot" aria-hidden="true"></span>
    <span class="texts">
      <span class="title">{{ texts.title }}</span>
      <span class="hint"><span v-if="inline" aria-hidden="true"> · </span>{{ texts.hint }}</span>
    </span>
  </div>
</template>

<style scoped>
.queue-count {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border: 1.5px dashed var(--line);
  border-radius: 2px;
}

.queue-count.live {
  background: var(--selected);
  border: 1.5px solid var(--seal);
}

.dot {
  flex: none;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  border: 1.5px solid var(--ink-3);
}

.live .dot {
  background: var(--seal);
  border-color: var(--seal);
  animation: pulse 2s ease-out infinite;
}

.texts {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.title {
  font-size: 16px;
  font-weight: 600;
}

.hint {
  font-size: 14px;
  color: var(--ink-2);
}

/* a line above the button: no box */
.inline,
.inline.live {
  padding: 0;
  gap: 10px;
  background: none;
  border: none;
}

.inline .texts {
  flex-direction: row;
  align-items: baseline;
  flex-wrap: wrap;
}

.inline .title,
.inline .hint {
  font-size: 15px;
}

@keyframes pulse {
  0% {
    box-shadow: 0 0 0 0 color-mix(in srgb, var(--seal) 45%, transparent);
  }
  70% {
    box-shadow: 0 0 0 9px transparent;
  }
  100% {
    box-shadow: 0 0 0 0 transparent;
  }
}

@media (prefers-reduced-motion: reduce) {
  .live .dot {
    animation: none;
  }
}
</style>
