<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch, type CSSProperties } from 'vue'

import { TOOLTIP_WIDTH, tooltip, tooltipPosition } from '@/lib/tooltip'

/**
 * The text of the element under the pointer (see lib/tooltip).
 */
const element = useTemplateRef<HTMLElement>('element')
const height = ref(0)

// placed once its height is known
watch(
  () => tooltip.text,
  async () => {
    height.value = 0
    await nextTick()
    height.value = element.value?.offsetHeight ?? 0
  },
)

const style = computed((): CSSProperties => {
  if (tooltip.rect === null) {
    return {}
  }
  const { left, top } = tooltipPosition(tooltip.rect, height.value, window.innerWidth, window.innerHeight)

  return {
    left: `${left}px`,
    top: `${top}px`,
    width: `${TOOLTIP_WIDTH}px`,
    visibility: height.value ? 'visible' : 'hidden',
  }
})
</script>

<template>
  <div v-if="tooltip.text && tooltip.rect" ref="element" class="tooltip" role="tooltip" :style="style">
    {{ tooltip.text }}
  </div>
</template>

<style scoped>
.tooltip {
  position: fixed;
  z-index: 60;
  box-sizing: border-box;
  padding: 8px 11px;
  border-radius: 2px;
  background: var(--ink);
  color: var(--paper);
  font-size: 12px;
  line-height: 1.4;
  pointer-events: none;
  box-shadow: 0 8px 20px rgba(31, 27, 22, 0.25);
}
</style>
