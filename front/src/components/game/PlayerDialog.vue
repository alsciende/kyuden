<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue'

import type { GameView, PlayerView, PayloadArgs, Send } from '@/api/game'
import type { Message, MessageType } from '@/api/messages'
import PlayerChip from '@/components/game/PlayerChip.vue'

/**
 * The effects applied to a player (honor, fate, cards, decks), for the costs and effects the engine does not apply itself.
 * They apply to every player.
 */
const props = defineProps<{
  game: GameView
  player: PlayerView
  sending: boolean
  error: string | null
  send: Send
}>()

const emit = defineEmits<{ close: [] }>()

const amount = ref(1)
const closeButton = useTemplateRef<HTMLButtonElement>('closeButton')
const available = computed(() => new Set(props.game.available_actions))
const other = computed(() => props.game.players.find((candidate) => candidate.id !== props.player.id))

const target = computed(() => ({ target_player_id: props.player.id }))

type Effect = Message & { label: string }

const actions = computed(() => {
  const effects: (Effect | false | undefined)[] = [
    {
      type: 'gain_honor',
      label: `Gain ${amount.value} honor`,
      payload: { ...target.value, honor_gain: amount.value },
    },
    {
      type: 'lose_honor',
      label: `Lose ${amount.value} honor`,
      payload: { ...target.value, honor_loss: amount.value },
    },
    other.value && {
      type: 'transfer_honor',
      label: `Give ${amount.value} honor`,
      payload: { giver_id: props.player.id, recipient_id: other.value.id, amount: amount.value },
    },
    {
      type: 'gain_fate',
      label: `Gain ${amount.value} fate`,
      payload: { ...target.value, fate_gain: amount.value },
    },
    {
      type: 'lose_fate',
      label: `Lose ${amount.value} fate`,
      payload: { ...target.value, fate_loss: amount.value },
    },
    {
      type: 'draw_conflict_cards',
      label: `Draw ${amount.value} card${amount.value === 1 ? '' : 's'}`,
      payload: { ...target.value, quantity: amount.value },
    },
    props.player.honor_bid !== null && {
      type: 'set_honor_bid',
      label: `Set the bid to ${amount.value}`,
      payload: { ...target.value, bid: amount.value },
    },
    {
      type: 'discard_random_card_from_hand',
      label: `Discard ${amount.value} card${amount.value === 1 ? '' : 's'} at random`,
      payload: { ...target.value, quantity: amount.value },
    },
    { type: 'shuffle_conflict_deck', label: 'Shuffle the conflict deck', payload: target.value },
    { type: 'shuffle_dynasty_deck', label: 'Shuffle the dynasty deck', payload: target.value },
  ]

  return effects.filter((action): action is Effect => !!action && available.value.has(action.type))
})

async function run<T extends MessageType>(type: T, ...payload: PayloadArgs<T>): Promise<void> {
  if (await props.send(type, ...payload)) {
    emit('close')
  }
}

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    emit('close')
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKey)
  closeButton.value?.focus()
})

onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <section class="panel" role="dialog" aria-modal="true" aria-label="Effects on a player">
      <header class="head">
        <PlayerChip :player="player" :viewer-id="game.viewer_id" class="chip" />
        <span class="identity">{{ player.name }}</span>
        <button ref="closeButton" type="button" class="btn btn-ghost close" @click="emit('close')">Close</button>
      </header>
      <label class="field-row">
        <span>Amount</span>
        <input v-model.number="amount" class="number" type="number" min="0" />
      </label>
      <div class="grid">
        <button
          v-for="action in actions"
          :key="action.type"
          type="button"
          class="btn btn-ghost"
          :disabled="sending"
          @click="run(action.type, action.payload)"
        >
          {{ action.label }}
        </button>
      </div>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
    </section>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  z-index: 40;
  background: rgba(18, 15, 11, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
}

.panel {
  width: 440px;
  background: var(--board);
  border-radius: 4px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.head {
  display: flex;
  align-items: center;
  gap: 10px;
}

.chip {
  font-family: var(--font-display);
  font-size: 20px;
}

.identity {
  flex: 1;
  color: var(--ink-2);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.close {
  min-height: 40px;
  padding: 0 14px;
  font-size: 14px;
}

.field-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 14px;
}

.number {
  font: inherit;
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 20px;
  width: 64px;
  min-height: 44px;
  box-sizing: border-box;
  text-align: center;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: var(--card);
  color: var(--ink);
}

.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.grid .btn {
  min-height: 44px;
  padding: 0 10px;
  font-size: 14px;
}

.error {
  font-size: 13px;
  color: var(--seal);
}
</style>
