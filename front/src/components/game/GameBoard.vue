<script setup lang="ts">
import { computed } from 'vue'

import type { ConflictType, Element, GameView } from '@/api/game'
import CharacterRow from '@/components/game/CharacterRow.vue'
import ConflictField from '@/components/game/ConflictField.vue'
import GameCard from '@/components/game/GameCard.vue'
import PlayerChip from '@/components/game/PlayerChip.vue'
import ProvinceRow from '@/components/game/ProvinceRow.vue'
import {
  cardsOf,
  charactersInPlay,
  conflictsLeft,
  opponent,
  provinces,
  rings,
  stackOf,
  strongholdCandidates,
  viewer,
  type Cards,
} from '@/lib/board'
import { CONFLICT_ICONS, ELEMENT_ICONS, ELEMENT_NAMES } from '@/lib/icons'
import type { Selection } from '@/lib/selection'

/**
 * The board, laid out for the current step: what the player does not need now folds into a line, what they need
 * comes forward. Declaring a conflict shows the opponent's provinces, the rings and the characters; a conflict shows
 * its two sides.
 */
const props = defineProps<{ game: GameView; cards: Cards; selection: Selection }>()

const emit = defineEmits<{
  open: [id: string]
  ring: [element: Element]
  type: [type: ConflictType]
  province: [id: string]
  attacker: [id: string]
  defender: [id: string]
}>()

const available = computed(() => new Set(props.game.available_actions))
const me = computed(() => viewer(props.game))
const opp = computed(() => opponent(props.game))

type Mode = 'stronghold' | 'declare' | 'conflict' | 'board'

const mode = computed<Mode>(() => {
  if (available.value.has('choose_stronghold_province')) {
    return 'stronghold'
  }
  if (available.value.has('declare_conflict')) {
    return 'declare'
  }

  return props.game.conflict === null ? 'board' : 'conflict'
})

const participants = computed(() => {
  const conflict = props.game.conflict

  return conflict === null ? [] : [...conflict.attacker_ids, ...conflict.defender_ids]
})

const myHome = computed(() =>
  charactersInPlay(me.value).filter((character) => !participants.value.includes(character.card.id)),
)
const oppHome = computed(() =>
  opp.value === undefined
    ? []
    : charactersInPlay(opp.value).filter((character) => !participants.value.includes(character.card.id)),
)

const ready = (ids: { card: { id: string; orientation: string } }[]) =>
  ids.filter((character) => character.card.orientation !== 'sideways').map((character) => character.card.id)

const attackable = computed(() => {
  if (opp.value === undefined) {
    return []
  }
  const list = provinces(opp.value)
  const broken = list.filter((province) => province.broken).length

  return list
    .filter((province) => !province.broken && (province.stronghold === null || broken >= 3))
    .flatMap((province) => (province.province ? [province.province.id] : []))
})

const defending = computed(() => available.value.has('declare_defenders'))
const defendable = computed(() =>
  ready(myHome.value).filter((id) => !(props.game.conflict?.evaded_ids ?? []).includes(id)),
)

const ringChoices = computed(() => rings(props.game))
const typesLeft = computed(() => conflictsLeft(me.value))

const hand = computed(() => cardsOf(stackOf(me.value, 'hand')))
const handFromProvinces = computed(() => props.game.place === 'play_cards_from_provinces')

const myProvinceNames = computed(() =>
  provinces(me.value)
    .map((province) => (province.province?.code ? (props.cards[province.province.code]?.name ?? '') : ''))
    .filter(Boolean)
    .join(' · '),
)
const myIntact = computed(() => provinces(me.value).filter((province) => !province.broken).length)
const oppIntact = computed(() => (opp.value ? provinces(opp.value).filter((province) => !province.broken).length : 0))

const candidates = computed(() => strongholdCandidates(me.value))
const stronghold = computed(() => cardsOf(stackOf(me.value, 'stronghold_province'))[0])
</script>

<template>
  <main class="board" :class="mode">
    <!-- setup: the province under the stronghold -->
    <template v-if="mode === 'stronghold'">
      <section class="zone" aria-label="Your provinces">
        <span class="label"
          ><PlayerChip :player="me" :viewer-id="game.viewer_id" /> · choose the province under your stronghold</span
        >
        <div class="row">
          <GameCard
            v-if="stronghold"
            :card="stronghold"
            :cards="cards"
            :mine="true"
            @click="emit('open', stronghold.id)"
          />
          <span class="divider" aria-hidden="true" />
          <GameCard
            v-for="card in candidates"
            :key="card.id"
            :card="card"
            :cards="cards"
            :mine="true"
            @click="emit('open', card.id)"
          />
        </div>
      </section>
    </template>

    <!-- the declaration of a conflict -->
    <template v-else-if="mode === 'declare'">
      <section v-if="opp" class="zone" aria-label="Opponent provinces">
        <span class="label"
          ><PlayerChip :player="opp" :viewer-id="game.viewer_id" /> · provinces · choose the one to attack</span
        >
        <ProvinceRow
          :game="game"
          :player="opp"
          :cards="cards"
          :selectable="attackable"
          :selected-id="selection.provinceId"
          @open="emit('open', $event)"
          @select="emit('province', $event)"
        />
      </section>

      <section class="zone" aria-label="Opponent characters">
        <span class="label"><PlayerChip :player="opp" :viewer-id="game.viewer_id" /> · at home · may defend</span>
        <CharacterRow :characters="oppHome" :cards="cards" :mine="false" @open="emit('open', $event)" />
      </section>

      <section class="zone choice" aria-label="Ring and type">
        <div class="choice-group">
          <span class="label">Ring</span>
          <div class="rings">
            <div v-for="ring in ringChoices" :key="ring.element" class="ring-cell">
              <button
                type="button"
                class="ring"
                :class="[ring.status, { selected: selection.ring === ring.element }]"
                :disabled="ring.status !== 'unclaimed'"
                :aria-pressed="selection.ring === ring.element"
                :aria-label="`${ELEMENT_NAMES[ring.element]} ring${ring.fate ? `, ${ring.fate} fate` : ''}`"
                @click="emit('ring', ring.element)"
              >
                <span class="icon">{{ ELEMENT_ICONS[ring.element] }}</span>
                <span v-if="ring.status === 'unclaimed' && ring.fate" class="ring-fate">{{ ring.fate }}</span>
              </button>
              <span class="ring-status">
                {{
                  ring.status === 'unclaimed'
                    ? ring.fate
                      ? `${ring.fate} fate`
                      : 'No fate'
                    : ring.status === 'yours'
                      ? 'You'
                      : 'Opp.'
                }}
              </span>
            </div>
          </div>
        </div>
        <div class="choice-group">
          <span class="label">Type</span>
          <div class="segmented" role="group" aria-label="Conflict type">
            <button
              v-for="type in ['military', 'political'] as const"
              :key="type"
              type="button"
              :class="{ on: selection.type === type }"
              :aria-pressed="selection.type === type"
              :disabled="!typesLeft.includes(type)"
              @click="emit('type', type)"
            >
              <span class="icon" aria-hidden="true">{{ CONFLICT_ICONS[type] }}</span> {{ type }}
            </button>
          </div>
          <span class="aside">
            {{ typesLeft.length === 2 ? 'You have both conflicts left' : `Only a ${typesLeft[0]} conflict left` }}
          </span>
        </div>
      </section>

      <section class="zone" aria-label="Your characters">
        <span class="label"
          ><PlayerChip :player="me" :viewer-id="game.viewer_id" /> · at home · choose the attackers</span
        >
        <CharacterRow
          :characters="myHome"
          :cards="cards"
          :mine="true"
          :selectable="ready(myHome)"
          :selected-ids="selection.attackerIds"
          selected-tag="Attacks"
          @open="emit('open', $event)"
          @select="emit('attacker', $event)"
        />
      </section>

      <div class="strips">
        <div class="strip">
          <span class="label"><PlayerChip :player="me" :viewer-id="game.viewer_id" /> · provinces</span>
          <span class="strip-text">{{ myProvinceNames }}</span>
          <span class="strip-end">{{ myIntact }} intact</span>
        </div>
        <div class="strip">
          <span class="label">Hand</span>
          <span class="strip-text">{{ hand.length }} cards</span>
          <span class="strip-end">No action while declaring</span>
        </div>
      </div>
    </template>

    <!-- a conflict -->
    <template v-else-if="mode === 'conflict'">
      <div class="strip">
        <span class="label"><PlayerChip :player="opp" :viewer-id="game.viewer_id" /> · at home</span>
        <CharacterRow :characters="oppHome" :cards="cards" :mine="false" size="small" @open="emit('open', $event)" />
        <span class="strip-end">{{ oppIntact }} provinces intact</span>
      </div>

      <ConflictField :game="game" :cards="cards" @open="emit('open', $event)" />

      <section v-if="defending" class="zone" aria-label="Your characters">
        <span class="label"
          ><PlayerChip :player="me" :viewer-id="game.viewer_id" /> · at home · choose your defenders</span
        >
        <CharacterRow
          :characters="myHome"
          :cards="cards"
          :mine="true"
          :selectable="defendable"
          :selected-ids="selection.defenderIds"
          selected-tag="Defends"
          @open="emit('open', $event)"
          @select="emit('defender', $event)"
        />
      </section>
      <div v-else class="strip">
        <span class="label"><PlayerChip :player="me" :viewer-id="game.viewer_id" /> · at home</span>
        <CharacterRow :characters="myHome" :cards="cards" :mine="true" size="small" @open="emit('open', $event)" />
        <span class="strip-end">{{ myIntact }} provinces intact</span>
      </div>

      <section class="zone hand" aria-label="Your hand">
        <span class="label">Hand · {{ hand.length }} cards · click a card to play it</span>
        <div class="row">
          <GameCard
            v-for="card in hand"
            :key="card.id"
            :card="card"
            :cards="cards"
            :mine="true"
            @click="emit('open', card.id)"
          />
        </div>
      </section>
    </template>

    <!-- the whole board -->
    <template v-else>
      <section v-if="opp" class="zone opponent" aria-label="Opponent provinces">
        <div class="identity">
          <PlayerChip :player="opp" :viewer-id="game.viewer_id" class="big-chip" />
          <span class="identity-name"
            >{{ opp.name }}<template v-if="opp.clan"> · {{ opp.clan }}</template></span
          >
        </div>
        <ProvinceRow :game="game" :player="opp" :cards="cards" size="small" @open="emit('open', $event)" />
      </section>

      <section class="zone" aria-label="Opponent characters">
        <span class="label"><PlayerChip :player="opp" :viewer-id="game.viewer_id" /> · at home</span>
        <CharacterRow :characters="oppHome" :cards="cards" :mine="false" @open="emit('open', $event)" />
      </section>

      <hr class="rule" />

      <section class="zone" aria-label="Your characters">
        <span class="label"><PlayerChip :player="me" :viewer-id="game.viewer_id" /> · at home</span>
        <CharacterRow :characters="myHome" :cards="cards" :mine="true" @open="emit('open', $event)" />
      </section>

      <section class="zone" aria-label="Your provinces">
        <span class="label"><PlayerChip :player="me" :viewer-id="game.viewer_id" /> · provinces</span>
        <ProvinceRow :game="game" :player="me" :cards="cards" @open="emit('open', $event)" />
      </section>

      <section class="zone hand" aria-label="Your hand">
        <span class="label">
          Hand · {{ hand.length }} cards<template v-if="handFromProvinces">
            · not playable during the dynasty phase</template
          >
        </span>
        <div class="row" :class="{ dimmed: handFromProvinces }">
          <GameCard
            v-for="card in hand"
            :key="card.id"
            :card="card"
            :cards="cards"
            :mine="true"
            @click="emit('open', card.id)"
          />
        </div>
      </section>
    </template>
  </main>
</template>

<style scoped>
.board {
  height: 100vh;
  overflow-y: auto;
  box-sizing: border-box;
  padding: 20px 36px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--board);
}

.zone {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-end;
}

.dimmed {
  opacity: 0.45;
}

.divider {
  width: 1px;
  align-self: stretch;
  background: var(--line);
  margin: 0 10px;
}

.opponent {
  flex-direction: row;
  align-items: flex-end;
  gap: 28px;
}

.identity {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 170px;
  min-width: 0;
  align-self: center;
}

.big-chip {
  align-self: flex-start;
  font-family: var(--font-display);
  font-size: 20px;
}

.identity-name {
  font-size: 13px;
  color: var(--ink-2);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-transform: capitalize;
}

.rule {
  border: 0;
  border-top: 1px solid var(--line);
  margin: 0;
}

.hand {
  margin-top: auto;
  padding-top: 14px;
  border-top: 1px solid var(--line);
}

.choice {
  flex-direction: row;
  align-items: center;
  gap: 48px;
  padding: 20px 0;
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
}

.choice-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.rings {
  display: flex;
  gap: 20px;
}

.ring-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.ring {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  border: 1.5px solid var(--ink);
  background: var(--card);
  color: var(--ink);
  font-size: 30px;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.ring.selected {
  outline: 3px solid var(--seal);
  outline-offset: 3px;
}

.ring.yours {
  background: var(--ink);
  color: var(--paper);
  cursor: not-allowed;
}

.ring.theirs {
  background: var(--paper-3);
  color: var(--ink-2);
  border-style: dashed;
  cursor: not-allowed;
}

.ring-fate {
  position: absolute;
  bottom: -4px;
  right: -4px;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--seal);
  color: var(--seal-ink);
  font-family: var(--font-ui);
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ring-status {
  font-size: 12px;
  color: var(--ink-2);
}

.segmented {
  display: flex;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  align-self: flex-start;
}

.segmented button {
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  min-height: 44px;
  padding: 0 20px;
  border: 0;
  background: transparent;
  color: var(--ink);
  cursor: pointer;
  text-transform: capitalize;
}

.segmented button.on {
  background: var(--ink);
  color: var(--paper);
}

.segmented button:disabled {
  color: var(--ink-3);
  cursor: not-allowed;
  text-decoration: line-through;
}

.aside {
  font-size: 12px;
  color: var(--ink-2);
}

.strips {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.strip {
  display: flex;
  align-items: center;
  gap: 18px;
  min-height: 44px;
  padding: 8px 16px;
  border: 1px solid var(--line);
  border-radius: 2px;
  font-size: 13px;
  color: var(--ink-2);
}

.strip :deep(.characters) {
  min-height: 0;
}

.strip-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.strip-end {
  margin-left: auto;
  white-space: nowrap;
}
</style>
