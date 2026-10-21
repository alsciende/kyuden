<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, useTemplateRef } from 'vue'

import type { ConflictType, GameView } from '@/api/game'
import PlayerChip from '@/components/game/PlayerChip.vue'
import { charactersInPlay, opponent, playerById, skillOf, type Cards } from '@/lib/board'
import type { CardMenu, PlayAction } from '@/lib/cardActions'
import { CONFLICT_ICONS } from '@/lib/icons'

/**
 * A card opened: its image in the middle of the darkened screen, and what the player can do with it beside it.
 */
const props = defineProps<{
  menu: CardMenu
  game: GameView
  cards: Cards
  sending: boolean
  error: string | null
  send: (type: string, payload: Record<string, unknown>) => Promise<boolean>
}>()

const emit = defineEmits<{ close: [] }>()

const closeButton = useTemplateRef<HTMLButtonElement>('closeButton')

const view = computed(() => props.menu.located.card)
const name = computed(() => props.menu.card?.name ?? (view.value.code === null ? 'Face-down card' : 'Card'))
const owner = computed(() => props.menu.located.player)
const conflict = computed(() => props.game.conflict)

const attachedTo = computed(() => {
  const under = props.menu.located.under
  if (under === null || props.menu.located.stack.type !== 'in_play') {
    return null
  }

  return under.code ? (props.cards[under.code]?.name ?? null) : null
})

const attachments = computed(() =>
  props.menu.located.stack.type === 'in_play' && props.menu.located.under === null
    ? (props.menu.located.stack.cards ?? []).slice(1).map((card) => (card.code ? props.cards[card.code]?.name : null))
    : [],
)

const tags = computed(() => {
  const card = view.value
  const tags: string[] = []
  if (conflict.value?.attacker_ids.includes(card.id)) {
    tags.push('Attacking')
  } else if (conflict.value?.defender_ids.includes(card.id)) {
    tags.push('Defending')
  }
  if (props.menu.located.stack.type === 'in_play') {
    tags.push(card.orientation === 'sideways' ? 'Bowed' : 'Ready')
  }
  if (card.orientation === 'face_down' && card.code !== null) {
    tags.push('Face down')
  }
  if (card.orientation === 'upside_down') {
    tags.push('Broken')
  }
  if (card.fate > 0) {
    tags.push(`${card.fate} fate`)
  }
  if (props.menu.located.stack.type === 'in_play' && props.menu.located.under === null) {
    tags.push(card.personal_honor === 'ordinary' ? 'Ordinary' : capitalize(card.personal_honor))
  }

  return tags
})

function capitalize(text: string): string {
  return text.charAt(0).toUpperCase() + text.slice(1)
}

async function run(type: string, payload: Record<string, unknown>): Promise<void> {
  if (await props.send(type, payload)) {
    emit('close')
  }
}

// playing the card: what the player pays
const play = reactive({ cost: 0, fate: 0, intoConflict: false, target: null as string | null })
const playing = ref<PlayAction | null>(null)

function startPlay(action: PlayAction): void {
  playing.value = action
  play.cost = action.cost
  play.fate = 0
  play.intoConflict = false
  play.target = null
}

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
    conflict.value !== null &&
    [conflict.value.attacking_player_id, conflict.value.defending_player_id].includes(props.game.viewer_id),
)

function confirmPlay(): void {
  const action = playing.value!
  const card_id = view.value.id
  switch (action.kind) {
    case 'from_province':
      void run(action.type, { card_id, card_cost: play.cost, fate_cost: play.fate, fate: play.fate })
      break
    case 'from_hand':
      void run(action.type, {
        card_id,
        card_cost: play.cost,
        fate_cost: play.fate,
        fate: play.fate,
        into_conflict: play.intoConflict,
      })
      break
    case 'attachment':
      if (play.target !== null) {
        void run(action.type, { card_id, target_card_id: play.target, card_cost: play.cost })
      }
      break
    case 'event':
      void run(action.type, { card_id, card_cost: play.cost })
      break
  }
}

// recording the current skills of a character
const character = computed(() =>
  props.menu.located.player.stacks.length === 0
    ? undefined
    : charactersInPlay(props.menu.located.player).find((candidate) => candidate.card.id === view.value.id),
)
const skills = reactive({ military: 0, political: 0 })

function resetSkillFields(): void {
  if (character.value) {
    skills.military = skillOf(character.value, props.cards, 'military')
    skills.political = skillOf(character.value, props.cards, 'political')
  }
}

async function recordSkills(): Promise<void> {
  const card_id = view.value.id
  for (const type of ['military', 'political'] as const) {
    const recorded = type === 'military' ? view.value.military_skill : view.value.political_skill
    const current = character.value ? skillOf(character.value, props.cards, type) : null
    if (recorded !== null || skills[type] !== current) {
      if (!(await props.send('set_character_skill', { card_id, type, skill: skills[type] }))) {
        return
      }
    }
  }
  emit('close')
}

const recorded = computed(() => view.value.military_skill !== null || view.value.political_skill !== null)

// a duel against a participating character of the opponent
const duel = reactive({ type: 'military' as ConflictType | 'glory', challenged: null as string | null })
const challengeable = computed(() => {
  const opposing = opponent(props.game)
  const participants = [...(conflict.value?.attacker_ids ?? []), ...(conflict.value?.defender_ids ?? [])]
  const other = owner.value.id === props.game.viewer_id ? opposing : playerById(props.game, props.game.viewer_id)

  return other === undefined
    ? []
    : charactersInPlay(other)
        .filter((candidate) => participants.includes(candidate.card.id))
        .map((candidate) => ({
          id: candidate.card.id,
          name: candidate.card.code ? (props.cards[candidate.card.code]?.name ?? 'Character') : 'Character',
        }))
})

function challenge(): void {
  if (duel.challenged !== null) {
    void run('initiate_duel', { type: duel.type, challenger_ids: [view.value.id], challenged_ids: [duel.challenged] })
  }
}

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    emit('close')
  }
}

onMounted(() => {
  resetSkillFields()
  closeButton.value?.focus()
  window.addEventListener('keydown', onKey)
})

onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="overlay" role="dialog" aria-modal="true" :aria-label="name" @click.self="emit('close')">
    <button ref="closeButton" type="button" class="close" @click="emit('close')">
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
      Close <span class="key">Esc</span>
    </button>

    <figure class="figure">
      <img v-if="menu.card?.image_url" class="image" :src="menu.card.image_url" :alt="name" />
      <div v-else class="image blank">{{ name }}</div>
      <figcaption v-if="attachedTo || attachments.length" class="caption">
        <template v-if="attachedTo">Attached to {{ attachedTo }}</template>
        <template v-else>Attached: {{ attachments.filter(Boolean).join(', ') }}</template>
      </figcaption>
    </figure>

    <section class="panel" aria-label="Card actions">
      <header class="head">
        <div class="owner">
          <PlayerChip :player="owner" :viewer-id="game.viewer_id" />
          <span class="kind"
            >{{ menu.card?.type ?? '' }}<template v-if="menu.card?.clan"> · {{ menu.card.clan }}</template></span
          >
        </div>
        <h2>{{ name }}</h2>
        <div v-if="tags.length" class="tags">
          <span v-for="tag in tags" :key="tag" class="tag">{{ tag }}</span>
        </div>
      </header>

      <div v-if="menu.abilities.length" class="group">
        <button
          v-for="ability in menu.abilities"
          :key="ability.type"
          type="button"
          class="btn btn-primary"
          :disabled="sending"
          @click="run(ability.type, ability.payload)"
        >
          {{ ability.label }}
        </button>
      </div>

      <div v-if="menu.plays.length" class="group">
        <template v-if="playing === null">
          <button
            v-for="action in menu.plays"
            :key="action.type"
            type="button"
            class="btn btn-primary"
            :disabled="sending"
            @click="startPlay(action)"
          >
            {{ action.label }}
          </button>
        </template>
        <form v-else class="form" @submit.prevent="confirmPlay">
          <span class="label">{{ playing.label }}</span>
          <label class="field-row">
            <span>Cost</span>
            <input v-model.number="play.cost" class="number" type="number" min="0" />
          </label>
          <label v-if="playing.kind === 'from_province' || playing.kind === 'from_hand'" class="field-row">
            <span>Fate placed on it</span>
            <input v-model.number="play.fate" class="number" type="number" min="0" />
          </label>
          <label v-if="playing.kind === 'from_hand' && viewerInConflict" class="check">
            <input v-model="play.intoConflict" type="checkbox" /> Into the conflict
          </label>
          <fieldset v-if="playing.kind === 'attachment'" class="choices">
            <legend class="label">Attach it to</legend>
            <label v-for="target in targets" :key="target.id" class="choice">
              <input v-model="play.target" type="radio" name="target" :value="target.id" />
              {{ target.name }} <span class="whose">{{ target.mine ? 'yours' : "opponent's" }}</span>
            </label>
          </fieldset>
          <span class="hint">You pay {{ play.cost + (playing.kind.startsWith('from') ? play.fate : 0) }} fate.</span>
          <div class="row two">
            <button type="button" class="btn btn-ghost" @click="playing = null">Back</button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="sending || (playing.kind === 'attachment' && play.target === null)"
            >
              Play
            </button>
          </div>
        </form>
      </div>

      <div v-if="menu.state.length" class="group">
        <span class="label">State</span>
        <div class="row three">
          <button
            v-for="action in menu.state"
            :key="action.type"
            type="button"
            class="btn btn-ghost"
            :disabled="sending"
            @click="run(action.type, action.payload)"
          >
            {{ action.label }}
          </button>
        </div>
      </div>

      <form v-if="menu.skill" class="group" @submit.prevent="recordSkills">
        <span class="label">Current skill</span>
        <div class="skills">
          <label class="skill">
            <span class="icon" aria-hidden="true">{{ CONFLICT_ICONS.military }}</span>
            <span class="visually-hidden">Military skill</span>
            <input v-model.number="skills.military" class="number" type="number" min="0" />
          </label>
          <label class="skill">
            <span class="icon" aria-hidden="true">{{ CONFLICT_ICONS.political }}</span>
            <span class="visually-hidden">Political skill</span>
            <input v-model.number="skills.political" class="number" type="number" min="0" />
          </label>
          <button type="submit" class="btn btn-ghost set" :disabled="sending">Set</button>
        </div>
        <span class="hint">
          Every modifier applied, for both players to see.
          <button
            v-if="recorded"
            type="button"
            class="btn-link"
            :disabled="sending"
            @click="run('reset_character_skill', { card_id: view.id })"
          >
            Reset
          </button>
        </span>
      </form>

      <form v-if="menu.duel && challengeable.length" class="group" @submit.prevent="challenge">
        <span class="label">Challenge to a duel</span>
        <div class="row three">
          <label v-for="type in ['military', 'political', 'glory'] as const" :key="type" class="choice">
            <input v-model="duel.type" type="radio" name="duel-type" :value="type" /> {{ type }}
          </label>
        </div>
        <label v-for="target in challengeable" :key="target.id" class="choice">
          <input v-model="duel.challenged" type="radio" name="challenged" :value="target.id" /> {{ target.name }}
        </label>
        <button type="submit" class="btn btn-ghost" :disabled="sending || duel.challenged === null">Challenge</button>
      </form>

      <p v-if="error" class="error" role="alert">{{ error }}</p>

      <div v-if="menu.discard" class="discard">
        <button
          type="button"
          class="btn danger"
          :disabled="sending"
          @click="run(menu.discard.type, menu.discard.payload)"
        >
          Discard
        </button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  z-index: 40;
  background: rgba(18, 15, 11, 0.76);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 48px;
  padding: 24px;
}

.close {
  position: absolute;
  top: 24px;
  right: 28px;
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  color: var(--paper);
  background: transparent;
  border: 1.5px solid var(--paper);
  border-radius: 2px;
  min-height: 44px;
  padding: 0 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}

.key {
  font-weight: 500;
  opacity: 0.7;
}

.figure {
  margin: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.image {
  width: min(400px, calc((100vh - 120px) * 0.716));
  aspect-ratio: 300 / 419;
  border-radius: 14px;
  display: block;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.5);
}

.blank {
  background: var(--card);
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 700;
}

.caption {
  font-size: 13px;
  color: var(--line);
}

.panel {
  width: 360px;
  max-height: calc(100vh - 48px);
  overflow-y: auto;
  box-sizing: border-box;
  background: var(--board);
  border-radius: 4px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.head {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.owner {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  font-size: 12px;
}

.kind {
  color: var(--ink-2);
  text-transform: capitalize;
}

h2 {
  font-size: 28px;
  line-height: 1.05;
}

.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.tag {
  font-size: 12px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 2px;
  background: var(--paper-2);
}

.group,
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

.group .btn {
  min-height: 44px;
  padding: 0 12px;
  font-size: 15px;
}

.row {
  display: grid;
  gap: 8px;
}

.two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.three {
  grid-template-columns: repeat(auto-fit, minmax(96px, 1fr));
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

.skills {
  display: flex;
  align-items: center;
  gap: 14px;
}

.skill {
  display: flex;
  align-items: center;
  gap: 6px;
}

.set {
  margin-left: auto;
}

.hint {
  font-size: 12px;
  color: var(--ink-2);
}

.hint .btn-link {
  font-size: 12px;
  padding: 0 2px;
}

.error {
  font-size: 13px;
  color: var(--seal);
}

.discard {
  padding-top: 16px;
  border-top: 1px solid var(--line);
}

.danger {
  width: 100%;
  min-height: 44px;
  font-size: 15px;
  background: transparent;
  border-color: var(--seal);
  color: var(--seal);
}
</style>
