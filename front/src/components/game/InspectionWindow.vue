<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref } from 'vue'

import type { CardView, GameView, Send, Side } from '@/api/game'
import type { Message } from '@/api/messages'
import GameCard from '@/components/game/GameCard.vue'
import PlayForm from '@/components/game/PlayForm.vue'
import { playerById, type Cards } from '@/lib/board'
import { hostChoices, provinceChoices, type PlayAction } from '@/lib/cardActions'
import {
  inspectionActions,
  inspectionPlays,
  lostInTheShuffle,
  type InspectedCard,
  type Inspection,
  type InspectionAction,
} from '@/lib/inspection'
import { hidePreview } from '@/lib/preview'

/**
 * The cards the player looks at or reveals, in a window of their own over the board: nothing else can be done while it
 * is open. The player chooses a card and what to do with it (its hand, its deck, its discard pile…), as the effect
 * says, then closes the window, which ends the look: the engine records nothing, the cards are out of sight again.
 * Each card shows what it has become; those left stay where they are. A card of a deck can be played from there
 * (Artisan Academy, Pillow Book, Infiltrator…), with the form of the card opened.
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

/** what each card has become, by id; a card revealed stays in play here */
const done = reactive<Record<string, string>>({})
const revealed = reactive(new Set<string>())
const selectedId = ref<string | null>(props.inspection.cards[0]?.id ?? null)

const STACK_NAMES: Record<string, string> = {
  conflict_draw_deck: 'conflict deck',
  dynasty_draw_deck: 'dynasty deck',
  hand: 'hand',
  regular_province: 'province',
  stronghold_province: 'stronghold province',
}

const yours = computed(() => props.inspection.ownerId === props.game.viewer_id)
const title = computed(() => {
  const { cards, stackType } = props.inspection
  const owner = yours.value ? 'your' : "Opp.'s"
  const where = `${owner} ${STACK_NAMES[stackType] ?? stackType.replaceAll('_', ' ')}`
  if (stackType === 'conflict_draw_deck' || stackType === 'dynasty_draw_deck') {
    return cards.length === 1 ? `The top card of ${where}` : `The top ${cards.length} cards of ${where}`
  }
  if (stackType === 'hand') {
    return `The cards of ${where}`
  }

  return cards.length === 1 ? `A card of ${where}` : `${cards.length} cards of ${where}`
})
const badge = computed(() =>
  props.inspection.mode === 'look' ? 'Looked at · only you see them' : 'Revealed · both players see them',
)

/** a card of the window as the board draws it: face up */
function viewOf(card: InspectedCard): CardView {
  return {
    id: card.id,
    code: card.code,
    side: (props.cards[card.code]?.side ?? 'conflict') as Side,
    orientation: 'face_up',
    fate: 0,
    honor_tokens: 0,
    personal_honor: 'ordinary',
    military_skill: null,
    political_skill: null,
    controller_id: props.inspection.ownerId,
  }
}

/** the provinces of the owner of the cards, which a card of their dynasty deck can be put into */
const ownerProvinces = computed(() => {
  const owner = playerById(props.game, props.inspection.ownerId)

  return owner === undefined ? [] : provinceChoices(owner, props.cards)
})

const selected = computed(() => props.inspection.cards.find((card) => card.id === selectedId.value) ?? null)
/** the cards of the viewer the selected card can be placed under */
const hosts = computed(() =>
  selected.value === null
    ? []
    : hostChoices(props.game, props.cards, selected.value.id).filter((host) => host.group !== 'opponent'),
)
const selectedIndex = computed(() => props.inspection.cards.findIndex((card) => card.id === selectedId.value))
const actions = computed(() =>
  selected.value === null
    ? []
    : inspectionActions(
        props.inspection,
        selected.value,
        props.game.viewer_id,
        props.cards[selected.value.code]?.side,
        ownerProvinces.value,
        hosts.value,
      )
        .filter((action) => !(action.type === 'reveal_card' && revealed.has(action.payload.card_id)))
        .filter((action) => props.game.available_actions.includes(action.type)),
)
/** the ways the selected card is played, from a deck */
const plays = computed(() =>
  selected.value === null
    ? []
    : inspectionPlays(props.inspection, props.cards[selected.value.code], new Set(props.game.available_actions)),
)
const playing = ref<PlayAction | null>(null)
const primary = computed(() => actions.value.filter((action) => action.primary))
const moves = computed(() => actions.value.filter((action) => !action.primary && !action.danger))
const removals = computed(() => actions.value.filter((action) => action.danger))
const allDone = computed(() => props.inspection.cards.every((card) => card.id in done))

function nameOf(card: InspectedCard): string {
  return props.cards[card.code]?.name ?? 'a card'
}

function select(card: InspectedCard): void {
  if (!(card.id in done)) {
    selectedId.value = card.id
    playing.value = null
  }
}

/** the card has gone somewhere: the next one left is selected */
function moved(card: InspectedCard, what: string): void {
  done[card.id] = what
  playing.value = null
  selectedId.value = props.inspection.cards.find((other) => !(other.id in done))?.id ?? null
}

async function play(message: Message): Promise<void> {
  const card = selected.value
  if (card !== null && (await props.send(message.type, message.payload as never))) {
    moved(card, 'Played')
  }
}

async function run(action: InspectionAction): Promise<void> {
  const card = selected.value
  if (card === null || !(await props.send(action.type, action.payload as never))) {
    return
  }
  if (action.stays) {
    revealed.add(card.id)

    return
  }
  if (lostInTheShuffle(props.inspection, action)) {
    for (const other of props.inspection.cards) {
      if (other.id !== card.id) {
        done[other.id] ??= 'Lost in the shuffle'
      }
    }
  }
  moved(card, action.done)
}

function close(): void {
  hidePreview()
  emit('close')
}

onBeforeUnmount(hidePreview)
</script>

<template>
  <div class="veil" role="dialog" aria-modal="true" aria-labelledby="inspection-title">
    <section class="window inverted">
      <header class="head">
        <span class="badge">
          <svg
            aria-hidden="true"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z" />
            <circle cx="12" cy="12" r="3" />
          </svg>
          {{ badge }}
        </span>
        <h2 id="inspection-title">{{ title }}</h2>
      </header>

      <div class="body">
        <div class="strip" role="group" :aria-label="title">
          <p v-if="!inspection.cards.length" class="empty">There is no card to see.</p>
          <div v-for="(card, index) in inspection.cards" :key="card.id" class="slot">
            <span class="position">#{{ index + 1 }}</span>
            <GameCard
              :card="viewOf(card)"
              :cards="cards"
              :mine="true"
              :selected="card.id === selectedId"
              :muted="card.id in done"
              @click="select(card)"
            />
            <span v-if="done[card.id]" class="moved">{{ done[card.id] }}</span>
            <span v-else-if="revealed.has(card.id)" class="moved revealed">Revealed</span>
          </div>
        </div>

        <aside class="panel" aria-label="What to do with the card">
          <template v-if="selected">
            <div class="chosen">
              <span class="label">Selected · #{{ selectedIndex + 1 }}</span>
              <span class="name">{{ nameOf(selected) }}</span>
            </div>
            <PlayForm
              v-if="playing"
              :action="playing"
              :card-id="selected.id"
              :game="game"
              :cards="cards"
              :sending="sending"
              @play="play"
              @back="playing = null"
            />
            <div v-else-if="plays.length" class="actions">
              <button
                v-for="action in plays"
                :key="action.type"
                type="button"
                class="action primary"
                :disabled="sending"
                @click="playing = action"
              >
                {{ action.label }}
              </button>
            </div>
            <div v-if="!playing && (primary.length || moves.length)" class="actions">
              <button
                v-for="action in primary"
                :key="action.label"
                type="button"
                class="action primary"
                :disabled="sending"
                @click="run(action)"
              >
                {{ action.label }}
              </button>
              <div class="grid">
                <button
                  v-for="action in moves"
                  :key="action.label"
                  type="button"
                  class="action"
                  :disabled="sending"
                  @click="run(action)"
                >
                  {{ action.label }}
                </button>
              </div>
            </div>
            <div v-if="!playing && removals.length" class="removals">
              <button
                v-for="action in removals"
                :key="action.label"
                type="button"
                class="action danger"
                :disabled="sending"
                @click="run(action)"
              >
                {{ action.label }}
              </button>
            </div>
            <p v-if="!actions.length && !plays.length" class="hint">
              Nothing to do with this card: it stays where it is.
            </p>
          </template>
          <template v-else>
            <span class="label">Selected</span>
            <p class="hint">
              {{
                allDone
                  ? 'Every card has gone somewhere. Close the window to end the look.'
                  : 'Click a card to choose what to do with it.'
              }}
            </p>
          </template>
          <p v-if="error" class="error" role="alert">{{ error }}</p>
        </aside>
      </div>

      <footer class="foot">
        <p>Click a card to choose what to do with it.<br />The cards left here stay where they are.</p>
        <button type="button" class="done" @click="close">
          {{ inspection.mode === 'look' ? 'Done looking' : 'Done' }} · Close
        </button>
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
  width: 1040px;
  max-width: calc(100vw - 48px);
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
  display: flex;
  align-items: center;
  gap: 8px;
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
  gap: 32px;
  align-items: stretch;
}

.strip {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 20px;
  overflow-x: auto;
  padding: 14px 8px 22px;
  border-radius: 4px;
  background: var(--paper);
  scrollbar-color: var(--ink-3) var(--paper-3);
}

.slot {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.position {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: var(--ink-2);
}

.moved {
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
  border-radius: 2px;
  padding: 3px 7px;
  background: var(--paper-3);
  color: var(--ink);
}

.moved.revealed {
  background: var(--seal);
  color: var(--seal-ink);
}

.empty {
  margin: 0 auto;
  font-size: 14px;
  color: var(--ink-2);
}

/* the panel of the actions stays light, as the dialog of a card */
.panel {
  --paper: #f4eee2;
  --paper-2: #ebe3d3;
  --paper-3: #e2d8c4;
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
  gap: 16px;
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

.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
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
  cursor: pointer;
}

.action:hover {
  background: var(--paper-2);
}

.action.primary {
  min-height: 48px;
  font-size: 16px;
  background: var(--ink);
  color: var(--paper);
}

.action.danger {
  width: 100%;
  border-color: var(--seal);
  color: var(--seal);
}

.action:disabled {
  opacity: 0.5;
  cursor: default;
}

.removals {
  padding-top: 14px;
  border-top: 1px solid var(--line);
}

.hint {
  margin: 0;
  font-size: 14px;
  line-height: 1.45;
  color: var(--ink-2);
}

.error {
  margin: 0;
  font-size: 13px;
  color: var(--seal);
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
  width: 50%;
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
