<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue'

import type { Cards } from '@/lib/board'
import { closeCardImage } from '@/lib/cardImage'

/**
 * The image of a card named in a text (see lib/cardImage), alone in the middle of the darkened screen, read-only: a
 * click anywhere closes it.
 */
const props = defineProps<{ cards: Cards; code: string }>()

const card = computed(() => props.cards[props.code] ?? null)
const name = computed(() => card.value?.name ?? 'Unknown card')
const closeButton = useTemplateRef<HTMLButtonElement>('closeButton')

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    closeCardImage()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKey)
  closeButton.value?.focus()
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="overlay" role="dialog" aria-modal="true" :aria-label="name" @click="closeCardImage">
    <button ref="closeButton" type="button" class="close" aria-label="Close">
      <svg
        aria-hidden="true"
        width="14"
        height="14"
        viewBox="0 0 14 14"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
      >
        <path d="M2 2l10 10M12 2L2 12" />
      </svg>
    </button>
    <img v-if="card?.image_url" class="image" :src="card.image_url" :alt="name" />
    <div v-else class="image blank">{{ name }}</div>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  z-index: 45;
  background: rgba(18, 15, 11, 0.76);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  cursor: pointer;
}

.close {
  position: absolute;
  top: 24px;
  right: 28px;
  width: 44px;
  height: 44px;
  color: var(--paper);
  background: transparent;
  border: 1.5px solid var(--paper);
  border-radius: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.image {
  width: min(400px, calc(100vw - 48px), calc((100vh - 120px) * 0.716));
  aspect-ratio: 300 / 419;
  border-radius: 14px;
  display: block;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.5);
}

.blank {
  background: var(--card);
  color: var(--ink);
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 700;
}
</style>
