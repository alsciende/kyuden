<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import type { CardView, GameView, PlayerView } from '@/api/game'
import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import GameCard from '@/components/game/GameCard.vue'
import UnderPile from '@/components/game/UnderPile.vue'
import { printed, provinceCards, provinces, type Cards } from '@/lib/board'
import { hidePreview, showPreview } from '@/lib/preview'
import { vTip } from '@/lib/tooltip'
import { useCardsUnder } from '@/lib/underneath'

/**
 * The provinces of a player, each with what lies on it (dynasty cards, the stronghold) above its name: the card on top,
 * and, when there are several (Rally, a holding beside a card, a card put onto the stronghold province), the depth of
 * the others behind it and a badge with their number, which opens their list, the top first; a card of the list opens
 * like a card of the board. The cards placed under the card on top or under the province (`UnderPile.vue`) show below
 * that card, or by a badge on the name of the province. In a selection, the provinces that can be chosen are the targets of the click.
 */
const props = withDefaults(
  defineProps<{
    game: GameView
    player: PlayerView
    cards: Cards
    size?: 'small' | 'normal'
    /** the provinces that can be chosen, by card id; null: no selection */
    selectable?: string[] | null
    selectedId?: string | null
  }>(),
  { size: 'normal', selectable: null, selectedId: null },
)

const emit = defineEmits<{ open: [id: string]; select: [id: string] }>()

const mine = computed(() => props.player.id === props.game.viewer_id)
const cardsUnder = useCardsUnder()

const list = computed(() =>
  provinces(props.player).map((province) => {
    const card = province.province
    const printedCard = card?.code ? props.cards[card.code] : undefined
    const top = province.stronghold ?? province.cards.at(-1) ?? null
    const stack = provinceCards(province)

    return {
      province,
      top,
      card,
      name: printedCard?.name ?? (card?.code === null ? 'Face-down province' : 'Province'),
      strength: printedCard ? printed(printedCard.strength) : null,
      hidden: card?.code === null,
      /** face down, its owner knowing it: the opponent has not seen it */
      secret: card !== null && card.code !== null && card.orientation === 'face_down',
      stronghold: province.stronghold !== null,
      selectable: card !== null && props.selectable !== null && props.selectable.includes(card.id),
      /** the cards on the province, the top first: listed when there are several */
      stack,
      several: stack.length > 1 && props.selectable === null,
      /** the outlines drawn behind the card on top, two at most */
      depth: Math.min(stack.length - 1, 2),
      /** cards lie under the card on top: their edges below it, its name further down */
      carrying: top !== null && cardsUnder.value.has(top.id),
    }
  }),
)

/** the stack of the province whose list is open */
const openId = ref<string | null>(null)
const openItem = computed(() => list.value.find((item) => item.province.stack.id === openId.value) ?? null)

function toggle(stackId: string): void {
  hidePreview()
  openId.value = openId.value === stackId ? null : stackId
}

function close(): void {
  hidePreview()
  openId.value = null
}

function openCard(id: string): void {
  close()
  emit('open', id)
}

function nameOf(card: CardView): string {
  return (card.code !== null && props.cards[card.code]?.name) || 'Face-down card'
}

function faceOf(card: CardView, item: { province: { stronghold: CardView | null } }): string {
  if (card === item.province.stronghold) {
    return 'Stronghold'
  }

  return card.orientation === 'face_down' ? 'Face down' : 'Face up'
}

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    close()
  }
}

watch(openId, (current, previous) => {
  if (current !== null && previous === null) {
    window.addEventListener('keydown', onKey)
  } else if (current === null) {
    window.removeEventListener('keydown', onKey)
  }
})

// a province whose list is open can lose its cards (played, discarded): the list closes
watch(openItem, (item) => {
  if (openId.value !== null && (item === null || !item.several)) {
    openId.value = null
  }
})

onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

function clickProvince(id: string, selectable: boolean): void {
  if (selectable) {
    emit('select', id)
  } else {
    emit('open', id)
  }
}
</script>

<template>
  <div class="provinces" :class="size">
    <div v-for="item in list" :key="item.province.stack.id" class="province" :class="{ carrying: item.carrying }">
      <div class="slot">
        <span
          v-for="layer in item.depth"
          :key="layer"
          class="depth"
          :style="{ transform: `translate(${layer * 5}px, ${-layer * 5}px)`, zIndex: -layer }"
          aria-hidden="true"
        />
        <button
          v-if="item.several"
          type="button"
          class="more"
          :class="{ open: openId === item.province.stack.id }"
          :aria-label="`${item.name}: ${item.stack.length} cards, show them`"
          :aria-expanded="openId === item.province.stack.id"
          @click="toggle(item.province.stack.id)"
        >
          +{{ item.stack.length - 1 }}
        </button>
        <UnderPile
          v-if="item.top"
          :host="item.top"
          :cards="cards"
          :mine="mine"
          :size="size"
          @open="emit('open', $event)"
        >
          <GameCard
            :card="item.top"
            :cards="cards"
            :mine="mine"
            :size="size"
            :muted="selectable !== null"
            show-cost
            @click="emit('open', item.top.id)"
          />
        </UnderPile>
        <span v-else class="empty" aria-hidden="true" />
      </div>
      <UnderPile
        v-if="item.card"
        :host="item.card"
        :cards="cards"
        :mine="mine"
        :size="size"
        :edges="false"
        @open="emit('open', $event)"
      >
        <button
          type="button"
          class="tag"
          :class="{
            hidden: item.hidden,
            secret: item.secret,
            broken: item.province.broken,
            selectable: item.selectable,
            selected: item.card.id === selectedId,
            muted: selectable !== null && !item.selectable,
          }"
          :aria-pressed="item.selectable ? item.card.id === selectedId : undefined"
          @click="clickProvince(item.card.id, item.selectable)"
          @mouseenter="showPreview(item.card.code, $event.currentTarget as HTMLElement, mine)"
          @mouseleave="hidePreview"
        >
          <span class="tag-name">{{ item.name }}</span>
          <span v-if="item.province.broken" class="tag-value">Broken</span>
          <span v-else-if="item.strength !== null" class="tag-value">{{ item.strength }}</span>
          <span v-if="item.secret" v-tip="'Not revealed: Opp. has not seen it'" class="tag-state">Face down</span>
          <span v-if="item.stronghold" class="visually-hidden">(stronghold province)</span>
        </button>
      </UnderPile>
      <template v-if="openId === item.province.stack.id">
        <div class="backdrop" @click="close" />
        <div
          class="popover"
          :class="mine ? 'up' : 'down'"
          role="dialog"
          :aria-label="`${item.name} · ${item.stack.length} cards`"
        >
          <div class="head">
            <span class="caption">{{ item.name }} · {{ item.stack.length }} cards</span>
            <button type="button" class="close" aria-label="Close" @click="close">
              <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true">
                <path d="M2 2l10 10M12 2L2 12" stroke="currentColor" stroke-width="1.8" fill="none" />
              </svg>
            </button>
          </div>
          <span class="hint">Top first · click a card to open it</span>
          <div class="pile">
            <button
              v-for="(card, index) in item.stack"
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
              <span class="pile-face">{{ faceOf(card, item) }}</span>
            </button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
/* the cards beneath the one on top: their edges, behind it */
.depth {
  position: absolute;
  left: 0;
  bottom: 0;
  width: 84px;
  height: 118px;
  box-sizing: border-box;
  border: 1.5px solid var(--ink);
  border-radius: 4px;
  background: var(--paper-2);
}

.small .depth {
  width: 58px;
  height: 81px;
}

.more {
  position: absolute;
  z-index: 3;
  left: -11px;
  bottom: 103px;
  font: inherit;
  min-width: 30px;
  height: 26px;
  padding: 0 8px;
  box-sizing: border-box;
  border-radius: 13px;
  border: 1.5px solid var(--ink);
  background: var(--board);
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.small .more {
  left: -10px;
  bottom: 69px;
  min-width: 24px;
  height: 22px;
  padding: 0 6px;
  font-size: 11px;
}

@media (hover: hover) {
  .more:hover {
    background: var(--paper-2);
  }
}

.more.open {
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
  top: calc(100% + 14px);
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
  white-space: nowrap;
}
.provinces {
  display: flex;
  gap: 18px;
  align-items: flex-start;
}

.small {
  gap: 12px;
}

.province {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 118px;
  align-items: flex-start;
  position: relative;
}

.small .province {
  width: 81px;
}

/* room for the edges of the cards under the card on top, and its badge */
.province.carrying {
  gap: 20px;
}

.slot {
  height: 118px;
  display: flex;
  align-items: flex-end;
  position: relative;
  isolation: isolate;
}

.small .slot {
  height: 81px;
}

.empty {
  width: 84px;
  height: 118px;
  box-sizing: border-box;
  border: 1.5px dashed var(--line);
  border-radius: 4px;
}

.small .empty {
  width: 58px;
  height: 81px;
}

.tag {
  font: inherit;
  color: var(--ink);
  width: 100%;
  min-height: 32px;
  box-sizing: border-box;
  padding: 5px 6px;
  border: 0;
  border-top: 1.5px solid var(--ink);
  background: transparent;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: baseline;
  gap: 2px 6px;
  font-size: 11px;
  text-align: left;
  cursor: pointer;
}

@media (hover: hover) {
  .tag:hover {
    background: var(--paper-2);
  }
}

.tag-name {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 13px;
  line-height: 1.1;
}

.small .tag-name {
  font-size: 11px;
}

.tag-value {
  font-weight: 700;
}

.tag-state {
  flex-basis: 100%;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.secret {
  border-top-style: dashed;
}

.hidden .tag-name {
  color: var(--ink-3);
  font-style: italic;
  font-weight: 500;
}

.broken {
  border-top-style: dashed;
  color: var(--ink-3);
}

.broken .tag-name {
  text-decoration: line-through;
}

.selectable {
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: var(--card);
  min-height: 48px;
}

.selected {
  outline: 3px solid var(--seal);
  outline-offset: 3px;
}

.muted {
  opacity: 0.45;
}
</style>
