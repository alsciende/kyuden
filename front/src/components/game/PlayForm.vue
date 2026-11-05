<script setup lang="ts">
import { computed, reactive } from 'vue'

import type { GameView } from '@/api/game'
import type { Message } from '@/api/messages'
import { charactersInPlay, type Cards } from '@/lib/board'
import type { PlayAction } from '@/lib/cardActions'

/**
 * Playing a card: what the player pays (the printed cost prefilled, card effects may modify it), the fate placed on a
 * character, whether it enters play into the conflict, the character an attachment goes on. Shared by the card opened
 * (`CardDialog`) and the cards looked at (`InspectionWindow`): a card of a deck is played from there.
 */
const props = defineProps<{
  action: PlayAction
  cardId: string
  game: GameView
  cards: Cards
  sending: boolean
}>()

const emit = defineEmits<{ play: [message: Message]; back: [] }>()

const play = reactive({ cost: props.action.cost, fate: 0, intoConflict: false, target: null as string | null })

const isCharacter = computed(() => props.action.kind === 'from_province' || props.action.kind === 'character')

/** the characters an attachment may go on */
const targets = computed(() =>
  props.game.players.flatMap((player) =>
    charactersInPlay(player).map((character) => ({
      id: character.card.id,
      name: character.card.code ? (props.cards[character.card.code]?.name ?? 'Character') : 'Character',
      mine: player.id === props.game.viewer_id,
    })),
  ),
)

const viewerInConflict = computed(
  () =>
    props.game.conflict !== null &&
    [props.game.conflict.attacking_player_id, props.game.conflict.defending_player_id].includes(props.game.viewer_id),
)

function confirm(): void {
  const action = props.action
  const card_id = props.cardId
  switch (action.kind) {
    case 'from_province':
      emit('play', {
        type: action.type,
        payload: { card_id, card_cost: play.cost, fate_cost: play.fate, fate: play.fate },
      })
      break
    case 'character':
      emit('play', {
        type: action.type,
        payload: {
          card_id,
          card_cost: play.cost,
          fate_cost: play.fate,
          fate: play.fate,
          into_conflict: play.intoConflict,
        },
      })
      break
    case 'attachment':
      if (play.target !== null) {
        emit('play', { type: action.type, payload: { card_id, target_card_id: play.target, card_cost: play.cost } })
      }
      break
    case 'event':
      emit('play', { type: action.type, payload: { card_id, card_cost: play.cost } })
      break
  }
}
</script>

<template>
  <form class="form" @submit.prevent="confirm">
    <span class="label">{{ action.label }}</span>
    <label class="field-row">
      <span>Cost</span>
      <input v-model.number="play.cost" class="number" type="number" min="0" />
    </label>
    <label v-if="isCharacter" class="field-row">
      <span>Fate placed on it</span>
      <input v-model.number="play.fate" class="number" type="number" min="0" />
    </label>
    <label v-if="action.kind === 'character' && viewerInConflict" class="check">
      <input v-model="play.intoConflict" type="checkbox" /> Into the conflict
    </label>
    <fieldset v-if="action.kind === 'attachment'" class="choices">
      <legend class="label">Attach it to</legend>
      <label v-for="target in targets" :key="target.id" class="choice">
        <input v-model="play.target" type="radio" name="target" :value="target.id" />
        {{ target.name }} <span class="whose">{{ target.mine ? 'yours' : "opponent's" }}</span>
      </label>
    </fieldset>
    <span class="hint">You pay {{ play.cost + (isCharacter ? play.fate : 0) }} fate.</span>
    <div class="row">
      <button type="button" class="btn btn-ghost" @click="emit('back')">Back</button>
      <button
        type="submit"
        class="btn btn-primary"
        :disabled="sending || (action.kind === 'attachment' && play.target === null)"
      >
        Play
      </button>
    </div>
  </form>
</template>

<style scoped>
.form {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.row .btn {
  min-height: 44px;
  padding: 0 12px;
  font-size: 15px;
}

.field-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
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

.check,
.choice {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  min-height: 32px;
  text-transform: capitalize;
}

.choices {
  border: 0;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.whose {
  color: var(--ink-3);
  font-size: 12px;
  text-transform: none;
}

.hint {
  font-size: 12px;
  color: var(--ink-2);
}
</style>
