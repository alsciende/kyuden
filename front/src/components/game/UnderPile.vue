<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import type { CardView } from '@/api/game'
import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import type { Cards } from '@/lib/board'
import { hidePreview, showPreview } from '@/lib/preview'
import { useCardsUnder } from '@/lib/underneath'

/**
 * A card of the board (the default slot) with the cards placed under it (Back-Alley Hideaway, Kaiu Shihobu, Under
 * Siege): their edges below it and a badge with their number at its foot, which opens their list, the last placed
 * first, face-down ones by their back when the viewer does not know them; a card of the list opens like a card of the
 * board (mockup: screen 2 of `LdtL4NT1SnDLTzRZpz376x`). Without cards under it, the slot alone. `edges: false` puts the
 * badge at the right of the slot, without edges: a province, under its name.
 */
const props = withDefaults(
  defineProps<{
    host: CardView
    cards: Cards
    /** the host belongs to the viewer: the list opens above it */
    mine: boolean
    size?: 'small' | 'normal' | 'large'
    edges?: boolean
  }>(),
  { size: 'normal', edges: true },
)

const emit = defineEmits<{ open: [id: string] }>()

const cardsUnder = useCardsUnder()
const under = computed(() => cardsUnder.value.get(props.host.id) ?? [])
/** the edges drawn below the card, two at most */
const depth = computed(() => (props.edges ? Math.min(under.value.length, 2) : 0))
const hostName = computed(() => (props.host.code !== null && props.cards[props.host.code]?.name) || 'a face-down card')

const open = ref(false)

function toggle(): void {
  hidePreview()
  open.value = !open.value
}

function close(): void {
  hidePreview()
  open.value = false
}

function openCard(id: string): void {
  close()
  emit('open', id)
}

function nameOf(card: CardView): string {
  return (card.code !== null && props.cards[card.code]?.name) || 'Face-down card'
}

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    close()
  }
}

watch(open, (current) =>
  current ? window.addEventListener('keydown', onKey) : window.removeEventListener('keydown', onKey),
)
// the cards under it leave (played, discarded, the host left play): the list closes
watch(
  () => under.value.length,
  (count) => {
    if (count === 0) {
      close()
    }
  },
)
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <slot v-if="under.length === 0" />
  <div v-else class="host" :class="[size, { edges }]">
    <span
      v-for="layer in depth"
      :key="layer"
      class="edge"
      :style="{ transform: `translateY(${layer * 5}px)`, zIndex: -layer }"
      aria-hidden="true"
    />
    <slot />
    <button
      type="button"
      class="badge"
      :class="{ open }"
      :aria-label="`${under.length} card${under.length === 1 ? '' : 's'} under ${hostName}, ${open ? 'hide' : 'show'} them`"
      :aria-expanded="open"
      @click="toggle"
    >
      <svg
        width="10"
        height="10"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        aria-hidden="true"
      >
        <path :d="open ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6'" />
      </svg>
      {{ under.length }} under
    </button>
    <template v-if="open">
      <div class="backdrop" @click="close" />
      <div
        class="popover"
        :class="mine ? 'up' : 'down'"
        role="dialog"
        :aria-label="`Under ${hostName} · ${under.length} cards`"
      >
        <div class="head">
          <span class="caption">Under {{ hostName }} · {{ under.length }}</span>
          <button type="button" class="close" aria-label="Close" @click="close">
            <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true">
              <path d="M2 2l10 10M12 2L2 12" stroke="currentColor" stroke-width="1.8" fill="none" />
            </svg>
          </button>
        </div>
        <span class="hint">Last placed first · click a card to open it</span>
        <div class="pile">
          <button
            v-for="({ card, note }, index) in under"
            :key="card.id"
            type="button"
            class="pile-card"
            :class="{ hidden: card.code === null }"
            @click="openCard(card.id)"
            @mouseenter="showPreview(card.code, $event.currentTarget as HTMLElement, mine)"
            @mouseleave="hidePreview"
          >
            <span class="pile-pos">#{{ index + 1 }}</span>
            <ClanMon v-if="card.code === null" :src="enso" :size="20" color="#a89c88" />
            <span class="pile-name">{{ nameOf(card) }}</span>
            <span class="pile-face">{{ note }}</span>
          </button>
        </div>
        <span v-if="edges" class="hint">Discarded once {{ hostName }} leaves its place.</span>
      </div>
    </template>
  </div>
</template>

<style scoped>
.host {
  position: relative;
  display: flex;
  align-items: flex-end;
  isolation: isolate;
}

.host:not(.edges) {
  width: 100%;
}

/* the cards under it: their edges, below it */
.edge {
  position: absolute;
  inset: 0;
  box-sizing: border-box;
  border: 1.5px solid var(--ink);
  border-radius: 4px;
  background: var(--paper-2);
}

.badge {
  position: absolute;
  z-index: 3;
  left: 50%;
  bottom: -9px;
  transform: translateX(-50%);
  font: inherit;
  height: 22px;
  padding: 0 8px;
  box-sizing: border-box;
  border-radius: 11px;
  border: 1.5px solid var(--ink);
  background: var(--board);
  color: var(--ink);
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 4px;
  cursor: pointer;
}

.small .badge {
  height: 20px;
  padding: 0 6px;
  font-size: 10px;
}

/* a province: the badge at the right of its name */
.host:not(.edges) .badge {
  left: auto;
  right: -6px;
  bottom: auto;
  top: -11px;
  transform: none;
}

@media (hover: hover) {
  .badge:hover {
    background: var(--paper-2);
  }
}

.badge.open {
  background: var(--ink);
  color: var(--paper);
}

.backdrop {
  position: fixed;
  inset: 0;
  z-index: 30;
}

.popover {
  position: absolute;
  z-index: 31;
  left: -14px;
  width: 280px;
  box-sizing: border-box;
  background: var(--card);
  border: 1.5px solid var(--ink);
  border-radius: 4px;
  box-shadow: 0 14px 34px rgb(31 27 22 / 0.28);
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.popover.up {
  bottom: calc(100% + 14px);
}

.popover.down {
  top: calc(100% + 18px);
}

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.caption {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.close {
  font: inherit;
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 2px;
  background: transparent;
  color: var(--ink-2);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

@media (hover: hover) {
  .close:hover {
    background: var(--paper-2);
  }
}

.hint {
  font-size: 12px;
  color: var(--ink-2);
}

.pile {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 260px;
  overflow-y: auto;
}

.pile-card {
  font: inherit;
  text-align: left;
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 6px 10px;
  border: 1.5px solid var(--ink);
  border-radius: 3px;
  background: var(--board);
  color: var(--ink);
  cursor: pointer;
}

@media (hover: hover) {
  .pile-card:hover {
    background: var(--paper-2);
  }
}

.pile-card.hidden {
  background: var(--paper-2);
  border-style: dashed;
}

.pile-pos {
  width: 20px;
  font-size: 11px;
  font-weight: 700;
  color: var(--ink-3);
}

.pile-name {
  flex: 1;
  font-family: var(--font-display);
  font-size: 15px;
  font-weight: 700;
}

.pile-face {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ink-2);
  text-align: right;
}
</style>
