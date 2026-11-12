<script setup lang="ts">
import { computed } from 'vue'

import type { Cards } from '@/lib/board'
import { PREVIEW_HEIGHT, PREVIEW_WIDTH, preview, previewPosition } from '@/lib/preview'
import { vTopLayer } from '@/lib/topLayer'

/**
 * The image of the card under the pointer (see lib/preview), kept inside the board: `boardRight` is its right edge.
 * In the top layer: above the windows too.
 */
const props = defineProps<{ cards: Cards; boardRight: number }>()

const image = computed(() => (preview.code === null ? null : (props.cards[preview.code]?.image_url ?? null)))

const style = computed(() => {
  if (preview.rect === null) {
    return {}
  }
  const { left, top } = previewPosition(preview.rect, preview.mine, props.boardRight, window.innerHeight)

  return { left: `${left}px`, top: `${top}px`, width: `${PREVIEW_WIDTH}px`, height: `${PREVIEW_HEIGHT}px` }
})
</script>

<template>
  <img v-if="image && preview.rect" v-top-layer class="preview" :src="image" alt="" :style="style" />
</template>

<style scoped>
.preview {
  position: fixed;
  z-index: 50;
  border-radius: 10px;
  box-shadow: 0 18px 40px rgba(31, 27, 22, 0.35);
  pointer-events: none;
  background: var(--paper-2);
}
</style>
