<script setup lang="ts">
import { computed, ref } from 'vue'

import type { Element, GameView, LoggedMessage, Send, SpectatorView } from '@/api/game'
import EyeIcon from '@/components/EyeIcon.vue'
import ActionPanel from '@/components/game/ActionPanel.vue'
import CardName from '@/components/game/CardName.vue'
import LogLine from '@/components/game/LogLine.vue'
import PlayerChip from '@/components/game/PlayerChip.vue'
import RingPopover from '@/components/game/RingPopover.vue'
import { PHASES, playerById, rings, stepOf, type Cards } from '@/lib/board'
import { CONFLICT_ICONS, ELEMENT_ICONS, ELEMENT_NAMES, ringTip } from '@/lib/icons'
import { othersResolving, ringResolving } from '@/lib/resolutions'
import type { Selection } from '@/lib/selection'
import { vTip } from '@/lib/tooltip'
import { spectatorsLabel } from '@/lib/watch'

/**
 * The bar on the right of the board: the spectators of the game (a click lists them), the step of the round, what the
 * player does in it, then the rings (a click on one opens its window) and the last lines of the log (the players are
 * in their bars, above and below the board).
 *
 * A spectator does nothing in the step: they read who acts, from whose side they watch (they switch sides), and leave.
 * A replay puts its own panel in place of what the player does (slot `panel`), and its log in place of the last lines
 * (slot `log`); nobody watches it.
 */
const props = defineProps<{
  game: GameView
  cards: Cards
  log: LoggedMessage[]
  spectators: SpectatorView[]
  /** the user reading the page, marked among the spectators */
  userId: string | null
  selection: Selection
  sending: boolean
  error: string | null
  send: Send
}>()

defineEmits<{ openLog: []; switchSides: []; leave: [] }>()

const step = computed(() => stepOf(props.game.place, props.game.spectating))

/** what the opponent resolves (every player, for a spectator): the viewer's own is in the panel of actions */
const othersResolve = computed(() => othersResolving(props.game, props.cards))

/** the effect of the contested ring the opponent resolves (the attacker, for a spectator) */
const othersRing = computed(() => {
  const ring = ringResolving(props.game)

  return ring !== null && (props.game.spectating || ring.player.id !== props.game.viewer_id) ? ring : null
})

const audienceOpen = ref(false)
const audienceLabel = computed(() => spectatorsLabel(props.spectators.length))

/** who has the opportunity to act, told to a spectator */
const acting = computed(() => {
  const names = props.game.active_player_ids.map((id) => playerById(props.game, id)?.name).filter(Boolean)
  if (names.length === 0) {
    return null
  }

  return names.length === 1 ? `${names[0]}’s opportunity` : `${names.join(' and ')} act`
})
const seenFrom = computed(() => playerById(props.game, props.game.viewer_id)?.name ?? '')

/** the status under a ring claimed, for a spectator: the name of the player who claimed it */
function claimedBy(element: Element): string {
  const id = props.game.rings[element]?.claimed_by_id ?? null

  return playerById(props.game, id)?.name.split(' ')[0] ?? ''
}

const ringList = computed(() => rings(props.game))
const unclaimedFate = computed(() =>
  ringList.value.filter((ring) => ring.status === 'unclaimed').reduce((total, ring) => total + ring.fate, 0),
)

const RING_STATUS = { unclaimed: '', contested: 'Contested', yours: 'You', theirs: 'Opp.' }

/** the ring whose window is open */
const openRing = ref<Element | null>(null)

/** the last lines of the log */
const tail = computed(() => props.log.flatMap((message) => message.entries).slice(-3))
</script>

<template>
  <aside class="sidebar" aria-label="Game">
    <div class="audience">
      <span class="eyebrow">{{ game.replaying ? 'Replay' : game.spectating ? 'Spectating' : 'Your game' }}</span>
      <div v-if="!game.replaying" class="audience-anchor">
        <button
          type="button"
          class="eye"
          :class="{ on: audienceOpen }"
          :aria-expanded="audienceOpen"
          :aria-label="`${audienceLabel}: show who`"
          @click="audienceOpen = !audienceOpen"
        >
          <EyeIcon />
          {{ spectators.length }}
        </button>
        <div v-if="audienceOpen" class="audience-list" role="dialog" aria-label="Spectators">
          <div class="title">
            <span class="label">{{ audienceLabel }}</span>
            <button type="button" class="close" aria-label="Close" @click="audienceOpen = false">
              <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true">
                <path d="M2 2l10 10M12 2L2 12" stroke="currentColor" stroke-width="1.8" fill="none" />
              </svg>
            </button>
          </div>
          <ul v-if="spectators.length > 0">
            <li v-for="spectator in spectators" :key="spectator.user_id">
              {{ spectator.name }}<span v-if="spectator.user_id === userId" class="you"> · you</span>
            </li>
          </ul>
          <p v-else class="nobody">Nobody watches this game.</p>
        </div>
      </div>
    </div>

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

    <slot name="panel">
      <section v-if="game.spectating" class="spectator" aria-label="Spectating">
        <p v-if="acting" class="acting"><span class="dot" aria-hidden="true"></span>{{ acting }}</p>
        <div class="pov">
          <span class="pov-text"
            >Seen from the side of<br /><strong>{{ seenFrom }}</strong></span
          >
          <button type="button" class="swap" @click="$emit('switchSides')">
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="m21 16-4 4-4-4" />
              <path d="M17 20V4" />
              <path d="m3 8 4-4 4 4" />
              <path d="M7 4v16" />
            </svg>
            Switch sides
          </button>
        </div>
      </section>
      <ActionPanel
        v-else
        :game="game"
        :cards="cards"
        :selection="selection"
        :sending="sending"
        :error="error"
        :send="send"
      />
    </slot>

    <section
      v-if="othersResolve.length > 0 || othersRing"
      class="others-resolving"
      aria-label="What the other players resolve"
    >
      <p v-for="{ player, resolving } in othersResolve" :key="player.id" class="resolving-line">
        <PlayerChip :player="player" :viewer-id="game.viewer_id" short />
        <span
          >resolves
          <CardName :code="resolving.current.card.code" :above="false">{{ resolving.current.card.name }}</CardName
          >.</span
        >
      </p>
      <p v-if="othersRing" class="resolving-line">
        <PlayerChip :player="othersRing.player" :viewer-id="game.viewer_id" short />
        <span
          >resolves the <span v-tip="othersRing.text" class="ring-name">{{ othersRing.name }}</span> effect.</span
        >
        <span class="ring-text">{{ othersRing.text }}</span>
      </p>
    </section>

    <section class="rings" aria-label="Rings">
      <div class="title">
        <span class="label">Rings</span>
        <span class="aside">{{ unclaimedFate }} fate on the unclaimed rings</span>
      </div>
      <div class="ring-list">
        <div v-for="ring in ringList" :key="ring.element" class="ring-cell">
          <button
            v-tip="ringTip(ring.element)"
            type="button"
            class="ring"
            :class="[ring.status, { on: openRing === ring.element }]"
            :aria-expanded="openRing === ring.element"
            :aria-label="`${ELEMENT_NAMES[ring.element]} ring, ${ring.status === 'unclaimed' ? `unclaimed, ${ring.fate} fate` : ring.status === 'contested' ? 'contested' : game.spectating ? `claimed by ${claimedBy(ring.element)}` : ring.status === 'yours' ? 'claimed by you' : 'claimed by the opponent'}`"
            @click="openRing = openRing === ring.element ? null : ring.element"
          >
            <span class="icon">{{ ELEMENT_ICONS[ring.element] }}</span>
            <span v-if="ring.fate > 0" class="ring-fate">{{ ring.fate }}</span>
          </button>
          <span class="ring-status" :class="ring.status">
            {{
              ring.status === 'unclaimed'
                ? ring.fate
                  ? `${ring.fate} fate`
                  : 'No fate'
                : game.spectating && ring.status !== 'contested'
                  ? claimedBy(ring.element)
                  : RING_STATUS[ring.status]
            }}
            <span
              v-if="ring.status !== 'contested' && ring.conflictType"
              class="icon"
              role="img"
              :aria-label="`claimed in a ${ring.conflictType} conflict`"
              >{{ CONFLICT_ICONS[ring.conflictType] }}</span
            >
          </span>
        </div>
      </div>
      <RingPopover
        v-if="openRing"
        :game="game"
        :cards="cards"
        :element="openRing"
        :sending="sending"
        :error="error"
        :send="send"
        @close="openRing = null"
      />
    </section>

    <slot name="log">
      <section class="log" aria-label="Last lines of the log">
        <div class="title">
          <span class="label">Log</span>
          <button type="button" class="btn-link" @click="$emit('openLog')">Show all</button>
        </div>
        <LogLine v-for="(entry, index) in tail" :key="index" :entry="entry" :game="game" :cards="cards" />
      </section>
    </slot>

    <button v-if="game.spectating" type="button" class="btn btn-ghost leave" @click="$emit('leave')">
      Leave the game
    </button>
  </aside>
</template>

<style scoped>
.audience {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: -10px;
}

.eyebrow {
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--seal);
}

.audience-anchor {
  position: relative;
}

.eye {
  font: inherit;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 0 10px;
  border: 1.5px solid var(--line);
  border-radius: 2px;
  background: transparent;
  color: var(--ink);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

@media (hover: hover) {
  .eye:hover {
    border-color: var(--ink);
  }
}

.eye.on {
  border-color: var(--ink);
  background: var(--ink);
  color: var(--paper);
}

.audience-list {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  z-index: 20;
  width: 240px;
  box-sizing: border-box;
  background: var(--card);
  border: 1.5px solid var(--ink);
  border-radius: 4px;
  box-shadow: 0 14px 34px rgba(31, 27, 22, 0.28);
  padding: 12px 14px 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.audience-list .title {
  align-items: center;
}

.close {
  font: inherit;
  background: transparent;
  border: 0;
  cursor: pointer;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ink-2);
  border-radius: 2px;
}

@media (hover: hover) {
  .close:hover {
    background: var(--paper-2);
  }
}

.audience-list ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.audience-list li {
  font-family: var(--font-display);
  font-size: 18px;
  font-weight: 700;
}

.you {
  font-family: var(--font-ui);
  font-size: 13px;
  font-weight: 500;
  color: var(--ink-3);
}

.nobody {
  font-size: 14px;
  color: var(--ink-2);
}

.spectator {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.others-resolving {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ring-name {
  text-decoration: underline;
  text-decoration-color: var(--ink-3);
  text-underline-offset: 3px;
}

.resolving-line {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 8px;
  margin: 0;
  font-size: 14px;
}

/* the effect of the ring the opponent resolves, written out: dimmed, on a line of its own */
.ring-text {
  flex-basis: 100%;
  font-size: 12px;
  color: var(--ink-2);
}

.acting {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 700;
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--seal);
}

.pov {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 14px;
  border: 1.5px solid var(--line);
  border-radius: 2px;
  background: var(--paper);
}

.pov-text {
  font-size: 13px;
  line-height: 1.35;
  color: var(--ink-2);
}

.pov-text strong {
  font-family: var(--font-display);
  font-size: 17px;
  color: var(--ink);
}

.swap {
  font: inherit;
  font-weight: 700;
  font-size: 14px;
  min-height: 40px;
  padding: 0 12px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: transparent;
  color: var(--ink);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex: none;
}

@media (hover: hover) {
  .swap:hover {
    background: var(--paper-3);
  }
}

.leave {
  flex: none;
}

.sidebar {
  height: 100%;
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

.rings,
.log {
  padding-top: 18px;
  border-top: 1px solid var(--line);
  display: flex;
  flex-direction: column;
  gap: 14px;
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

.rings {
  position: relative;
}

.ring {
  font: inherit;
  width: 44px;
  height: 44px;
  box-sizing: border-box;
  border-radius: 50%;
  border: 1.5px solid var(--ink);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
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

.ring.on {
  outline: 2px solid var(--seal);
  outline-offset: 2px;
}

@media (hover: hover) {
  .ring:hover {
    outline: 2px solid var(--seal);
    outline-offset: 2px;
  }
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
