<script setup lang="ts">
import { computed, reactive, watch } from 'vue'

import type { ConflictType, GameView } from '@/api/game'
import PlayerChip from '@/components/game/PlayerChip.vue'
import {
  stepOf,
  charactersInPlay,
  gameEnded,
  gloryCount,
  locate,
  opponent,
  printed,
  provinceStrength,
  sideSkill,
  skillOf,
  strongholdFate,
  victoryText,
  viewer,
  type Cards,
} from '@/lib/board'
import { CONFLICT_ICONS, ELEMENT_NAMES } from '@/lib/icons'
import type { Selection } from '@/lib/selection'

/**
 * What the player does in the current step, besides playing cards: passing, ending what they play, and the framework
 * actions (fate, bids, conflicts, totals). The totals are prefilled from the cards; the player confirms or corrects.
 */
const props = defineProps<{
  game: GameView
  cards: Cards
  selection: Selection
  sending: boolean
  error: string | null
  send: (type: string, payload?: Record<string, unknown>) => Promise<boolean>
}>()

const available = computed(() => new Set(props.game.available_actions))
const me = computed(() => viewer(props.game))
const opp = computed(() => opponent(props.game))

const ended = computed(() => gameEnded(props.game))

const status = computed(() => {
  if (ended.value) {
    return null
  }
  if (me.value.acted || me.value.resolving_interrupts_and_reactions > 0) {
    return { mine: true, text: 'Resolve what you played, then end it' }
  }
  if (props.game.active_player_ids.includes(me.value.id)) {
    return { mine: true, text: 'Your opportunity' }
  }
  if (props.game.active_player_ids.length > 0) {
    return { mine: false, text: 'Waiting for the opponent' }
  }

  return null
})

const hint = computed(() => (status.value && !status.value.mine ? null : (stepOf(props.game.place).hint ?? null)))

const passBonus = computed(() =>
  props.game.place === 'play_cards_from_provinces' && !props.game.players.some((player) => player.passed)
    ? '+1 fate'
    : null,
)

const conflict = computed(() => props.game.conflict)
const mySide = computed(() => {
  const current = conflict.value
  if (current === null) {
    return null
  }
  if (current.attacking_player_id === me.value.id) {
    return current.attacker_ids
  }

  return current.defending_player_id === me.value.id ? current.defender_ids : null
})

function cardName(id: string): string {
  const card = locate(props.game, id)?.card

  return card?.code ? (props.cards[card.code]?.name ?? 'a card') : 'a face-down province'
}

/** the values the player sends, prefilled */
const values = reactive({ fate: 0, bid: 2, skill: 0, glory: 0, statistic: 0, strengths: {} as Record<string, number> })

function prefill(): void {
  values.fate = strongholdFate(me.value, props.cards)
  // only the provinces attacked in the current conflict
  values.strengths = {}
  if (conflict.value !== null && mySide.value !== null) {
    values.skill = sideSkill(props.game, props.cards, mySide.value, conflict.value.type)
    for (const id of conflict.value.attacked_province_ids) {
      values.strengths[id] = conflict.value.province_strengths[id] ?? provinceStrength(props.game, props.cards, id)
    }
  }
  values.glory = gloryCount(props.game, me.value, props.cards)
  values.statistic = duelStatistic()
}

function duelStatistic(): number {
  const duel = props.game.duel
  if (duel === null) {
    return 0
  }
  const ids = duel.challenger_id === me.value.id ? duel.challenger_card_ids : duel.challenged_card_ids
  const statistic = charactersInPlay(me.value)
    .filter((character) => ids.includes(character.card.id))
    .reduce((total, character) => {
      if (duel.type === 'glory') {
        return total + printed(character.card.code ? props.cards[character.card.code]?.glory : null)
      }

      return total + skillOf(character, props.cards, duel.type)
    }, 0)

  return statistic + (me.value.honor_bid ?? 0)
}

// the prefilled values follow the game until the player sends them
watch(
  () => [props.game.version, Object.keys(props.cards).length],
  () => prefill(),
  { immediate: true },
)

const declaration = computed(() => {
  const selection = props.selection
  const complete =
    selection.ring !== null &&
    selection.type !== null &&
    selection.provinceId !== null &&
    selection.attackerIds.length > 0
  const skill =
    selection.type === null ? null : sideSkill(props.game, props.cards, selection.attackerIds, selection.type)

  return { complete, skill }
})

function declareConflict(): void {
  const selection = props.selection
  void props.send('declare_conflict', {
    ring: selection.ring,
    type: selection.type,
    province_id: selection.provinceId,
    attacker_ids: selection.attackerIds,
    evaded_ids: [],
  })
}

async function declareStrengths(): Promise<void> {
  for (const [province_id, strength] of Object.entries(values.strengths)) {
    if (!(await props.send('declare_province_strength', { province_id, strength }))) {
      return
    }
  }
}

function concede(): void {
  if (window.confirm('Concede the game?')) {
    void props.send('concede_game', { reason: null })
  }
}

const duel = computed(() => props.game.duel)
const DUEL_TYPES: Record<ConflictType | 'glory', string> = {
  military: 'Military',
  political: 'Political',
  glory: 'Glory',
}

const nothing = computed(
  () =>
    !ended.value &&
    ![
      'end_resolution',
      'pass_opportunity',
      'collect_fate',
      'bid_honor',
      'declare_conflict',
      'pass_conflict',
      'declare_defenders',
      'declare_no_defenders',
      'declare_conflict_skill',
      'declare_glory_count',
      'claim_imperial_favor',
      'declare_duel_statistic',
      'end_duel',
    ].some((type) => available.value.has(type)),
)
</script>

<template>
  <div class="actions">
    <div v-if="ended" class="result">
      <h2>{{ game.winner_id === me.id ? 'Victory' : 'Defeat' }}</h2>
      <p v-if="game.victory_condition">{{ victoryText(game) }}</p>
    </div>

    <div v-if="status" class="status" :class="{ mine: status.mine }"><span class="dot" />{{ status.text }}</div>

    <section v-if="duel" class="duel" aria-label="Duel">
      <span class="label">{{ DUEL_TYPES[duel.type] }} duel</span>
      <p>
        <PlayerChip
          :player="game.players.find((p) => p.id === duel!.challenger_id)"
          :viewer-id="game.viewer_id"
          short
        />
        {{ duel.challenger_card_ids.map(cardName).join(', ') }}
        <template v-if="duel.challenger_statistic !== null"> · {{ duel.challenger_statistic }}</template>
      </p>
      <p>
        <PlayerChip
          :player="game.players.find((p) => p.id === duel!.challenged_id)"
          :viewer-id="game.viewer_id"
          short
        />
        {{ duel.challenged_card_ids.map(cardName).join(', ') }}
        <template v-if="duel.challenged_statistic !== null"> · {{ duel.challenged_statistic }}</template>
      </p>
      <p v-if="duel.resolved" class="hint">
        {{
          duel.winner_id === null
            ? 'A tie.'
            : duel.winner_id === me.id
              ? 'You win the duel.'
              : 'The opponent wins the duel.'
        }}
      </p>
    </section>

    <button
      v-if="available.has('end_resolution')"
      type="button"
      class="btn btn-primary"
      :disabled="sending"
      @click="send('end_resolution')"
    >
      End resolution
    </button>

    <button
      v-if="available.has('pass_opportunity')"
      type="button"
      class="btn btn-primary"
      :disabled="sending"
      @click="send('pass_opportunity')"
    >
      Pass <span v-if="passBonus" class="aside">· {{ passBonus }}</span>
    </button>

    <form
      v-if="available.has('collect_fate')"
      class="form"
      @submit.prevent="send('collect_fate', { amount: values.fate })"
    >
      <label class="field-row">
        <span>Fate from your stronghold</span>
        <input v-model.number="values.fate" class="number" type="number" min="0" />
      </label>
      <button type="submit" class="btn btn-primary" :disabled="sending">Collect {{ values.fate }} fate</button>
    </form>

    <form v-if="available.has('bid_honor')" class="form" @submit.prevent="send('bid_honor', { bid: values.bid })">
      <div class="dial" role="group" aria-label="Honor dial">
        <button
          v-for="bid in 5"
          :key="bid"
          type="button"
          :class="{ on: values.bid === bid }"
          :aria-pressed="values.bid === bid"
          @click="values.bid = bid"
        >
          {{ bid }}
        </button>
      </div>
      <span class="hint">
        Kept secret until both players have bid.
        <template v-if="opp?.honor_bid_selected">The opponent has bid.</template>
      </span>
      <button type="submit" class="btn btn-primary" :disabled="sending">Bid {{ values.bid }}</button>
    </form>

    <div v-if="available.has('declare_conflict')" class="form">
      <dl class="recap">
        <dt>Type</dt>
        <dd class="capitalize">
          <template v-if="selection.type">
            <span class="icon" aria-hidden="true">{{ CONFLICT_ICONS[selection.type] }}</span>
            {{ selection.type }}
          </template>
          <span v-else class="missing">Choose a type</span>
        </dd>
        <dt>Ring</dt>
        <dd>
          <template v-if="selection.ring">
            {{ ELEMENT_NAMES[selection.ring] }}
            <span v-if="game.rings[selection.ring]?.fate" class="aside"
              >· you take its {{ game.rings[selection.ring]?.fate }} fate</span
            >
          </template>
          <span v-else class="missing">Choose a ring</span>
        </dd>
        <dt>Province</dt>
        <dd>
          <template v-if="selection.provinceId">{{ cardName(selection.provinceId) }}</template>
          <span v-else class="missing">Choose a province</span>
        </dd>
        <dt>Attackers</dt>
        <dd>
          <template v-if="selection.attackerIds.length">{{ selection.attackerIds.map(cardName).join(', ') }}</template>
          <span v-else class="missing">Choose your attackers</span>
        </dd>
        <template v-if="declaration.skill !== null">
          <dt>Skill</dt>
          <dd>
            <span class="total">{{ declaration.skill }}</span> {{ selection.type }}
          </dd>
        </template>
      </dl>
      <button
        type="button"
        class="btn btn-primary"
        :disabled="sending || !declaration.complete"
        @click="declareConflict"
      >
        Declare the conflict
      </button>
    </div>
    <button
      v-if="available.has('pass_conflict')"
      type="button"
      class="btn btn-ghost"
      :disabled="sending"
      @click="send('pass_conflict')"
    >
      Pass this conflict
    </button>

    <div v-if="available.has('declare_defenders')" class="form">
      <p class="hint">
        <template v-if="selection.defenderIds.length"
          >Defenders: {{ selection.defenderIds.map(cardName).join(', ') }}</template
        >
        <template v-else>Choose your defenders among your ready characters.</template>
      </p>
      <button
        type="button"
        class="btn btn-primary"
        :disabled="sending || selection.defenderIds.length === 0"
        @click="send('declare_defenders', { defender_ids: selection.defenderIds })"
      >
        Declare {{ selection.defenderIds.length || '' }} defender{{ selection.defenderIds.length === 1 ? '' : 's' }}
      </button>
    </div>
    <button
      v-if="available.has('declare_no_defenders')"
      type="button"
      class="btn btn-ghost"
      :disabled="sending"
      @click="send('declare_no_defenders')"
    >
      No defenders
    </button>

    <form
      v-if="available.has('declare_province_strength') && conflict?.defending_player_id === me.id"
      class="form secondary"
      @submit.prevent="declareStrengths"
    >
      <label v-for="(strength, id) in values.strengths" :key="id" class="field-row">
        <span>Strength of {{ cardName(id) }}</span>
        <input v-model.number="values.strengths[id]" class="number" type="number" />
      </label>
      <button type="submit" class="btn btn-ghost" :disabled="sending">Declare the strength</button>
    </form>

    <form
      v-if="available.has('declare_conflict_skill')"
      class="form"
      @submit.prevent="send('declare_conflict_skill', { skill: values.skill })"
    >
      <label class="field-row">
        <span>Your {{ conflict?.type }} skill</span>
        <input v-model.number="values.skill" class="number" type="number" min="0" />
      </label>
      <button type="submit" class="btn btn-primary" :disabled="sending">Declare {{ values.skill }}</button>
    </form>

    <form
      v-if="available.has('declare_glory_count')"
      class="form"
      @submit.prevent="send('declare_glory_count', { glory: values.glory })"
    >
      <label class="field-row">
        <span>Your glory</span>
        <input v-model.number="values.glory" class="number" type="number" min="0" />
      </label>
      <button type="submit" class="btn btn-primary" :disabled="sending">Declare {{ values.glory }}</button>
    </form>

    <div v-if="available.has('claim_imperial_favor')" class="row two">
      <button
        v-for="side in ['military', 'political'] as const"
        :key="side"
        type="button"
        class="btn btn-primary"
        :disabled="sending"
        @click="send('claim_imperial_favor', { side })"
      >
        <span class="icon" aria-hidden="true">{{ CONFLICT_ICONS[side] }}</span>
        {{ side }}
      </button>
    </div>

    <form
      v-if="available.has('declare_duel_statistic')"
      class="form"
      @submit.prevent="send('declare_duel_statistic', { statistic: values.statistic })"
    >
      <label class="field-row">
        <span>Your dueling statistic</span>
        <input v-model.number="values.statistic" class="number" type="number" min="0" />
      </label>
      <button type="submit" class="btn btn-primary" :disabled="sending">Declare {{ values.statistic }}</button>
    </form>

    <button
      v-if="available.has('end_duel')"
      type="button"
      class="btn btn-ghost"
      :disabled="sending"
      @click="send('end_duel')"
    >
      End the duel
    </button>

    <p v-if="hint" class="hint">{{ hint }}</p>
    <p v-else-if="nothing && !status" class="hint">Nothing to do for now.</p>

    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <button v-if="available.has('concede_game')" type="button" class="btn-link concede" @click="concede">
      Concede the game
    </button>
  </div>
</template>

<style scoped>
.actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.actions > .btn,
.form .btn,
.row .btn {
  width: 100%;
  text-transform: none;
}

.row .btn {
  text-transform: capitalize;
}

.status {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 700;
  color: var(--ink-2);
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--ink-3);
}

.status.mine {
  color: var(--ink);
}

.status.mine .dot {
  background: var(--seal);
}

.result h2 {
  font-size: 36px;
}

.result p {
  color: var(--ink-2);
}

.aside {
  font-weight: 500;
  opacity: 0.75;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.secondary {
  padding-top: 6px;
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

.dial {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
}

.dial button {
  font: inherit;
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 26px;
  height: 56px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: var(--card);
  color: var(--ink);
  cursor: pointer;
}

.dial button.on {
  background: var(--ink);
  color: var(--paper);
  outline: 3px solid var(--seal);
  outline-offset: 2px;
}

.recap {
  margin: 0;
  display: grid;
  grid-template-columns: 90px 1fr;
  row-gap: 8px;
  font-size: 14px;
}

.recap dt {
  color: var(--ink-3);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding-top: 2px;
}

.recap dd {
  margin: 0;
}

.recap .capitalize {
  text-transform: capitalize;
}

.missing {
  color: var(--ink-3);
  text-transform: none;
}

.total {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 24px;
  line-height: 1;
}

.row {
  display: grid;
  gap: 8px;
}

.two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.duel {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  font-size: 14px;
}

.label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--seal);
}

.hint {
  font-size: 13px;
  line-height: 1.4;
  color: var(--ink-2);
}

.error {
  font-size: 13px;
  color: var(--seal);
}

.concede {
  align-self: flex-start;
  font-size: 13px;
  color: var(--ink-3);
  padding: 4px 0;
}
</style>
