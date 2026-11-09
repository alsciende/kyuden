<script setup lang="ts">
import { computed } from 'vue'

import type { GameView, LoggedMessage } from '@/api/game'
import type { ReplayView } from '@/api/replay'
import LogLine from '@/components/game/LogLine.vue'
import { resultTitle, victoryText, type Cards } from '@/lib/board'
import { actionMarks, entriesSince, nextEntries, progress, roundMarks, stoppedBefore } from '@/lib/replay'

/**
 * The panel of a replay, in place of what the player does: where the replay stands (the message, the rounds and the
 * actions of the player on a timeline), what happened since the last step, the action of the player it stopped before,
 * then continuing (their action, then the game up to their next one), autoplay and starting again. Once every message
 * is replayed, the result; once the game can no longer be replayed (the engine has changed since), why.
 */
const props = defineProps<{
  replay: ReplayView
  game: GameView
  cards: Cards
  log: LoggedMessage[]
  /** the version the last step started from, null before the first one */
  stepFrom: number | null
  busy: boolean
  /** the version a jump goes to, while it is replayed */
  jumping: number | null
  autoplay: boolean
  diverged: boolean
  error: string | null
}>()

defineEmits<{ proceed: []; autoplay: []; restart: [] }>()

/** the lines of what happened since the last step that are shown */
const SINCE_LINES = 4

const rounds = computed(() => roundMarks(props.log, props.replay.total))
const actions = computed(() => actionMarks(props.log, props.replay))
const at = computed(() => progress(props.replay))
const next = computed(() => nextEntries(props.log, props.replay))
const waiting = computed(() => stoppedBefore(props.replay) !== null)
const since = computed(() =>
  props.stepFrom === null ? [] : entriesSince(props.log, props.stepFrom, props.replay.version),
)
const percent = (value: number) => `${(value * 100).toFixed(2)}%`
</script>

<template>
  <section class="replay" aria-label="Replay">
    <div class="head">
      <span class="eyebrow">Replay</span>
      <span class="where">Message {{ replay.version }} of {{ replay.total }}</span>
    </div>

    <div class="timeline" aria-hidden="true">
      <div class="track">
        <div class="done" :style="{ width: percent(at) }"></div>
        <span v-for="mark in actions" :key="mark.label" class="tick" :style="{ left: percent(mark.at) }"></span>
        <span class="now" :style="{ left: percent(at) }"></span>
      </div>
      <div class="rounds">
        <span v-for="mark in rounds" :key="mark.label" :style="{ left: percent(mark.at) }">{{ mark.label }}</span>
      </div>
    </div>

    <template v-if="diverged">
      <p class="alert" role="alert">
        This game cannot be replayed beyond message {{ replay.version }}: Kyūden has changed since it was played. The
        board shows the game up to there.
      </p>
      <RouterLink :to="{ name: 'history' }" class="btn btn-ghost">Back to your games</RouterLink>
    </template>

    <template v-else-if="replay.ended">
      <div class="result">
        <h2>{{ resultTitle(game) }}</h2>
        <p v-if="game.victory_condition">{{ victoryText(game) }}</p>
      </div>
      <button type="button" class="btn btn-primary" :disabled="busy" @click="$emit('restart')">
        Replay from the start
      </button>
      <RouterLink :to="{ name: 'history' }" class="btn btn-ghost">Back to your games</RouterLink>
    </template>

    <p v-else-if="jumping !== null" class="jumping" role="status">Replaying the game up to message {{ jumping }}…</p>

    <template v-else>
      <div v-if="since.length > 0" class="since" aria-label="What happened since">
        <LogLine
          v-for="(entry, index) in since.slice(-SINCE_LINES)"
          :key="index"
          :entry="entry"
          :game="game"
          :cards="cards"
        />
        <p v-if="since.length > SINCE_LINES" class="more">and {{ since.length - SINCE_LINES }} lines before</p>
      </div>

      <div class="next">
        <span class="next-label">{{ waiting ? 'Next, you' : 'You no longer act' }}</span>
        <template v-if="waiting">
          <LogLine v-for="(entry, index) in next" :key="index" :entry="entry" :game="game" :cards="cards" />
        </template>
        <p v-else>Continue plays the game to its end.</p>
      </div>

      <div class="buttons">
        <button type="button" class="btn btn-primary continue" :disabled="busy || autoplay" @click="$emit('proceed')">
          Continue
        </button>
        <button
          type="button"
          class="btn"
          :class="autoplay ? 'btn-primary pause' : 'btn-ghost'"
          :aria-pressed="autoplay"
          @click="$emit('autoplay')"
        >
          {{ autoplay ? 'Pause' : 'Autoplay' }}
        </button>
        <button
          type="button"
          class="btn btn-ghost restart"
          aria-label="Replay from the start"
          :disabled="busy"
          @click="$emit('restart')"
        >
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="M3 12a9 9 0 1 0 3-6.7" />
            <path d="M3 4v5h5" />
          </svg>
        </button>
      </div>
      <p class="hint">
        {{
          autoplay
            ? 'Continues every 2 seconds.'
            : 'Continue plays your action, then the game up to your next decision.'
        }}
      </p>
      <RouterLink :to="{ name: 'history' }" class="btn-link leave">Leave the replay</RouterLink>
    </template>

    <p v-if="error" class="alert" role="alert">{{ error }}</p>
  </section>
</template>

<style scoped>
.replay {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.eyebrow {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--seal);
}

.where {
  font-size: 12px;
  color: var(--ink-2);
}

.timeline {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.track {
  position: relative;
  height: 14px;
}

.track::before,
.done {
  content: '';
  position: absolute;
  left: 0;
  top: 6px;
  height: 3px;
  border-radius: 2px;
}

.track::before {
  right: 0;
  background: var(--paper-3);
}

.done {
  background: var(--ink);
}

.tick {
  position: absolute;
  top: 3px;
  width: 2px;
  height: 9px;
  margin-left: -1px;
  background: var(--seal);
}

.now {
  position: absolute;
  top: 1px;
  width: 12px;
  height: 12px;
  margin-left: -6px;
  box-sizing: border-box;
  border-radius: 50%;
  border: 2px solid var(--paper-2);
  background: var(--ink);
}

.rounds {
  position: relative;
  height: 14px;
  font-size: 11px;
  color: var(--ink-3);
}

.rounds span {
  position: absolute;
}

.since {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
  line-height: 1.4;
  color: var(--ink-2);
}

.since p,
.next p {
  margin: 0;
}

.more {
  color: var(--ink-3);
}

.next {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 2px;
  background: var(--card);
  font-size: 14px;
  line-height: 1.4;
}

.next-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink-2);
}

.buttons {
  display: flex;
  gap: 8px;
}

.buttons .btn {
  text-transform: none;
}

.continue {
  flex: 1;
}

.restart {
  width: 44px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pause {
  background: var(--seal);
  border-color: var(--seal);
}

.hint {
  margin: 0;
  font-size: 12px;
  color: var(--ink-3);
}

.leave {
  align-self: flex-start;
  font-size: 12px;
  padding: 0;
}

.jumping {
  margin: 0;
  padding: 18px 0;
}

.result h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 26px;
}

.result p {
  margin: 4px 0 0;
  color: var(--ink-2);
}

.alert {
  margin: 0;
  line-height: 1.45;
  color: var(--seal);
}
</style>
