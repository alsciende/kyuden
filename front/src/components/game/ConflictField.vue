<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import type { Element, GameView } from '@/api/game'
import CharacterRow from '@/components/game/CharacterRow.vue'
import GameCard from '@/components/game/GameCard.vue'
import PlayerChip from '@/components/game/PlayerChip.vue'
import {
  charactersInPlay,
  conflictResolved,
  locate,
  opponent,
  provinceStrength,
  sideSkill,
  switchableRings,
  viewer,
  type Cards,
} from '@/lib/board'
import { CONFLICT_ICONS, ELEMENT_ICONS, ELEMENT_NAMES, RING_EFFECTS, ringTip } from '@/lib/icons'
import { vTip } from '@/lib/tooltip'

/**
 * The conflict being resolved: the contested ring, the participating characters of the opponent above those of the
 * viewer, the contested province first in the row of the defender, and the skill of each side (declared, else computed from the cards). Once its result is
 * determined: the winner, the broken province, then the effect of the ring. A click on the icon of the type or of the
 * contested ring opens a popup to switch it, to resolve an effect.
 */
const props = defineProps<{ game: GameView; cards: Cards }>()

const emit = defineEmits<{ open: [id: string]; switchType: []; switchRing: [element: Element] }>()

const conflict = computed(() => props.game.conflict!)
const me = computed(() => viewer(props.game))
const opp = computed(() => opponent(props.game))
const attacking = computed(() => conflict.value.attacking_player_id === me.value.id)

function side(playerId: string | undefined) {
  const player = props.game.players.find((candidate) => candidate.id === playerId)
  if (player === undefined) {
    return { player, characters: [], skill: 0, declared: false, role: '', defending: false }
  }
  const isAttacker = player.id === conflict.value.attacking_player_id
  const ids = isAttacker ? conflict.value.attacker_ids : conflict.value.defender_ids
  const declared = isAttacker ? conflict.value.attacker_skill : conflict.value.defender_skill

  return {
    player,
    characters: charactersInPlay(player).filter((character) => ids.includes(character.card.id)),
    skill: declared ?? sideSkill(props.game, props.cards, ids, conflict.value.type),
    declared: declared !== null,
    role: isAttacker ? 'attacking' : 'defending',
    defending: !isAttacker,
  }
}

const top = computed(() => side(opp.value?.id))
const bottom = computed(() => side(me.value.id))

const province = computed(() => {
  const id = conflict.value.province_id
  const card = locate(props.game, id)?.card
  const strength = conflict.value.province_strengths[id] ?? provinceStrength(props.game, props.cards, id)

  return {
    card,
    strength: card?.code ? strength : null,
    broken: conflict.value.broken_province_ids.includes(id),
  }
})

/** the Imperial Favor, when it is on the side of the type of the conflict */
const favor = computed(() => {
  const holder = props.game.imperial_favor_holder_id
  if (holder === null || props.game.imperial_favor_side !== conflict.value.type) {
    return null
  }

  return holder === me.value.id ? 'You have' : 'The opponent has'
})

const available = computed(() => new Set(props.game.available_actions))
const otherType = computed(() => (conflict.value.type === 'military' ? 'political' : 'military'))
const ringChoices = computed(() => (available.value.has('switch_contested_ring') ? switchableRings(props.game) : []))
const canSwitchType = computed(() => available.value.has('switch_conflict_type'))

/** the popup opened from the icon of the type or of the contested ring, to switch it */
const popup = ref<'type' | 'ring' | null>(null)

function toggle(which: 'type' | 'ring'): void {
  popup.value = popup.value === which ? null : which
}

function switchType(): void {
  popup.value = null
  emit('switchType')
}

function switchRing(element: Element): void {
  popup.value = null
  emit('switchRing', element)
}

// closed once the action is no longer available
watch([canSwitchType, ringChoices], () => {
  if ((popup.value === 'type' && !canSwitchType.value) || (popup.value === 'ring' && !ringChoices.value.length)) {
    popup.value = null
  }
})

function onKey(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    popup.value = null
  }
}

onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

const resolved = computed(() => conflictResolved(props.game))

const result = computed(() => {
  const winner = conflict.value.winner_id
  const unopposed = winner === conflict.value.attacking_player_id && conflict.value.defender_ids.length === 0
  const won = winner === me.value.id ? 'You won' : 'The opponent won'
  const text = winner === null ? 'No winner' : unopposed ? `${won} unopposed` : won
  const note = province.value.broken
    ? attacking.value
      ? 'You broke the province'
      : 'The opponent broke your province'
    : winner === null
      ? 'Nobody claims the ring'
      : ''

  return { text, note, mine: winner === me.value.id }
})

/** the effect of the ring, once the attacker may resolve it */
const ringEffect = computed(() => {
  if (props.game.place !== 'resolve_ring_effects') {
    return null
  }
  const attackerWon = conflict.value.winner_id === conflict.value.attacking_player_id
  let who = 'The attacker did not win the conflict: no ring effect.'
  if (attackerWon) {
    who = attacking.value
      ? 'You may resolve it: click the cards or open the player it concerns, then pass.'
      : 'The opponent may resolve it.'
  }

  return { text: RING_EFFECTS[conflict.value.ring], who, active: attackerWon }
})

const outcome = computed(() => {
  const attacker = attacking.value ? bottom.value : top.value
  const defender = attacking.value ? top.value : bottom.value
  const lead = bottom.value.skill - top.value.skill
  const text = lead === 0 ? 'Tied' : lead > 0 ? `You lead by ${lead}` : `The opponent leads by ${-lead}`
  let note = ''
  if (attacker.skill > defender.skill && province.value.strength !== null) {
    const margin = attacker.skill - defender.skill
    note =
      margin >= province.value.strength
        ? `Enough to break the province (strength ${province.value.strength})`
        : `Not enough to break the province (strength ${province.value.strength})`
  } else if (lead === 0 && attacker.skill > 0 && attacker.characters.length > 0) {
    // a tie at 1 or more goes to the attacker
    note = 'Attacker wins'
  } else if (conflict.value.winner_id !== null) {
    note = conflict.value.winner_id === me.value.id ? 'You won the conflict' : 'The opponent won the conflict'
  }

  return { text, note, leading: lead > 0 }
})
</script>

<template>
  <section class="conflict" aria-label="Conflict">
    <div class="sides">
      <div class="side">
        <span class="label"> <PlayerChip :player="top.player" :viewer-id="game.viewer_id" /> · {{ top.role }} </span>
        <CharacterRow :characters="top.characters" :cards="cards" :mine="false" @open="emit('open', $event)">
          <GameCard
            v-if="top.defending && province.card"
            :card="province.card"
            :cards="cards"
            :mine="false"
            tag="Contested"
            @click="emit('open', province.card.id)"
          />
        </CharacterRow>
      </div>

      <div class="contest">
        <h2>
          <button
            v-if="canSwitchType"
            type="button"
            class="icon type trigger"
            :aria-expanded="popup === 'type'"
            :aria-label="`${conflict.type} conflict: switch the type`"
            @click="toggle('type')"
          >
            {{ CONFLICT_ICONS[conflict.type] }}
          </button>
          <span v-else class="icon type" aria-hidden="true">{{ CONFLICT_ICONS[conflict.type] }}</span>
          <span class="capitalize">{{ conflict.type }}</span> conflict for the
          <button
            v-if="ringChoices.length"
            v-tip="ringTip(conflict.ring)"
            type="button"
            class="icon type ring trigger"
            :aria-expanded="popup === 'ring'"
            :aria-label="`${ELEMENT_NAMES[conflict.ring]} ring: switch the contested ring`"
            @click="toggle('ring')"
          >
            {{ ELEMENT_ICONS[conflict.ring] }}
          </button>
          <span v-else v-tip="ringTip(conflict.ring)" class="icon type ring" aria-hidden="true">{{
            ELEMENT_ICONS[conflict.ring]
          }}</span>
          {{ ELEMENT_NAMES[conflict.ring] }} ring
        </h2>
        <p v-if="favor" class="favor">
          {{ favor }} the Imperial Favor for
          <span class="icon" aria-hidden="true">{{ CONFLICT_ICONS[conflict.type] }}</span>
          {{ conflict.type }} conflicts
        </p>

        <template v-if="popup">
          <div class="backdrop" @click="popup = null" />
          <div class="popup" role="dialog" :aria-label="popup === 'type' ? 'Switch the type' : 'Switch the ring'">
            <button v-if="popup === 'type'" type="button" class="btn btn-ghost switch-type" @click="switchType">
              Switch to <span class="icon" aria-hidden="true">{{ CONFLICT_ICONS[otherType] }}</span> {{ otherType }}
            </button>
            <template v-else>
              <span class="label">Switch the ring with</span>
              <div class="switch-rings">
                <button
                  v-for="ring in ringChoices"
                  :key="ring.element"
                  v-tip="ringTip(ring.element)"
                  type="button"
                  class="switch-ring"
                  :class="ring.status"
                  :aria-label="`The ${ELEMENT_NAMES[ring.element]} ring${ring.fate ? `, ${ring.fate} fate` : ''}`"
                  @click="switchRing(ring.element)"
                >
                  <span class="icon">{{ ELEMENT_ICONS[ring.element] }}</span>
                  <span v-if="ring.fate" class="ring-fate">{{ ring.fate }}</span>
                </button>
              </div>
            </template>
          </div>
        </template>
      </div>

      <div v-if="ringEffect" class="effect" :class="{ inactive: !ringEffect.active }">
        <span class="label">{{ ELEMENT_NAMES[conflict.ring] }} ring effect</span>
        <p class="effect-text">{{ ringEffect.text }}</p>
        <p class="effect-who">{{ ringEffect.who }}</p>
      </div>

      <div class="side">
        <span class="label">
          <PlayerChip :player="bottom.player" :viewer-id="game.viewer_id" /> · {{ bottom.role }}
        </span>
        <CharacterRow :characters="bottom.characters" :cards="cards" :mine="true" @open="emit('open', $event)">
          <GameCard
            v-if="bottom.defending && province.card"
            :card="province.card"
            :cards="cards"
            :mine="true"
            tag="Contested"
            @click="emit('open', province.card.id)"
          />
        </CharacterRow>
      </div>
    </div>

    <div class="totals">
      <div class="total-block">
        <span class="total muted">{{ top.skill }}</span>
        <span class="label">{{ top.declared ? 'Declared' : conflict.type }} skill</span>
      </div>
      <div v-if="resolved" class="lead">
        <span class="result-text" :class="{ leading: result.mine }">{{ result.text }}</span>
        <span v-if="result.note" class="note">{{ result.note }}</span>
      </div>
      <div v-else class="lead">
        <span class="lead-text" :class="{ leading: outcome.leading }">{{ outcome.text }}</span>
        <span v-if="outcome.note" class="note">{{ outcome.note }}</span>
      </div>
      <div class="total-block">
        <span class="label">{{ bottom.declared ? 'Declared' : conflict.type }} skill</span>
        <span class="total">{{ bottom.skill }}</span>
      </div>
    </div>
  </section>
</template>

<style scoped>
.conflict {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 190px;
  gap: 28px;
  padding: 24px 28px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: var(--card);
}

.sides {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 20px;
}

.side {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.contest {
  position: relative;
  padding: 16px 0;
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
}

.favor {
  margin-top: 4px;
  font-size: 14px;
  color: var(--ink-2);
}

.ring {
  margin-left: 2px;
}

.switch-type {
  text-transform: capitalize;
}

/* the icon stays an icon: only the pointer and a hover tell it opens something */
.trigger {
  padding: 0;
  border: none;
  background: none;
  color: inherit;
  cursor: pointer;
  border-radius: 2px;
}

.trigger:hover,
.trigger[aria-expanded='true'] {
  color: var(--seal);
}

.backdrop {
  position: fixed;
  inset: 0;
  z-index: 10;
}

.popup {
  position: absolute;
  top: calc(100% - 8px);
  left: 0;
  z-index: 11;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px 14px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: var(--card);
  box-shadow: 0 6px 18px rgb(0 0 0 / 0.15);
}

.switch-rings {
  display: flex;
  align-items: center;
  gap: 8px;
}

.switch-ring {
  position: relative;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: 1.5px solid var(--ink);
  background: var(--card);
  color: var(--ink);
  font-size: 17px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

/* a ring of the claimed ring pool of the attacking player */
.switch-ring.yours,
.switch-ring.theirs {
  background: var(--ink);
  color: var(--paper);
}

.switch-ring:hover {
  outline: 2px solid var(--seal);
  outline-offset: 2px;
}

.ring-fate {
  position: absolute;
  bottom: -5px;
  right: -5px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--seal);
  color: var(--seal-ink);
  font-family: var(--font-ui);
  font-size: 10px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

h2 {
  font-size: 24px;
}

.type {
  font-size: 20px;
  margin-right: 6px;
}

.capitalize {
  text-transform: capitalize;
}

.totals {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: flex-end;
  text-align: right;
  padding-left: 20px;
  border-left: 1px solid var(--line);
}

.total-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-end;
}

.total {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 64px;
  line-height: 0.9;
}

.total.muted {
  color: var(--ink-2);
}

.lead {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-end;
}

.lead-text {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink-2);
}

.lead-text.leading,
.result-text.leading {
  color: var(--seal);
}

.result-text {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 700;
  line-height: 1.1;
}

.effect {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px 18px;
  border-left: 3px solid var(--seal);
  background: var(--paper-2);
}

.effect.inactive {
  border-left-color: var(--line);
  color: var(--ink-3);
}

.effect-text {
  font-family: var(--font-display);
  font-size: 20px;
  font-weight: 700;
}

.effect-who {
  font-size: 13px;
  color: var(--ink-2);
}

.note {
  font-size: 12px;
  color: var(--ink-2);
  max-width: 170px;
}
</style>
