<script setup lang="ts">
import { hidePreview, showPreview } from '@/lib/preview'

/**
 * The name of a card in a text, underlined: hovering it shows the image of the card, as on the board.
 */
const props = withDefaults(
  defineProps<{
    code: string
    /** the image goes above the name (the cards of the viewer, the bar), else below */
    above?: boolean
  }>(),
  { above: true },
)

function enter(event: Event): void {
  showPreview(props.code, event.currentTarget as HTMLElement, props.above)
}
</script>

<template>
  <span class="card-name" tabindex="0" @mouseenter="enter" @mouseleave="hidePreview" @focus="enter" @blur="hidePreview"
    ><slot
  /></span>
</template>

<style scoped>
.card-name {
  text-decoration: underline;
  text-decoration-color: var(--ink-3);
  text-decoration-thickness: 1px;
  text-underline-offset: 3px;
  cursor: help;
}

.card-name:hover,
.card-name:focus-visible {
  text-decoration-color: var(--seal);
  outline: none;
}
</style>
