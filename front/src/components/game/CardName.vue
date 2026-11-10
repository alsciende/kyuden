<script setup lang="ts">
import { viewCardImage } from '@/lib/cardImage'
import { hidePreview, showPreview } from '@/lib/preview'

/**
 * The name of a card in a text, underlined: hovering it shows the image of the card, as on the board; a click opens
 * the image alone (a touch screen cannot hover it).
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

function view(): void {
  hidePreview()
  viewCardImage(props.code)
}
</script>

<template>
  <span
    class="card-name"
    role="button"
    tabindex="0"
    @mouseenter="enter"
    @mouseleave="hidePreview"
    @focus="enter"
    @blur="hidePreview"
    @click="view"
    @keydown.enter.prevent="view"
    ><slot
  /></span>
</template>

<style scoped>
.card-name {
  text-decoration: underline;
  text-decoration-color: var(--ink-3);
  text-decoration-thickness: 1px;
  text-underline-offset: 3px;
  cursor: pointer;
}

.card-name:focus-visible {
  text-decoration-color: var(--seal);
  outline: none;
}

@media (hover: hover) {
  .card-name:hover {
    text-decoration-color: var(--seal);
    outline: none;
  }
}
</style>
