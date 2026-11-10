<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue'

import type { CardView, Element, GameView, PayloadArgs, PlayerView, Send } from '@/api/game'
import type { MessageType } from '@/api/messages'
import RingPopover from '@/components/game/RingPopover.vue'
import SpecialEffects from '@/components/game/SpecialEffects.vue'
import { nameOf as playerName, readOnly, setAsideNote, type Cards } from '@/lib/board'
import { findClan } from '@/data/clans'
import { CONFLICT_ICONS, ELEMENT_ICONS, ELEMENT_NAMES, ringTip } from '@/lib/icons'
import { playerBar, playerEffects, WITHOUT_AMOUNT, type BarStat, type Effect } from '@/lib/playerBar'
import { specialActions } from '@/lib/specialActions'
import { hidePreview, showPreview } from '@/lib/preview'
import { vTip } from '@/lib/tooltip'

/**
 * The bar of a player, above the board for the opponent and below it for the viewer: the player (their name on the tint
 * of their clan, honor, fate, conflicts left, claimed rings), their dynasty cards (intact provinces, deck, discard
 * pile) and their conflict cards (hand, deck, discard pile), then the cards they have removed from the game, once there
 * are some (mockup: screen 1 of `LdtL4NT1SnDLTzRZpz376x`). A click on a value opens a popover with the effects on it,
 * or the cards of a discard pile, which open like the cards of the board; a click on a claimed ring opens its window,
 * as in the bar on the right.
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
  tip?: string
}

const TITLES: Record<BarStat, string> = {
  honor: 'Honor',
  fate: 'Fate',
  hand: 'Hand',
  dynasty_deck: 'Dynasty deck',
  conflict_deck: 'Conflict deck',
  provinces: 'Provinces',
  dynasty_discard: 'Dynasty discard pile',
  conflict_discard: 'Conflict discard pile',
  removed: 'Removed from the game',
  first_player: 'First player token',
  imperial_favor: 'Imperial Favor',
  conflicts: 'Conflicts',
  player: 'Game',
}

const mine = computed(() => props.player.id === props.game.viewer_id)
/** the Imperial Favor nobody has, in the bar of the viewer: a card effect may claim it */
const unclaimedFavor = computed(
  () => mine.value && !readOnly(props.game) && props.game.imperial_favor_holder_id === null,
)
const tint = computed(() => findClan(props.player.clan)?.tint ?? '#e2d8c4')
const bar = computed(() => playerBar(props.game, props.player))

/** a value opening the effects on it, which a spectator (or a replay) does not apply: they only see the discard piles */
const effectsOn = (key: BarStat): BarStat | null => (readOnly(props.game) ? null : key)

const general = computed((): Stat[] => [
  { key: effectsOn('honor'), label: 'Honor', value: bar.value.honor ?? '–' },
  { key: effectsOn('fate'), label: 'Fate', value: bar.value.fate },
])
const dynasty = computed((): Stat[] => [
  {
    // its window tells the stronghold, for a spectator too
    key: 'provinces',
    label: 'Provinces',
    value: bar.value.intactProvinces,
    of: bar.value.provinces,
    tip: bar.value.strongholdTip,
  },
  { key: effectsOn('dynasty_deck'), label: 'Deck', value: bar.value.dynastyDeck },
  { key: 'dynasty_discard', label: 'Discard', value: bar.value.dynastyDiscard.length },
])
const conflict = computed((): Stat[] => [
  { key: effectsOn('hand'), label: 'Hand', value: bar.value.hand },
  { key: effectsOn('conflict_deck'), label: 'Deck', value: bar.value.conflictDeck },
  { key: 'conflict_discard', label: 'Discard', value: bar.value.conflictDiscard.length },
])

const sections = computed(() => [
  { name: 'Dynasty', stats: dynasty.value },
  { name: 'Conflict', stats: conflict.value },
  // a pile few games use: shown once it has cards
  ...(bar.value.removed.length
    ? [{ name: 'Removed', stats: [{ key: 'removed', label: 'Cards', value: bar.value.removed.length } as Stat] }]
    : []),
])

const conflictsAria = computed(() => `Conflicts left: ${bar.value.conflictsLeft.join(' and ') || 'none'}`)
const ringsAria = computed(
  () => `Claimed rings: ${bar.value.rings.map((element) => ELEMENT_NAMES[element]).join(', ') || 'none'}`,
)

const root = useTemplateRef<HTMLElement>('root')
const open = ref<BarStat | null>(null)
/** the claimed ring whose window is open */
const openRing = ref<Element | null>(null)
/** the left of the popover in the bar, under the value clicked */
const left = ref(0)
const amount = ref(1)

const POPOVER_WIDTH = 280

/** places the popover under the value clicked */
function place(event: MouseEvent): void {
  const barRect = root.value!.getBoundingClientRect()
  const anchor = (event.currentTarget as HTMLElement).getBoundingClientRect()
  left.value = Math.max(0, Math.min(anchor.left - barRect.left, barRect.width - POPOVER_WIDTH - 12))
}

function toggle(key: BarStat, event: MouseEvent): void {
  if (open.value === key) {
    close()

    return
  }
  place(event)
  amount.value = 1
  openRing.value = null
  open.value = key
}

function toggleRing(element: Element, event: MouseEvent): void {
  if (openRing.value === element) {
    openRing.value = null

    return
  }
  place(event)
  close()
  openRing.value = element
}

function ringLabel(element: Element): string {
  const type = props.game.rings[element]?.claimed_conflict_type

  return `${ELEMENT_NAMES[element]} ring${type ? `, claimed in a ${type} conflict` : ''}`
}

/** whose value the popover is about: nobody for the Imperial Favor nobody has (in the bar of the viewer) */
const heading = computed(() =>
  open.value === 'imperial_favor' && !bar.value.favor ? 'Nobody has it' : playerName(props.game, props.player),
)

function close(): void {
  hidePreview()
  open.value = null
}

const effects = computed(() =>
  open.value === null ? [] : playerEffects(props.game, props.player, open.value, amount.value),
)
/** the effects shown at once; the special ones behind a "+" (SpecialEffects) */
const basicEffects = computed(() => {
  const specials = specialActions(props.game, props.cards)

  return effects.value.filter((effect) => !specials.isSpecial(effect.type))
})

function runEffect(effect: Effect): void {
  if (effect.danger && !window.confirm(`${effect.label}?`)) {
    return
  }
  void run(effect.type, effect.payload as never)
}

const pile = computed((): CardView[] | null => {
  switch (open.value) {
    case 'dynasty_discard':
      return bar.value.dynastyDiscard
    case 'conflict_discard':
      return bar.value.conflictDiscard
    case 'removed':
      return bar.value.removed
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

/** how a card removed from the game lies: face down, a card of the opponent */
function noteOf(card: CardView): string {
  return open.value === 'removed' ? setAsideNote(props.game, card, props.player.id) : ''
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
  } else if (current === null) {
    window.removeEventListener('keydown', onKey)
  }
})

watch(
  () => open.value !== null || openRing.value !== null,
  (opened) => emit('popover', opened),
)

onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <section
    ref="root"
    class="bar"
    :class="position"
    :aria-label="game.spectating ? player.name : mine ? 'You' : 'Opponent'"
  >
    <div class="who">
      <button
        v-if="mine && !readOnly(game)"
        type="button"
        class="name"
        :class="{ on: open === 'player' }"
        :style="{ background: tint }"
        :aria-expanded="open === 'player'"
        @click="toggle('player', $event)"
      >
        {{ player.name }}
      </button>
      <span v-else class="name" :style="{ background: tint }">{{ player.name }}</span>
      <span v-if="!game.spectating" class="caption">{{ mine ? 'You' : 'Opp.' }}</span>
    </div>

    <!-- the values of the player, centered in the bar -->
    <div class="stats">
      <div class="section first" role="group" aria-label="Player">
        <template v-for="stat in general" :key="stat.label">
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
            <b>{{ stat.value }}</b
            ><span>{{ stat.label }}</span>
          </div>
        </template>
        <component
          :is="readOnly(game) ? 'div' : 'button'"
          :type="readOnly(game) ? undefined : 'button'"
          class="stat"
          :class="{ fixed: readOnly(game), on: open === 'conflicts' }"
          :aria-expanded="readOnly(game) ? undefined : open === 'conflicts'"
          @click="readOnly(game) || toggle('conflicts', $event)"
        >
          <b class="icon glyphs" role="img" :aria-label="conflictsAria">
            <span :class="{ used: !bar.conflictsLeft.includes('military') }">{{ CONFLICT_ICONS.military }}</span>
            <span :class="{ used: !bar.conflictsLeft.includes('political') }">{{ CONFLICT_ICONS.political }}</span>
          </b>
          <span>Conflicts</span>
        </component>
        <div class="stat fixed">
          <b v-if="bar.rings.length" class="icon glyphs" role="group" :aria-label="ringsAria">
            <button
              v-for="element in bar.rings"
              :key="element"
              v-tip="ringTip(element)"
              type="button"
              class="ring"
              :class="{ on: openRing === element }"
              :aria-label="ringLabel(element)"
              :aria-expanded="openRing === element"
              @click="toggleRing(element, $event)"
            >
              {{ ELEMENT_ICONS[element] }}
            </button>
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
            v-tip="stat.tip ?? ''"
            type="button"
            class="stat"
            :class="{ on: open === stat.key }"
            :aria-expanded="open === stat.key"
            @click="toggle(stat.key, $event)"
          >
            <b
              >{{ stat.value }}<small v-if="stat.of !== undefined">/{{ stat.of }}</small></b
            ><span>{{ stat.label }}</span>
          </button>
          <div v-else v-tip="stat.tip ?? ''" class="stat fixed" :tabindex="stat.tip ? 0 : undefined">
            <b
              >{{ stat.value }}<small v-if="stat.of !== undefined">/{{ stat.of }}</small></b
            ><span>{{ stat.label }}</span>
          </div>
        </template>
      </div>
    </div>

    <div class="badges">
      <span v-for="note in bar.notes" :key="note" class="badge">{{ note }}</span>
      <span v-if="bar.favor && readOnly(game)" class="badge">
        <span v-if="game.imperial_favor_side" class="icon" aria-hidden="true">{{
          CONFLICT_ICONS[game.imperial_favor_side]
        }}</span>
        Imperial Favor
      </span>
      <button
        v-else-if="bar.favor || unclaimedFavor"
        v-tip="bar.favor ? '' : 'Nobody has the Imperial Favor'"
        type="button"
        class="badge"
        :class="{ on: open === 'imperial_favor', unclaimed: !bar.favor }"
        :aria-label="bar.favor ? undefined : 'Imperial Favor, unclaimed'"
        :aria-expanded="open === 'imperial_favor'"
        @click="toggle('imperial_favor', $event)"
      >
        <span v-if="bar.favor && game.imperial_favor_side" class="icon" aria-hidden="true">{{
          CONFLICT_ICONS[game.imperial_favor_side]
        }}</span>
        Imperial Favor
      </button>
      <span v-if="bar.firstPlayer && readOnly(game)" class="badge first">First player</span>
      <button
        v-else-if="bar.firstPlayer"
        type="button"
        class="badge first"
        :class="{ on: open === 'first_player' }"
        :aria-expanded="open === 'first_player'"
        @click="toggle('first_player', $event)"
      >
        First player
      </button>
    </div>

    <RingPopover
      v-if="openRing"
      :game="game"
      :cards="cards"
      :element="openRing"
      :sending="sending"
      :error="error"
      :send="send"
      :anchor="{ left, width: POPOVER_WIDTH, position }"
      @close="openRing = null"
    />

    <template v-if="open">
      <div class="backdrop" @click="close" />
      <div
        class="popover"
        :class="position"
        :style="{ left: `${left}px`, width: `${POPOVER_WIDTH}px` }"
        role="dialog"
        :aria-label="`${TITLES[open]} · ${playerName(game, player, false)}`"
      >
        <div class="head">
          <span class="caption">{{ TITLES[open] }} · {{ heading }}</span>
          <button type="button" class="close" aria-label="Close" @click="close">
            <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true">
              <path d="M2 2l10 10M12 2L2 12" stroke="currentColor" stroke-width="1.8" fill="none" />
            </svg>
          </button>
        </div>

        <template v-if="pile">
          <template v-if="pile.length">
            <span class="hint"
              >{{ open === 'removed' ? 'Last removed first' : 'Top first' }} · click a card to open it</span
            >
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
                <span class="pile-text"
                  ><span class="pile-name">{{ nameOf(card) }}</span
                  ><span v-if="noteOf(card)" class="pile-note">{{ noteOf(card) }}</span></span
                ><span class="pile-type">{{ typeOf(card) }}</span>
              </button>
            </div>
          </template>
          <p v-else class="hint">The pile is empty.</p>
        </template>

        <p v-else-if="open === 'provinces'" class="tip-text">{{ bar.strongholdTip }}</p>

        <template v-else>
          <div v-if="!WITHOUT_AMOUNT.includes(open)" class="amount">
            <span>Amount</span>
            <button type="button" class="step" aria-label="Less" :disabled="amount <= 0" @click="amount--">−</button>
            <b aria-live="polite">{{ amount }}</b>
            <button type="button" class="step" aria-label="More" @click="amount++">+</button>
          </div>
          <div v-if="basicEffects.length" class="effects">
            <button
              v-for="(effect, index) in basicEffects"
              :key="effect.type"
              type="button"
              class="effect"
              :class="{ primary: index === 0 && !effect.danger, danger: effect.danger }"
              :disabled="sending"
              @click="runEffect(effect)"
            >
              {{ effect.label }}
            </button>
          </div>
          <SpecialEffects
            :effects="effects"
            :game="game"
            :cards="cards"
            :sending="sending"
            :open="!basicEffects.length"
            @run="runEffect"
          />
          <p v-if="!effects.length" class="hint">Nothing to do now.</p>
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
  /* the name on the left, the values centered, the badges on the right: both sides share what is left */
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: center;
  column-gap: 16px;
  background: var(--paper-2);
}

.stats {
  display: flex;
  align-items: center;
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
  /* the name takes the room left of the values: cut only when it does not fit */
  min-width: 0;
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

@media (hover: hover) {
  .stat:hover {
    background: var(--paper-3);
  }
}

.stat.on {
  background: var(--ink);
  color: var(--paper);
}

.stat.fixed {
  cursor: default;
}

@media (hover: hover) {
  .stat.fixed:hover {
    background: transparent;
  }
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

.stat .ring {
  font: inherit;
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: inherit;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.stat .ring.on {
  background: var(--ink);
  color: var(--paper);
}

@media (hover: hover) {
  .stat .ring:hover {
    background: var(--ink);
    color: var(--paper);
  }
}

.stat b.none {
  font-family: var(--font-ui);
  color: var(--ink-3);
}

.used {
  color: #c9bda6;
}

.badges {
  justify-self: end;
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

/* the Imperial Favor nobody has, only offered to be claimed */
.badge.unclaimed {
  border-style: dashed;
  border-color: var(--line);
  color: var(--ink-3);
}

/* the token opens the effects on it */
button.badge {
  font-family: inherit;
  background: transparent;
  cursor: pointer;
}

button.badge.on {
  background: var(--paper-2);
}

@media (hover: hover) {
  button.badge:hover {
    background: var(--paper-2);
  }
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

@media (hover: hover) {
  .close:hover {
    background: var(--paper-2);
  }
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

@media (hover: hover) {
  .effect:hover {
    background: var(--paper-2);
  }
}

button.name {
  font-family: var(--font-display);
  border: none;
  cursor: pointer;
  text-align: left;
}

button.name:focus-visible {
  outline: 1.5px solid var(--ink);
  outline-offset: 2px;
}

@media (hover: hover) {
  button.name:hover {
    outline: 1.5px solid var(--ink);
    outline-offset: 2px;
  }
}

button.name.on {
  outline: 2px solid var(--ink);
  outline-offset: 2px;
}

.effect.danger {
  border-color: var(--seal);
  color: var(--seal);
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

.tip-text {
  font-size: 14px;
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

@media (hover: hover) {
  .pile-card:hover {
    background: var(--paper-2);
  }
}

.pile-name {
  font-family: var(--font-display);
  font-size: 15px;
  font-weight: 700;
}

.pile-text {
  display: flex;
  flex-direction: column;
}

.pile-note {
  font-size: 12px;
  color: var(--ink-2);
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
