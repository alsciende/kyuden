<script setup lang="ts">
import { computed } from 'vue'

import type { GameView, LoggedMessage } from '@/api/game'
import ActionPanel from '@/components/game/ActionPanel.vue'
import PlayerChip from '@/components/game/PlayerChip.vue'
import { PHASES, conflictsLeft, hasPassed, playerById, rings, stackOf, stepOf, type Cards } from '@/lib/board'
import { CONFLICT_ICONS, ELEMENT_ICONS, ELEMENT_NAMES } from '@/lib/icons'
import { entryText } from '@/lib/logText'
import type { Selection } from '@/lib/selection'

/**
 * The bar on the right of the board: the step of the round, what the player does in it, then the state of the game
 * (players, rings, Imperial Favor) and the last lines of the log.
 */
const props = defineProps<{
  game: GameView
  cards: Cards
  log: LoggedMessage[]
  selection: Selection
  sending: boolean
  error: string | null
  send: (type: string, payload?: Record<string, unknown>) => Promise<boolean>
}>()

defineEmits<{ openLog: []; openPlayer: [id: string] }>()

const step = computed(() => stepOf(props.game.place))
/** the bids are shown during the draw phase and the duels, the steps they are made for */
const bidding = computed(() => step.value.phase === 'draw' || props.game.duel !== null)

/** the viewer first */
const players = computed(() =>
  [...props.game.players]
    .sort((a, b) => Number(b.id === props.game.viewer_id) - Number(a.id === props.game.viewer_id))
    .map((player) => {
      const left = conflictsLeft(player)

      return {
        player,
        hand: stackOf(player, 'hand')?.count ?? 0,
        deck: stackOf(player, 'conflict_draw_deck')?.count ?? 0,
        military: left.includes('military'),
        political: left.includes('political'),
        notes: [
          player.id === props.game.first_player_id ? 'First player' : null,
          hasPassed(props.game, player) ? 'Passed' : null,
          bidding.value && player.honor_bid !== null ? `Bid ${player.honor_bid}` : null,
          player.honor_bid_selected ? 'Has bid' : null,
          player.glory_count !== null ? `Glory ${player.glory_count}` : null,
        ].filter((note): note is string => note !== null),
      }
    }),
)

const ringList = computed(() => rings(props.game))
const unclaimedFate = computed(() =>
  ringList.value.filter((ring) => ring.status === 'unclaimed').reduce((total, ring) => total + ring.fate, 0),
)

const RING_STATUS = { unclaimed: '', contested: 'Contested', yours: 'You', theirs: 'Opp.' }

const favorHolder = computed(() => playerById(props.game, props.game.imperial_favor_holder_id))

/** the last lines of the log */
const tail = computed(() =>
  props.log
    .flatMap((message) => message.entries)
    .slice(-3)
    .map((entry) => entryText(entry, props.game, props.cards)),
)
</script>

<template>
  <aside class="sidebar" aria-label="Game">
    <header class="step">
      <nav class="phases" aria-label="Phases">
        <span
          v-for="phase in PHASES"
          :key="phase.id"
          class="phase"
          :class="{ current: phase.id === step.phase }"
          :aria-current="phase.id === step.phase ? 'step' : undefined"
        >
          {{ phase.name }}
        </span>
      </nav>
      <h1>{{ step.title }}</h1>
      <p v-if="step.help" class="help">{{ step.help }}</p>
    </header>

    <ActionPanel :game="game" :cards="cards" :selection="selection" :sending="sending" :error="error" :send="send" />

    <section class="players" aria-label="Players">
      <div v-for="row in players" :key="row.player.id" class="player">
        <div class="who">
          <PlayerChip :player="row.player" :viewer-id="game.viewer_id" class="name" />
          <span class="identity"
            >{{ row.player.name }}<template v-if="row.player.clan"> · {{ row.player.clan }}</template></span
          >
          <button type="button" class="btn-link effects" @click="$emit('openPlayer', row.player.id)">Effects</button>
        </div>
        <div class="stats">
          <div class="stat">
            <b>{{ row.player.honor ?? '–' }}</b
            ><span>Honor</span>
          </div>
          <div class="stat">
            <b>{{ row.player.fate }}</b
            ><span>Fate</span>
          </div>
          <div class="stat">
            <b>{{ row.hand }}</b
            ><span>Hand</span>
          </div>
          <div class="stat">
            <b>{{ row.deck }}</b
            ><span>Deck</span>
          </div>
          <div class="stat">
            <b
              class="icon conflicts"
              role="img"
              :aria-label="`Conflicts left: ${[row.military ? 'military' : '', row.political ? 'political' : ''].filter(Boolean).join(' and ') || 'none'}`"
            >
              <span :class="{ used: !row.military }">{{ CONFLICT_ICONS.military }}</span>
              <span :class="{ used: !row.political }">{{ CONFLICT_ICONS.political }}</span>
            </b>
            <span>Conflicts</span>
          </div>
        </div>
        <p v-if="row.notes.length" class="notes">{{ row.notes.join(' · ') }}</p>
      </div>
    </section>

    <section class="rings" aria-label="Rings and Imperial Favor">
      <div class="title">
        <span class="label">Rings</span>
        <span class="aside">{{ unclaimedFate }} fate on the unclaimed rings</span>
      </div>
      <div class="ring-list">
        <div v-for="ring in ringList" :key="ring.element" class="ring-cell">
          <div
            class="ring"
            :class="ring.status"
            role="img"
            :aria-label="`${ELEMENT_NAMES[ring.element]} ring, ${ring.status === 'unclaimed' ? `unclaimed, ${ring.fate} fate` : ring.status === 'contested' ? 'contested' : ring.status === 'yours' ? 'claimed by you' : 'claimed by the opponent'}`"
          >
            <span class="icon">{{ ELEMENT_ICONS[ring.element] }}</span>
            <span v-if="ring.status === 'unclaimed' && ring.fate > 0" class="ring-fate">{{ ring.fate }}</span>
          </div>
          <span class="ring-status" :class="ring.status">
            {{ ring.status === 'unclaimed' ? (ring.fate ? `${ring.fate} fate` : 'No fate') : RING_STATUS[ring.status] }}
          </span>
        </div>
      </div>
      <div class="favor">
        <span class="label">Imperial Favor</span>
        <template v-if="favorHolder">
          <PlayerChip :player="favorHolder" :viewer-id="game.viewer_id" />
          <span v-if="game.imperial_favor_side" class="icon" aria-hidden="true">{{
            CONFLICT_ICONS[game.imperial_favor_side]
          }}</span>
          <span>{{ game.imperial_favor_side }}</span>
        </template>
        <span v-else class="aside">Unclaimed</span>
      </div>
    </section>

    <section class="log" aria-label="Last lines of the log">
      <div class="title">
        <span class="label">Log</span>
        <button type="button" class="btn-link" @click="$emit('openLog')">Show all</button>
      </div>
      <p v-for="(line, index) in tail" :key="index">{{ line }}</p>
    </section>
  </aside>
</template>

<style scoped>
.sidebar {
  height: 100vh;
  overflow-y: auto;
  box-sizing: border-box;
  border-left: 1px solid var(--line);
  background: var(--paper-2);
  padding: 26px 26px 20px;
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.step {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.phases {
  display: flex;
  gap: 14px;
  font-size: 13px;
  font-weight: 700;
  color: var(--ink-3);
}

.phase.current {
  color: var(--ink);
  border-bottom: 2px solid var(--seal);
  padding-bottom: 3px;
}

h1 {
  margin-top: 6px;
  font-size: 30px;
  line-height: 1.05;
}

.help {
  font-size: 14px;
  line-height: 1.45;
  color: var(--ink-2);
}

.players,
.rings,
.log {
  padding-top: 18px;
  border-top: 1px solid var(--line);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.player {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.who {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
}

.name {
  font-family: var(--font-display);
  font-size: 18px;
}

.identity {
  font-size: 12px;
  color: var(--ink-2);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
  flex: 1;
  text-transform: capitalize;
}

.effects {
  font-size: 12px;
  padding: 0;
}

.stats {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 6px;
}

.stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stat b {
  font-family: var(--font-display);
  font-size: 24px;
  line-height: 1;
}

.stat > span {
  font-size: 11px;
  color: var(--ink-3);
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-weight: 700;
}

.stat b.conflicts {
  font-family: var(--font-icons);
  font-weight: normal;
  font-size: 18px;
  height: 24px;
  display: flex;
  align-items: center;
  gap: 5px;
}

.used {
  color: #c9bda6;
}

.notes {
  font-size: 12px;
  color: var(--ink-2);
}

.title {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.aside {
  font-size: 12px;
  color: var(--ink-2);
}

.ring-list {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 4px;
}

.ring-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 7px;
}

.ring {
  width: 44px;
  height: 44px;
  box-sizing: border-box;
  border-radius: 50%;
  border: 1.5px solid var(--ink);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  position: relative;
}

.ring.contested {
  border: 2px solid var(--seal);
  color: var(--seal);
}

.ring.yours {
  background: var(--ink);
  color: var(--paper);
}

.ring.theirs {
  background: var(--paper-3);
  color: var(--ink-2);
  border: 1.5px dashed var(--ink-3);
}

.ring-fate {
  position: absolute;
  bottom: -6px;
  right: -6px;
  width: 20px;
  height: 20px;
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
  font-size: 11px;
  color: var(--ink-2);
  white-space: nowrap;
}

.ring-status.contested {
  color: var(--seal);
  font-weight: 700;
}

.ring-status.yours,
.ring-status.theirs {
  color: var(--ink);
  font-weight: 700;
}

.favor {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--ink-2);
  text-transform: capitalize;
}

.log {
  margin-top: auto;
  gap: 6px;
  font-size: 12px;
  line-height: 1.4;
  color: var(--ink-2);
}

.log .btn-link {
  font-size: 12px;
  padding: 0;
}
</style>
