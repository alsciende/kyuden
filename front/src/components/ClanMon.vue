<script setup lang="ts">
import { computed } from 'vue'

/**
 * A mon (the SVG of a clan, or the ensō of the neutral cards) painted in a color: the SVG is a mask, so that one file
 * gives every color.
 */
const props = withDefaults(
  defineProps<{
    src: string
    color?: string
    size?: number
    /** the accessible name; none for a decorative mon */
    label?: string
  }>(),
  { color: 'var(--ink)', size: 72, label: undefined },
)

const style = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
  backgroundColor: props.color,
  maskImage: `url("${props.src}")`,
  WebkitMaskImage: `url("${props.src}")`,
}))
</script>

<template>
  <span v-if="label" class="mon" :style="style" role="img" :aria-label="label" />
  <span v-else class="mon" :style="style" aria-hidden="true" />
</template>

<style scoped>
.mon {
  display: inline-block;
  flex: none;
  mask-position: center;
  mask-size: contain;
  mask-repeat: no-repeat;
  -webkit-mask-position: center;
  -webkit-mask-size: contain;
  -webkit-mask-repeat: no-repeat;
  transition: background-color 0.2s;
}
</style>
