<script setup lang="ts">
import { computed } from 'vue'

import type { GameView, LoggedMessage, Send } from '@/api/game'
import ActionPanel from '@/components/game/ActionPanel.vue'
import LogLine from '@/components/game/LogLine.vue'
import { PHASES, rings, stepOf, type Cards } from '@/lib/board'
import { ELEMENT_ICONS, ELEMENT_NAMES, ringTip } from '@/lib/icons'
import type { Selection } from '@/lib/selection'
import { vTip } from '@/lib/tooltip'

/**
 * The bar on the right of the board: the step of the round, what the player does in it, then the rings and the last
 * lines of the log (the players are in their bars, above and below the board).
 */
const props = defineProps<{
  game: GameView
  cards: Cards
  log: LoggedMessage[]
  selection: Selection
  sending: boolean
  error: string | null
  send: Send
}>()

defineEmits<{ openLog: [] }>()

const step = computed(() => stepOf(props.game.place))

const ringList = computed(() => rings(props.game))
const unclaimedFate = computed(() =>
  ringList.value.filter((ring) => ring.status === 'unclaimed').reduce((total, ring) => total + ring.fate, 0),
)

const RING_STATUS = { unclaimed: '', contested: 'Contested', yours: 'You', theirs: 'Opp.' }

/** the last lines of the log */
const tail = computed(() => props.log.flatMap((message) => message.entries).slice(-3))
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

    <section class="rings" aria-label="Rings">
      <div class="title">
        <span class="label">Rings</span>
        <span class="aside">{{ unclaimedFate }} fate on the unclaimed rings</span>
      </div>
      <div class="ring-list">
        <div v-for="ring in ringList" :key="ring.element" class="ring-cell">
          <div
            v-tip="ringTip(ring.element)"
            class="ring"
            :class="ring.status"
            role="img"
            tabindex="0"
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
    </section>

    <section class="log" aria-label="Last lines of the log">
      <div class="title">
        <span class="label">Log</span>
        <button type="button" class="btn-link" @click="$emit('openLog')">Show all</button>
      </div>
      <LogLine v-for="(entry, index) in tail" :key="index" :entry="entry" :game="game" :cards="cards" />
    </section>
  </aside>
</template>

<style scoped>
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
