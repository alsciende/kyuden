<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'

import type { CardView, GameView, Send, Side } from '@/api/game'
import GameCard from '@/components/game/GameCard.vue'
import type { Cards } from '@/lib/board'
import type { Inspection } from '@/lib/inspection'
import { hidePreview } from '@/lib/preview'
import {
  emptyProvinces,
  initialPlacement,
  ownerOf,
  redistributedCards,
  redistributionPayload,
  redistributionProvinces,
  type Placement,
  type RedistributedCard,
} from '@/lib/redistribution'

/**
 * The cards of the provinces of a player the viewer has looked at, to put them back face down, each into the province
 * the viewer chooses (Governor's Spy; logic in `lib/redistribution.ts`; mockup: screen 6 of
 * `LdtL4NT1SnDLTzRZpz376x`): the provinces in columns, a click chooses a card, then its province. Nothing is sent
 * before "Put them back face down"; closing the window leaves the cards as they are (the look is done).
 */
const props = defineProps<{
  game: GameView
  cards: Cards
  inspection: Inspection
  sending: boolean
  error: string | null
  send: Send
}>()

const emit = defineEmits<{ close: [] }>()

const owner = computed(() => ownerOf(props.game, props.inspection))
const yours = computed(() => props.inspection.ownerId === props.game.viewer_id)
const columns = computed(() => (owner.value === undefined ? [] : redistributionProvinces(owner.value, props.cards)))
// the cards as they were when the window opened: they do not move before the message is sent
const looked: RedistributedCard[] = redistributedCards(props.game, props.inspection)
const placement = ref<Placement>(initialPlacement(looked))
const selectedId = ref<string | null>(looked[0]?.id ?? null)

const selected = computed(() => looked.find((card) => card.id === selectedId.value) ?? null)
const empty = computed(() => emptyProvinces(columns.value, placement.value))
const moved = computed(() => looked.some((card) => placement.value[card.id] !== card.fromId))
const title = computed(() => `Redistribute the cards of ${yours.value ? 'your' : "Opp.'s"} provinces`)

function nameOf(code: string): string {
  return props.cards[code]?.name ?? 'a card'
}

function provinceName(id: string): string {
  return columns.value.find((column) => column.id === id)?.name ?? 'a province'
}

function cardsIn(provinceId: string): RedistributedCard[] {
  return looked.filter((card) => placement.value[card.id] === provinceId)
}

/** a card of the window as the board draws it: face up, the viewer knows it */
function viewOf(card: RedistributedCard): CardView {
  return {
    id: card.id,
    code: card.code,
    side: (props.cards[card.code]?.side ?? 'dynasty') as Side,
    orientation: 'face_up',
    fate: 0,
    honor_tokens: 0,
    personal_honor: 'ordinary',
    military_skill: null,
    political_skill: null,
    controller_id: props.inspection.ownerId,
  }
}

function noteOf(card: RedistributedCard): string {
  const was = card.faceUp ? 'was face up' : 'was face down'

  return placement.value[card.id] === card.fromId ? was : `${was} · from ${provinceName(card.fromId)}`
}

function moveTo(provinceId: string): void {
  if (selected.value !== null) {
    placement.value = { ...placement.value, [selected.value.id]: provinceId }
  }
}

function undo(): void {
  placement.value = initialPlacement(looked)
}

async function confirm(): Promise<void> {
  if (
    looked.length > 0 &&
    (await props.send(
      'redistribute_province_cards',
      redistributionPayload(props.inspection.ownerId, looked, placement.value),
    ))
  ) {
    close()
  }
}

function close(): void {
  hidePreview()
  emit('close')
}

onBeforeUnmount(hidePreview)
</script>

<template>
  <div class="veil" role="dialog" aria-modal="true" aria-labelledby="redistribution-title">
    <section class="window">
      <header class="head">
        <span class="badge">
          Looked at · only you see them · {{ looked.length }} card{{ looked.length === 1 ? '' : 's' }}
        </span>
        <h2 id="redistribution-title">{{ title }}</h2>
      </header>

      <div class="body">
        <div class="columns" :style="{ gridTemplateColumns: `repeat(${Math.max(columns.length, 1)}, minmax(0, 1fr))` }">
          <section
            v-for="column in columns"
            :key="column.id"
            class="column"
            :class="{ empty: !cardsIn(column.id).length }"
            :aria-label="column.name"
          >
            <div class="column-head">
              <h3>{{ column.name }}</h3>
              <span class="count">
                {{
                  cardsIn(column.id).length
                    ? `${cardsIn(column.id).length} card${cardsIn(column.id).length === 1 ? '' : 's'}`
                    : 'no card'
                }}
              </span>
            </div>
            <div v-for="card in cardsIn(column.id)" :key="card.id" class="slot">
              <GameCard
                :card="viewOf(card)"
                :cards="cards"
                :mine="true"
                :selected="card.id === selectedId"
                @click="selectedId = card.id"
              />
              <span class="note" :class="{ moved: placement[card.id] !== card.fromId }">{{ noteOf(card) }}</span>
            </div>
            <button
              v-if="selected && placement[selected.id] !== column.id"
              type="button"
              class="target"
              :disabled="sending"
              @click="moveTo(column.id)"
            >
              Move {{ nameOf(selected.code) }} here
            </button>
          </section>
        </div>

        <aside class="panel" aria-label="Redistribution">
          <template v-if="selected">
            <div class="chosen">
              <span class="label">Chosen, in {{ provinceName(placement[selected.id] ?? selected.fromId) }}</span>
              <span class="name">{{ nameOf(selected.code) }}</span>
            </div>
            <div class="actions">
              <span class="label">Move it to</span>
              <button
                v-for="column in columns.filter((candidate) => candidate.id !== placement[selected!.id])"
                :key="column.id"
                type="button"
                class="action"
                :disabled="sending"
                @click="moveTo(column.id)"
              >
                {{ column.name }}
                <span v-if="!cardsIn(column.id).length" class="warn">· no card</span>
              </button>
            </div>
          </template>
          <p v-else class="hint">There is no card in these provinces.</p>

          <span class="spacer" />

          <p v-for="province in empty" :key="province.id" class="warning" role="status">
            {{ province.name }} has no card: each province must end with at least one.
          </p>
          <p class="hint">
            Every card goes back face down. {{ yours ? 'You' : 'Opp.' }} will see that the provinces were redistributed,
            not where each card went.
          </p>
          <p v-if="error" class="error" role="alert">{{ error }}</p>
          <button type="button" class="action primary" :disabled="sending || !looked.length" @click="confirm">
            Put them back face down
          </button>
          <button v-if="moved" type="button" class="link" @click="undo">Undo the moves</button>
        </aside>
      </div>

      <footer class="foot">
        <p>Click a card, then the province it goes into.<br />Closing the window leaves the cards as they are.</p>
        <button type="button" class="done" @click="close">Close</button>
      </footer>
    </section>
  </div>
</template>

<style scoped>
.veil {
  position: fixed;
  inset: 0;
  z-index: 45;
  background: rgb(18 15 11 / 0.82);
  display: flex;
  align-items: center;
  justify-content: center;
}

.window {
  width: 1240px;
  max-width: calc(100vw - 48px);
  max-height: calc(100vh - 48px);
  border-radius: 4px;
  border-top: 6px solid var(--seal);
  background: var(--paper-2);
  color: var(--ink);
  box-shadow: 0 30px 80px rgb(0 0 0 / 0.55);
  display: flex;
  flex-direction: column;
}

.head {
  padding: 22px 28px 18px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  border-bottom: 1px solid var(--line);
}

.badge {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--seal);
}

h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 30px;
  line-height: 1.1;
}

.body {
  padding: 20px 28px;
  display: flex;
  gap: 24px;
  min-height: 0;
  overflow: auto;
}

.columns {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 14px;
}

.column {
  border-radius: 4px;
  background: var(--paper);
  padding: 14px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
}

.column.empty {
  outline: 2px dashed var(--seal);
  outline-offset: -2px;
}

.column-head {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 8px;
}

h3 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 19px;
}

.count {
  font-size: 12px;
  color: var(--ink-2);
  white-space: nowrap;
}

.column.empty .count {
  color: var(--seal);
  font-weight: 700;
}

.slot {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.note {
  font-size: 11px;
  color: var(--ink-2);
}

.note.moved {
  color: var(--seal);
  font-weight: 700;
}

.target {
  font: inherit;
  font-weight: 700;
  font-size: 14px;
  width: 100%;
  min-height: 64px;
  border-radius: 4px;
  border: 1.5px dashed var(--ink-3);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.target:hover {
  background: var(--paper-2);
}

/* the panel stays light, as the dialog of a card */
.panel {
  --paper: #f4eee2;
  --paper-2: #ebe3d3;
  --line: #d3c7b0;
  --ink: #1f1b16;
  --ink-2: #4f473d;
  --ink-3: #6b6155;
  --seal: #b3311f;

  width: 300px;
  flex: none;
  border-radius: 4px;
  background: #faf7f1;
  color: var(--ink);
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  color-scheme: light;
}

.label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.chosen {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.name {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 24px;
  line-height: 1.1;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.action {
  font: inherit;
  font-weight: 700;
  font-size: 15px;
  min-height: 44px;
  padding: 0 12px;
  border-radius: 2px;
  border: 1.5px solid var(--ink);
  background: transparent;
  color: var(--ink);
  text-align: left;
  cursor: pointer;
}

.action:hover {
  background: var(--paper-2);
}

.action.primary {
  min-height: 48px;
  font-size: 16px;
  text-align: center;
  background: var(--ink);
  color: var(--paper);
}

.action:disabled {
  opacity: 0.5;
  cursor: default;
}

.warn,
.warning,
.error {
  color: var(--seal);
}

.warn {
  font-size: 12px;
}

.spacer {
  flex: 1;
}

.warning,
.hint,
.error {
  margin: 0;
  font-size: 13px;
  line-height: 1.45;
}

.hint {
  color: var(--ink-2);
}

.link {
  font: inherit;
  font-size: 13px;
  border: 0;
  background: transparent;
  color: var(--seal);
  text-decoration: underline;
  cursor: pointer;
}

.foot {
  padding: 16px 28px;
  border-top: 1px solid var(--line);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}

.foot p {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--ink-2);
}

.done {
  font: inherit;
  font-weight: 700;
  font-size: 16px;
  flex: none;
  width: 30%;
  min-height: 48px;
  border-radius: 2px;
  border: 1.5px solid var(--ink);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}

.done:hover {
  background: var(--paper-3);
}
</style>
