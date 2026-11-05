<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, useTemplateRef } from 'vue'

import type { ConflictType, GameView, PayloadArgs, Send } from '@/api/game'
import type { Message, MessageType } from '@/api/messages'
import ActionIcon from '@/components/ActionIcon.vue'
import PlayForm from '@/components/game/PlayForm.vue'
import PlayerChip from '@/components/game/PlayerChip.vue'
import { actionIcon } from '@/lib/actionIcons'
import { charactersInPlay, opponent, playerById, skillOf, type Cards } from '@/lib/board'
import type { CardMenu, PlayAction, SimpleAction } from '@/lib/cardActions'
import { CONFLICT_ICONS } from '@/lib/icons'
import { groupSpecial, neededCount, specialActions } from '@/lib/specialActions'
import { vTip } from '@/lib/tooltip'

/**
 * A card opened: its image in the middle of the darkened screen, and what the player can do with it beside it; the
 * special actions (`lib/specialActions.ts`) in a panel of their own, opened with the "+": those the cards of the player
 * need, with these cards, then every other one on demand.
 */
const props = defineProps<{
  menu: CardMenu
  game: GameView
  cards: Cards
  sending: boolean
  error: string | null
  send: Send
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

/** the ways the card leaves where it is on the left, then taking control of it and pointing at it: a row of icons,
 * their label on hover, ended by the "+" of the special actions */
const iconActions = computed(() =>
  [
    ...props.menu.removals,
    ...(props.menu.control ? [props.menu.control] : []),
    ...(props.menu.point ? [props.menu.point] : []),
  ].map((action, index) => ({
    action,
    icon: actionIcon(action),
    tip: action.type === 'point_at_card' ? `${action.label}: shows it to your opponent in the log` : action.label,
    /** the first of the actions on the right */
    start: index === props.menu.removals.length,
  })),
)

// an attachment moved to another character
const hostId = ref<string | null>(null)
const hosts = computed(() => targets.value.filter((target) => target.id !== props.menu.located.under?.id))

function moveAttachment(): void {
  if (hostId.value !== null) {
    void run('move_attachment', { card_id: view.value.id, target_card_id: hostId.value })
  }
}

// the status token of a character moved to another character: not to one with a token of the same kind
const tokenTargetId = ref<string | null>(null)
const tokenTargets = computed(() =>
  props.game.players.flatMap((player) =>
    charactersInPlay(player)
      .filter(
        (character) =>
          character.card.id !== view.value.id && character.card.personal_honor !== view.value.personal_honor,
      )
      .map((character) => ({
        id: character.card.id,
        name: character.card.code ? (props.cards[character.card.code]?.name ?? 'Character') : 'Character',
        mine: player.id === props.game.viewer_id,
        status: character.card.personal_honor,
      })),
  ),
)

function moveStatusToken(): void {
  if (tokenTargetId.value !== null) {
    void run('move_status_token', { from_card_id: view.value.id, to_card_id: tokenTargetId.value })
  }
}

// a dynasty card switched with a card of another province
const switchId = ref<string | null>(null)

function switchCards(): void {
  if (switchId.value !== null) {
    void run('switch_province_cards', { card_id: view.value.id, other_card_id: switchId.value })
  }
}

// a holding put into one of the provinces of its owner
const provinceId = ref<string | null>(null)

function putIntoProvince(): void {
  if (provinceId.value !== null) {
    void run('put_holding_into_province', { card_id: view.value.id, province_id: provinceId.value })
  }
}

// a dynasty card put into another province of its owner, face up unless the player says otherwise
const targetProvinceId = ref<string | null>(null)
const targetFaceUp = ref(true)

function putCardIntoProvince(): void {
  if (targetProvinceId.value !== null) {
    void run('put_card_into_province', {
      card_id: view.value.id,
      province_id: targetProvinceId.value,
      face_up: targetFaceUp.value,
    })
  }
}

function runAction(action: SimpleAction): void {
  void run(action.type, action.payload as never)
}

async function run<T extends MessageType>(type: T, ...payload: PayloadArgs<T>): Promise<void> {
  if (await props.send(type, ...payload)) {
    emit('close')
  }
}

// playing the card (PlayForm)
const playing = ref<PlayAction | null>(null)

function confirmPlay(message: Message): void {
  void run(message.type, message.payload as never)
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

// the special actions: a click, or one of the forms
const specials = computed(() => specialActions(props.game, props.cards))

const HINTS: Partial<Record<string, string>> = {
  put_character_into_play: 'Without paying its cost nor using an opportunity to act.',
  put_holding_into_province: 'The cards of the province stay there, and it is no longer refilled.',
  put_card_into_province: 'The cards of the province stay there; a province it leaves empty is refilled.',
  fill_province: 'The top card of the dynasty deck, beside the cards already there.',
  place_status_token: 'It is neither honored nor dishonored; opposed tokens are both discarded.',
  discard_status_token: 'It is neither honored nor dishonored.',
  move_status_token: 'Neither is honored nor dishonored; opposed tokens are both discarded.',
  place_honor_tokens: 'A counter on the card, from the token pool: no player gains nor loses honor.',
  remove_honor_tokens: 'Back to the token pool: no player gains nor loses honor.',
}

const specialGroups = computed(() =>
  groupSpecial(specials.value, props.menu.more, [
    ...(props.menu.duel && challengeable.value.length ? ['initiate_duel'] : []),
    ...(props.menu.intoProvince.length ? ['put_holding_into_province'] : []),
    ...(props.menu.moveAttachment && hosts.value.length ? ['move_attachment'] : []),
    ...(props.menu.switchWith.length ? ['switch_province_cards'] : []),
    ...(props.menu.putIntoProvince.length ? ['put_card_into_province'] : []),
    ...(props.menu.moveStatusToken && tokenTargets.value.length ? ['move_status_token'] : []),
  ]),
)

/** the number of special actions the cards of the player need: the badge of the "+" */
const neededSpecial = computed(() => neededCount(specialGroups.value.needed))
const hasSpecial = computed(() => specialGroups.value.needed.length + specialGroups.value.others.length > 0)
const specialOpen = ref(false)
const everyAction = ref(false)

/** the sections of the panel: the actions the cards need, then the others on demand (at once when none is needed) */
const specialSections = computed(() => {
  const { needed, others } = specialGroups.value
  const showOthers = (everyAction.value || needed.length === 0) && others.length > 0

  return [
    ...(needed.length ? [{ key: 'needed', label: 'For your cards', hint: null, groups: needed }] : []),
    ...(showOthers
      ? [
          {
            key: 'others',
            label: needed.length ? 'Every other action' : 'Every action',
            hint: 'No card you know needs them: for an effect the engine does not know about.',
            groups: others,
          },
        ]
      : []),
  ]
})

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

    <div class="panels">
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
              @click="playing = action"
            >
              {{ action.label }}
            </button>
          </template>
          <PlayForm
            v-else
            :action="playing"
            :card-id="view.id"
            :game="game"
            :cards="cards"
            :sending="sending"
            @play="confirmPlay"
            @back="playing = null"
          />
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
            {{ recorded ? 'Recorded by a player.' : 'Computed from the cards: nobody has recorded it.' }}
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

        <p v-if="error" class="error" role="alert">{{ error }}</p>

        <div v-if="iconActions.length > 0 || hasSpecial" class="removals">
          <button
            v-for="{ action, icon, tip, start } in iconActions"
            :key="action.label"
            v-tip="tip"
            type="button"
            class="btn"
            :class="[icon ? 'icon-action' : 'text-action', { start, point: action.type === 'point_at_card' || start }]"
            :aria-label="action.label"
            :disabled="sending"
            @click="runAction(action)"
          >
            <ActionIcon v-if="icon" :name="icon" />
            <template v-else>{{ action.label }}</template>
          </button>
          <button
            v-if="hasSpecial"
            v-tip="'More actions'"
            type="button"
            class="btn icon-action more"
            :class="{ start: !menu.control && !menu.point }"
            :aria-label="neededSpecial ? `More actions, ${neededSpecial} for your cards` : 'More actions'"
            aria-controls="special-actions"
            :aria-expanded="specialOpen"
            @click="specialOpen = !specialOpen"
          >
            <svg
              aria-hidden="true"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
            >
              <path d="M5 12h14" />
              <path v-if="!specialOpen" d="M12 5v14" />
            </svg>
            <span v-if="neededSpecial && !specialOpen" class="badge" aria-hidden="true">{{ neededSpecial }}</span>
          </button>
        </div>
      </section>

      <section v-if="specialOpen" id="special-actions" class="panel special-panel" aria-label="More actions">
        <h3>More actions</h3>
        <div v-for="section in specialSections" :key="section.key" class="group section" :class="section.key">
          <span class="label">{{ section.label }}</span>
          <span v-if="section.hint" class="hint">{{ section.hint }}</span>
          <div v-for="group in section.groups" :key="group.type" class="special">
            <button
              v-for="action in group.items"
              :key="action.label"
              type="button"
              class="btn btn-ghost"
              :disabled="sending"
              @click="runAction(action)"
            >
              {{ action.label }}
            </button>
            <form v-if="group.form && group.type === 'initiate_duel'" class="group" @submit.prevent="challenge">
              <span class="label">Challenge to a duel</span>
              <div class="row three">
                <label v-for="type in ['military', 'political', 'glory'] as const" :key="type" class="choice">
                  <input v-model="duel.type" type="radio" name="duel-type" :value="type" /> {{ type }}
                </label>
              </div>
              <label v-for="target in challengeable" :key="target.id" class="choice">
                <input v-model="duel.challenged" type="radio" name="challenged" :value="target.id" /> {{ target.name }}
              </label>
              <button type="submit" class="btn btn-ghost" :disabled="sending || duel.challenged === null">
                Challenge
              </button>
            </form>

            <form
              v-if="group.form && group.type === 'put_holding_into_province'"
              class="group"
              @submit.prevent="putIntoProvince"
            >
              <fieldset class="choices">
                <legend class="label">Put it into a province</legend>
                <label v-for="province in menu.intoProvince" :key="province.id" class="choice">
                  <input v-model="provinceId" type="radio" name="province" :value="province.id" /> {{ province.name }}
                </label>
              </fieldset>
              <button type="submit" class="btn btn-ghost" :disabled="sending || provinceId === null">
                Put it into play there
              </button>
            </form>

            <form v-if="group.form && group.type === 'move_attachment'" class="group" @submit.prevent="moveAttachment">
              <fieldset class="choices">
                <legend class="label">Move it to</legend>
                <label v-for="host in hosts" :key="host.id" class="choice">
                  <input v-model="hostId" type="radio" name="host" :value="host.id" />
                  {{ host.name }} <span class="whose">{{ host.mine ? 'yours' : "opponent's" }}</span>
                </label>
              </fieldset>
              <button type="submit" class="btn btn-ghost" :disabled="sending || hostId === null">Move it</button>
            </form>

            <form
              v-if="group.form && group.type === 'move_status_token'"
              class="group"
              @submit.prevent="moveStatusToken"
            >
              <fieldset class="choices">
                <legend class="label">Move its {{ view.personal_honor }} status token to</legend>
                <label v-for="target in tokenTargets" :key="target.id" class="choice">
                  <input v-model="tokenTargetId" type="radio" name="token-target" :value="target.id" />
                  {{ target.name }} <span class="whose">{{ target.mine ? 'yours' : "opponent's" }}</span>
                  <span v-if="target.status !== 'ordinary'" class="whose">{{ target.status }}</span>
                </label>
              </fieldset>
              <button type="submit" class="btn btn-ghost" :disabled="sending || tokenTargetId === null">Move it</button>
            </form>

            <form
              v-if="group.form && group.type === 'switch_province_cards'"
              class="group"
              @submit.prevent="switchCards"
            >
              <fieldset class="choices">
                <legend class="label">Switch it with</legend>
                <label v-for="other in menu.switchWith" :key="other.id" class="choice">
                  <input v-model="switchId" type="radio" name="switch" :value="other.id" /> {{ other.name }}
                </label>
              </fieldset>
              <button type="submit" class="btn btn-ghost" :disabled="sending || switchId === null">Switch them</button>
            </form>

            <form
              v-if="group.form && group.type === 'put_card_into_province'"
              class="group"
              @submit.prevent="putCardIntoProvince"
            >
              <fieldset class="choices">
                <legend class="label">Put it into a province</legend>
                <label v-for="province in menu.putIntoProvince" :key="province.id" class="choice">
                  <input v-model="targetProvinceId" type="radio" name="target-province" :value="province.id" />
                  {{ province.name }}
                </label>
              </fieldset>
              <label class="choice"> <input v-model="targetFaceUp" type="checkbox" /> Face up </label>
              <button type="submit" class="btn btn-ghost" :disabled="sending || targetProvinceId === null">
                Put it there
              </button>
            </form>
            <span v-if="HINTS[group.type]" class="hint">{{ HINTS[group.type] }}</span>
            <span v-if="group.sources" class="source">{{ group.sources }}</span>
          </div>
        </div>
        <button
          v-if="specialGroups.needed.length && specialGroups.others.length"
          type="button"
          class="btn-link every"
          @click="everyAction = !everyAction"
        >
          {{ everyAction ? 'Only the actions for your cards' : 'Show every action' }}
        </button>
      </section>
    </div>
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

/* pointing at the card on the left, the ways it leaves on the right */
.removals {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 16px;
  border-top: 1px solid var(--line);
}

.removals .btn {
  min-height: 44px;
  background: transparent;
  border-color: var(--seal);
  color: var(--seal);
}

.removals .btn:hover {
  background: var(--paper-2);
}

.removals .btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.icon-action {
  flex: 0 1 44px;
  min-width: 0;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.text-action {
  flex: 1;
  font-size: 15px;
}

.removals .start {
  margin-left: auto;
}

/* taking control, pointing at the card and the special actions: not the ways it leaves */
.removals .point,
.removals .more {
  border-color: var(--ink);
  color: var(--ink);
}

.removals .more {
  position: relative;
}

.removals .more[aria-expanded='true'] {
  background: var(--ink);
  color: var(--board);
}

.badge {
  position: absolute;
  top: -7px;
  right: -7px;
  min-width: 18px;
  height: 18px;
  box-sizing: border-box;
  padding: 0 4px;
  border-radius: 9px;
  background: var(--seal);
  color: var(--board);
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* the panel of the special actions, beside the panel of the card, their bottoms aligned: it opens by the "+" */
.panels {
  display: flex;
  align-items: flex-end;
  gap: 12px;
}

.special-panel {
  width: 320px;
}

.special-panel h3 {
  font-size: 22px;
  line-height: 1.1;
}

.special {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.special .btn {
  justify-content: flex-start;
}

.section + .section {
  padding-top: 12px;
  border-top: 1px solid var(--line);
}

.source {
  font-size: 12px;
  color: var(--ink-2);
  padding-left: 2px;
}

.every {
  align-self: flex-start;
  font-size: 13px;
}
</style>
