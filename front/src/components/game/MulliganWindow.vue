<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'

import type { CardView, GameView, Send, Side } from '@/api/game'
import GameCard from '@/components/game/GameCard.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import type { Cards } from '@/lib/board'
import type { MulliganCard, MulliganWindow } from '@/lib/mulligan'
import { hidePreview } from '@/lib/preview'

/**
 * The mulligan of the viewer, in a window over the board that nothing closes before they decide (mockup
 * `K178dcmNYPeiY9es4xob8F`): their dynasty cards, then their starting hand. A click chooses a card to set aside (the
 * second player chooses while the first one decides), then "Mulligan N cards" or "Keep these cards"; the new cards are
 * shown until "Done", which only closes the window.
 */
const props = defineProps<{
  game: GameView
  cards: Cards
  window: MulliganWindow
  sending: boolean
  error: string | null
  send: Send
}>()

const emit = defineEmits<{ done: [] }>()

const chosen = ref<string[]>([])

const dynasty = computed(() => props.window.step === 'dynasty')
const choosing = computed(() => props.window.phase !== 'result')
const count = computed(() => chosen.value.filter((id) => props.window.cards.some((card) => card.id === id)).length)
const plural = (n: number): string => (n === 1 ? 'card' : 'cards')
const deck = computed(() => (dynasty.value ? 'dynasty deck' : 'conflict deck'))
const nameOf = (code: string): string => props.cards[code]?.name ?? 'A card'

const badge = computed(() => (dynasty.value ? 'Setup · Dynasty mulligan' : 'Setup · Conflict mulligan'))
const title = computed(() => {
  if (props.window.phase === 'result') {
    return dynasty.value ? 'Your new dynasty cards' : 'Your starting hand'
  }

  return dynasty.value ? 'Your dynasty cards' : 'Your starting hand'
})
const subtitle = computed(() => {
  if (props.window.phase === 'result') {
    return 'Look at them before you go on.'
  }

  return dynasty.value
    ? 'The cards on your provinces. Select those to set aside: you get as many from your dynasty deck.'
    : `The ${props.window.cards.length} cards you drew. Select those to set aside: you draw as many from your conflict deck.`
})

const chosenNames = computed(() =>
  props.window.cards.filter((card) => chosen.value.includes(card.id)).map((card) => nameOf(card.code)),
)
const newCount = computed(() => props.window.cards.filter((card) => card.isNew).length)
const panel = computed(() => {
  if (props.window.phase === 'result') {
    const setAside = props.window.setAside.map((card) => nameOf(card.code))
    const shuffled =
      setAside.length === 0
        ? 'You kept your cards.'
        : `${setAside.join(', ')} ${setAside.length === 1 ? 'was' : 'were'} shuffled back into your ${deck.value}.`

    return {
      label: 'Mulligan done',
      title: newCount.value === 0 ? 'No new card' : `${newCount.value} new ${plural(newCount.value)}`,
      text: dynasty.value
        ? `${shuffled} These cards stay face down on your provinces: you will see them again when they are revealed.`
        : `${shuffled} This is your starting hand.`,
    }
  }

  return {
    label: 'To set aside',
    title: count.value === 0 ? 'None' : `${count.value} ${plural(count.value)}`,
    text:
      count.value === 0
        ? 'Select the cards you do not want. You keep the others.'
        : `${chosenNames.value.join(', ')}: replaced from the top of your ${deck.value}, then shuffled back into it.`,
  }
})

const footnote = computed(() => {
  if (props.window.phase === 'wait') {
    return 'Opp. chooses first. You can select your cards meanwhile.'
  }
  if (props.window.phase === 'result') {
    return dynasty.value
      ? 'Next: your starting hand.'
      : 'Then each player gains the honor of their stronghold, and the game begins.'
  }

  return 'Once only: you cannot change your mind afterwards.'
})

function toggle(card: MulliganCard): void {
  if (!choosing.value) {
    return
  }
  chosen.value = chosen.value.includes(card.id)
    ? chosen.value.filter((id) => id !== card.id)
    : [...chosen.value, card.id]
}

/** the name of the province a dynasty card lies on */
function provinceOf(card: MulliganCard): string | null {
  if (card.provinceId === null) {
    return null
  }
  const viewer = props.game.players.find((player) => player.id === props.game.viewer_id)
  const code = viewer?.stacks.find((stack) => stack.id === card.provinceId)?.cards?.[0]?.code

  return code ? `on ${nameOf(code)}` : null
}

/** a card of the window as the board draws it: face up */
function viewOf(card: MulliganCard): CardView {
  return {
    id: card.id,
    code: card.code,
    side: (props.cards[card.code]?.side ?? (dynasty.value ? 'dynasty' : 'conflict')) as Side,
    orientation: 'face_up',
    fate: 0,
    honor_tokens: 0,
    personal_honor: 'ordinary',
    military_skill: null,
    political_skill: null,
    controller_id: props.game.viewer_id,
  }
}

async function mulligan(cardIds: string[]): Promise<void> {
  const ids = cardIds.filter((id) => props.window.cards.some((card) => card.id === id))
  const sent = dynasty.value
    ? await props.send('mulligan_dynasty_cards', { card_ids: ids })
    : await props.send('mulligan_conflict_cards', { card_ids: ids })
  if (sent) {
    chosen.value = []
  }
}

onBeforeUnmount(hidePreview)
</script>

<template>
  <ModalDialog class="veil" aria-labelledby="mulligan-title">
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
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d="M21 12a9 9 0 1 1-3-6.7" />
            <path d="M21 4v5h-5" />
          </svg>
          {{ badge }}
        </span>
        <h2 id="mulligan-title">{{ title }}</h2>
        <p class="subtitle">{{ subtitle }}</p>
      </header>

      <div class="body">
        <div class="strip" role="group" :aria-label="title">
          <p v-if="!window.cards.length" class="empty">There is no card to see.</p>
          <div v-for="card in window.cards" :key="card.id" class="slot">
            <GameCard
              :card="viewOf(card)"
              :cards="cards"
              :mine="true"
              size="large"
              :selected="choosing && chosen.includes(card.id)"
              :muted="choosing && chosen.includes(card.id)"
              :class="{ lifted: choosing && chosen.includes(card.id), still: !choosing }"
              @click="toggle(card)"
            />
            <span v-if="choosing && chosen.includes(card.id)" class="tag aside">Set aside</span>
            <span v-else-if="card.isNew" class="tag new">New</span>
            <span v-else class="label">{{ provinceOf(card) ?? 'In hand' }}</span>
          </div>
        </div>

        <aside class="panel" aria-label="Mulligan">
          <span class="panel-label">{{ panel.label }}</span>
          <span class="panel-title">{{ panel.title }}</span>
          <p class="panel-text">{{ panel.text }}</p>
          <div class="grow"></div>
          <p v-if="error" class="error" role="alert">{{ error }}</p>
          <template v-if="window.phase === 'result'">
            <button type="button" class="action primary" @click="emit('done')">Done</button>
          </template>
          <template v-else-if="window.phase === 'wait'">
            <button type="button" class="action primary" disabled>Opp. is choosing…</button>
          </template>
          <template v-else>
            <button type="button" class="action primary" :disabled="sending" @click="mulligan(chosen)">
              {{ count === 0 ? 'Keep these cards' : `Mulligan ${count} ${plural(count)}` }}
            </button>
            <button v-if="count > 0" type="button" class="action" :disabled="sending" @click="mulligan([])">
              Keep these cards
            </button>
          </template>
          <p class="footnote">{{ footnote }}</p>
        </aside>
      </div>
    </section>
  </ModalDialog>
</template>

<style scoped>
.veil {
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

.subtitle {
  margin: 0;
  font-size: 15px;
  color: var(--ink-2);
}

.body {
  padding: 20px 28px 24px;
  display: flex;
  gap: 32px;
  align-items: stretch;
}

.strip {
  --large-w: 132px;

  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 26px;
  overflow-x: auto;
  padding: 34px 16px 24px;
  border-radius: 4px;
  background: var(--paper);
  scrollbar-color: var(--ink-3) var(--paper-3);
}

.slot {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.lifted {
  transform: translateY(-8px);
}

.still {
  cursor: default;
}

.tag {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  white-space: nowrap;
  border-radius: 2px;
  padding: 3px 8px;
}

.tag.aside {
  background: var(--seal);
  color: var(--paper);
}

.tag.new {
  background: var(--ink);
  color: var(--paper);
}

.label {
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
  padding: 3px 0;
  color: var(--ink-3);
}

.empty {
  margin: 0 auto;
  font-size: 14px;
  color: var(--ink-2);
}

/* the panel stays light, as the dialog of a card */
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
  gap: 14px;
  color-scheme: light;
}

.panel-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.panel-title {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 26px;
  line-height: 1.1;
}

.panel-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.45;
  color: var(--ink-2);
}

.grow {
  flex: 1;
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

@media (hover: hover) {
  .action:hover:not(:disabled) {
    background: var(--paper-2);
  }
}

.action.primary {
  min-height: 48px;
  font-size: 16px;
  background: var(--ink);
  color: var(--paper);
}

@media (hover: hover) {
  .action.primary:hover:not(:disabled) {
    background: #000;
  }
}

.action:disabled {
  background: var(--paper-3);
  border-color: var(--paper-3);
  color: var(--ink-3);
  cursor: not-allowed;
}

.footnote {
  margin: 0;
  font-size: 12px;
  line-height: 1.4;
  color: var(--ink-3);
}

.error {
  margin: 0;
  font-size: 13px;
  color: var(--seal);
}
</style>
