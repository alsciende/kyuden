<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue'

import type { CardView, GameView, PayloadArgs, PlayerView, Send } from '@/api/game'
import type { MessageType } from '@/api/messages'
import type { Cards } from '@/lib/board'
import { findClan } from '@/data/clans'
import { CONFLICT_ICONS, ELEMENT_ICONS, ELEMENT_NAMES } from '@/lib/icons'
import { playerBar, playerEffects, type BarStat } from '@/lib/playerBar'
import { hidePreview, showPreview } from '@/lib/preview'

/**
 * The bar of a player, above the board for the opponent and below it for the viewer: the player (their name on the tint
 * of their clan, honor, fate, conflicts left, claimed rings), their dynasty cards (intact provinces, deck, discard
 * pile) and their conflict cards (hand, deck, discard pile). A click on a value opens a popover with the effects on it,
 * or the cards of a discard pile, which open like the cards of the board.
 */
const props = defineProps<{
  game: GameView
  player: PlayerView
  cards: Cards
  position: 'top' | 'bottom'
  sending: boolean
  error: string | null
  send: Send
}>()

const emit = defineEmits<{ open: [id: string]; popover: [open: boolean] }>()

interface Stat {
  key: BarStat | null
  label: string
  value: number | string
  /** after the value, smaller: the total of the provinces */
  of?: number
}

const TITLES: Record<BarStat, string> = {
  honor: 'Honor',
  fate: 'Fate',
  hand: 'Hand',
  conflict_deck: 'Conflict deck',
  dynasty_discard: 'Dynasty discard pile',
  conflict_discard: 'Conflict discard pile',
}

const mine = computed(() => props.player.id === props.game.viewer_id)
const tint = computed(() => findClan(props.player.clan)?.tint ?? '#e2d8c4')
const bar = computed(() => playerBar(props.game, props.player))

const general = computed((): Stat[] => [
  { key: 'honor', label: 'Honor', value: bar.value.honor ?? '–' },
  { key: 'fate', label: 'Fate', value: bar.value.fate },
])
const dynasty = computed((): Stat[] => [
  { key: null, label: 'Provinces', value: bar.value.intactProvinces, of: bar.value.provinces },
  { key: null, label: 'Deck', value: bar.value.dynastyDeck },
  { key: 'dynasty_discard', label: 'Discard', value: bar.value.dynastyDiscard.length },
])
const conflict = computed((): Stat[] => [
  { key: 'hand', label: 'Hand', value: bar.value.hand },
  { key: 'conflict_deck', label: 'Deck', value: bar.value.conflictDeck },
  { key: 'conflict_discard', label: 'Discard', value: bar.value.conflictDiscard.length },
])

const sections = computed(() => [
  { name: 'Dynasty', stats: dynasty.value },
  { name: 'Conflict', stats: conflict.value },
])

const conflictsAria = computed(() => `Conflicts left: ${bar.value.conflictsLeft.join(' and ') || 'none'}`)
const ringsAria = computed(
  () => `Claimed rings: ${bar.value.rings.map((element) => ELEMENT_NAMES[element]).join(', ') || 'none'}`,
)

const root = useTemplateRef<HTMLElement>('root')
const open = ref<BarStat | null>(null)
/** the left of the popover in the bar, under the value clicked */
const left = ref(0)
const amount = ref(1)

const POPOVER_WIDTH = 280

function toggle(key: BarStat, event: MouseEvent): void {
  if (open.value === key) {
    close()

    return
  }
  const barRect = root.value!.getBoundingClientRect()
  const anchor = (event.currentTarget as HTMLElement).getBoundingClientRect()
  left.value = Math.max(0, Math.min(anchor.left - barRect.left, barRect.width - POPOVER_WIDTH - 12))
  amount.value = 1
  open.value = key
}

function close(): void {
  hidePreview()
  open.value = null
}

const effects = computed(() =>
  open.value === null ? [] : playerEffects(props.game, props.player, open.value, amount.value),
)
const pile = computed((): CardView[] | null => {
  switch (open.value) {
    case 'dynasty_discard':
      return bar.value.dynastyDiscard
    case 'conflict_discard':
      return bar.value.conflictDiscard
    default:
      return null
  }
})

function nameOf(card: CardView): string {
  return (card.code !== null && props.cards[card.code]?.name) || 'Unknown card'
}

function typeOf(card: CardView): string {
  return (card.code !== null && props.cards[card.code]?.type) || card.side
}

function openCard(id: string): void {
  close()
  emit('open', id)
}

async function run<T extends MessageType>(type: T, ...payload: PayloadArgs<T>): Promise<void> {
  if (await props.send(type, ...payload)) {
    close()
  }
}

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    close()
  }
}

watch(open, (current, previous) => {
  if (current !== null && previous === null) {
    window.addEventListener('keydown', onKey)
    emit('popover', true)
  } else if (current === null) {
    window.removeEventListener('keydown', onKey)
    emit('popover', false)
  }
})

onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <section ref="root" class="bar" :class="position" :aria-label="mine ? 'You' : 'Opponent'">
    <div class="section first" role="group" aria-label="Player">
      <div class="who">
        <span class="name" :style="{ background: tint }">{{ player.name }}</span>
        <span class="caption">{{ mine ? 'You' : 'Opp.' }}</span>
      </div>
      <button
        v-for="stat in general"
        :key="stat.label"
        type="button"
        class="stat"
        :class="{ on: open === stat.key }"
        :aria-expanded="open === stat.key"
        @click="toggle(stat.key!, $event)"
      >
        <b>{{ stat.value }}</b
        ><span>{{ stat.label }}</span>
      </button>
      <div class="stat fixed">
        <b class="icon glyphs" role="img" :aria-label="conflictsAria">
          <span :class="{ used: !bar.conflictsLeft.includes('military') }">{{ CONFLICT_ICONS.military }}</span>
          <span :class="{ used: !bar.conflictsLeft.includes('political') }">{{ CONFLICT_ICONS.political }}</span>
        </b>
        <span>Conflicts</span>
      </div>
      <div class="stat fixed">
        <b v-if="bar.rings.length" class="icon glyphs" role="img" :aria-label="ringsAria">
          <span v-for="element in bar.rings" :key="element">{{ ELEMENT_ICONS[element] }}</span>
        </b>
        <b v-else class="glyphs none" role="img" :aria-label="ringsAria">–</b>
        <span>Rings</span>
      </div>
    </div>

    <div v-for="section in sections" :key="section.name" class="section" role="group" :aria-label="section.name">
      <span class="caption seal">{{ section.name }}</span>
      <template v-for="stat in section.stats" :key="stat.label">
        <button
          v-if="stat.key"
          type="button"
          class="stat"
          :class="{ on: open === stat.key }"
          :aria-expanded="open === stat.key"
          @click="toggle(stat.key, $event)"
        >
          <b>{{ stat.value }}</b
          ><span>{{ stat.label }}</span>
        </button>
        <div v-else class="stat fixed">
          <b
            >{{ stat.value }}<small v-if="stat.of !== undefined">/{{ stat.of }}</small></b
          ><span>{{ stat.label }}</span>
        </div>
      </template>
    </div>

    <div class="badges">
      <span v-for="note in bar.notes" :key="note" class="badge">{{ note }}</span>
      <span v-if="bar.favor" class="badge">
        <span v-if="game.imperial_favor_side" class="icon" aria-hidden="true">{{
          CONFLICT_ICONS[game.imperial_favor_side]
        }}</span>
        Imperial Favor
      </span>
      <span v-if="bar.firstPlayer" class="badge first">First player</span>
    </div>

    <template v-if="open">
      <div class="backdrop" @click="close" />
      <div
        class="popover"
        :class="position"
        :style="{ left: `${left}px`, width: `${POPOVER_WIDTH}px` }"
        role="dialog"
        :aria-label="`${TITLES[open]} · ${mine ? 'You' : 'Opponent'}`"
      >
        <div class="head">
          <span class="caption">{{ TITLES[open] }} · {{ mine ? 'You' : 'Opp.' }}</span>
          <button type="button" class="close" aria-label="Close" @click="close">
            <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true">
              <path d="M2 2l10 10M12 2L2 12" stroke="currentColor" stroke-width="1.8" fill="none" />
            </svg>
          </button>
        </div>

        <template v-if="pile">
          <template v-if="pile.length">
            <span class="hint">Top first · click a card to open it</span>
            <div class="pile">
              <button
                v-for="card in pile"
                :key="card.id"
                type="button"
                class="pile-card"
                @click="openCard(card.id)"
                @mouseenter="showPreview(card.code, $event.currentTarget as HTMLElement, position === 'bottom')"
                @mouseleave="hidePreview"
              >
                <span class="pile-name">{{ nameOf(card) }}</span
                ><span class="pile-type">{{ typeOf(card) }}</span>
              </button>
            </div>
          </template>
          <p v-else class="hint">The pile is empty.</p>
        </template>

        <template v-else>
          <div class="amount">
            <span>Amount</span>
            <button type="button" class="step" aria-label="Less" :disabled="amount <= 0" @click="amount--">−</button>
            <b aria-live="polite">{{ amount }}</b>
            <button type="button" class="step" aria-label="More" @click="amount++">+</button>
          </div>
          <div v-if="effects.length" class="effects">
            <button
              v-for="(effect, index) in effects"
              :key="effect.type"
              type="button"
              class="effect"
              :class="{ primary: index === 0 }"
              :disabled="sending"
              @click="run(effect.type, effect.payload)"
            >
              {{ effect.label }}
            </button>
          </div>
          <p v-else class="hint">Nothing to do now.</p>
        </template>

        <p v-if="error" class="error" role="alert">{{ error }}</p>
      </div>
    </template>
  </section>
</template>

<style scoped>
.bar {
  grid-column: 1 / -1;
  position: relative;
  z-index: 5;
  height: 60px;
  box-sizing: border-box;
  padding: 0 24px;
  display: flex;
  align-items: center;
  background: var(--paper-2);
}

.bar.top {
  border-bottom: 1px solid var(--line);
}

.bar.bottom {
  border-top: 1px solid var(--line);
}

.section {
  display: flex;
  align-items: center;
  gap: 2px;
  height: 44px;
  padding: 0 14px;
  border-left: 1px solid var(--line);
}

.section.first {
  border-left: 0;
  padding-left: 0;
}

.who {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 180px;
  min-width: 0;
  margin-right: 6px;
}

/* the tints are light whatever the theme: the name keeps the ink of the day */
.name {
  padding: 2px 8px;
  border-radius: 2px;
  color: #1f1b16;
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

.caption {
  flex: none;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.caption.seal {
  color: var(--seal);
  margin-right: 8px;
}

.stat {
  font: inherit;
  color: var(--ink);
  background: transparent;
  border: 0;
  border-radius: 3px;
  padding: 5px 10px;
  min-height: 48px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 1px;
  cursor: pointer;
}

.stat:hover {
  background: var(--paper-3);
}

.stat.on {
  background: var(--ink);
  color: var(--paper);
}

.stat.fixed {
  cursor: default;
}

.stat.fixed:hover {
  background: transparent;
}

.stat b {
  font-family: var(--font-display);
  font-size: 24px;
  line-height: 1;
}

.stat small {
  font-family: var(--font-ui);
  font-size: 13px;
  font-weight: 500;
  color: var(--ink-3);
}

.stat > span {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ink-3);
  white-space: nowrap;
}

.stat.on > span {
  color: var(--line);
}

.stat b.glyphs {
  font-family: var(--font-icons);
  font-weight: normal;
  font-size: 18px;
  height: 24px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.stat b.none {
  font-family: var(--font-ui);
  color: var(--ink-3);
}

.used {
  color: #c9bda6;
}

.badges {
  margin-left: auto;
  display: flex;
  gap: 8px;
}

.badge {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 4px 8px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 6px;
}

.badge.first {
  border-color: var(--seal);
  color: var(--seal);
}

.backdrop {
  position: fixed;
  inset: 0;
  z-index: 30;
}

.popover {
  position: absolute;
  z-index: 31;
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

.popover.bottom {
  bottom: calc(100% + 8px);
}

.popover.top {
  top: calc(100% + 8px);
}

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
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

.close:hover {
  background: var(--paper-2);
}

.amount {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
}

.amount > span {
  flex: 1;
}

.amount b {
  font-family: var(--font-display);
  font-size: 22px;
  min-width: 24px;
  text-align: center;
}

.step {
  font: inherit;
  font-weight: 700;
  font-size: 18px;
  width: 40px;
  height: 40px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.effects {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.effect {
  font: inherit;
  font-weight: 700;
  font-size: 14px;
  min-height: 40px;
  padding: 0 10px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.effect:hover {
  background: var(--paper-2);
}

.effect.primary {
  background: var(--ink);
  color: var(--paper);
}

.step:disabled,
.effect:disabled {
  opacity: 0.5;
  cursor: default;
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
  justify-content: space-between;
  align-items: baseline;
  gap: 10px;
  min-height: 40px;
  padding: 6px 10px;
  border: 1.5px solid var(--ink);
  border-radius: 3px;
  background: var(--board);
  color: var(--ink);
  cursor: pointer;
}

.pile-card:hover {
  background: var(--paper-2);
}

.pile-name {
  font-family: var(--font-display);
  font-size: 15px;
  font-weight: 700;
}

.pile-type {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.error {
  font-size: 13px;
  color: var(--seal);
}
</style>
