<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'

import type { GameView } from '@/api/game'
import type { Cards } from '@/lib/board'
import { entryParts } from '@/lib/logText'
import type { ReplayLogRow } from '@/lib/replay'

/**
 * The log of the whole game replayed, in place of the last lines of the log: a heading at the beginning of each phase,
 * the messages replayed, the action of the player the replay stopped before, then those to come, dimmed. A click on a
 * message jumps to the board as it was once it was handled. The list follows where the replay stands.
 */
const props = defineProps<{ rows: ReplayLogRow[]; game: GameView; cards: Cards; version: number; busy: boolean }>()

defineEmits<{ jump: [version: number] }>()

const list = ref<HTMLElement | null>(null)

/** the text of a message: the names of the cards stand out (a click on the line jumps, no preview) */
function partsOf(row: Extract<ReplayLogRow, { kind: 'message' }>) {
  return row.entries.map((entry) => entryParts(entry, props.game, props.cards))
}

watch(
  () => props.version,
  async () => {
    await nextTick()
    const current = list.value?.querySelector('[data-current]')
    if (current instanceof HTMLElement && typeof current.scrollIntoView === 'function') {
      current.scrollIntoView({ block: 'nearest' })
    }
  },
  { immediate: true },
)
</script>

<template>
  <section class="replay-log" aria-label="Log of the game">
    <div class="title">
      <span class="label">Log of the game</span>
      <span class="aside">click to jump</span>
    </div>
    <ol ref="list" class="rows">
      <template v-for="row in rows" :key="row.key">
        <li v-if="row.kind === 'heading'" class="heading" :class="{ future: row.future }">{{ row.text }}</li>
        <li v-else :data-current="row.state === 'next' || row.version === version ? '' : undefined">
          <button
            type="button"
            class="row"
            :class="[row.state, { mine: row.mine }]"
            :disabled="busy || row.version === version"
            :aria-current="row.version === version ? 'step' : undefined"
            :aria-label="row.version === version ? undefined : `Jump to message ${row.version}`"
            @click="$emit('jump', row.version)"
          >
            <span class="version">#{{ row.version }}</span>
            <span class="text">
              <span v-for="(parts, index) in partsOf(row)" :key="index" class="entry"
                ><template v-for="(part, partIndex) in parts" :key="partIndex"
                  ><strong v-if="part.code">{{ part.text }}</strong
                  ><template v-else>{{ part.text }}</template></template
                ></span
              >
            </span>
            <span v-if="row.state === 'next'" class="next-tag">Next</span>
          </button>
        </li>
      </template>
    </ol>
  </section>
</template>

<style scoped>
.replay-log {
  padding-top: 18px;
  border-top: 1px solid var(--line);
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-height: 0;
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

.rows {
  margin: 0;
  padding: 0;
  list-style: none;
  max-height: 340px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.heading {
  margin-top: 8px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--ink-2);
}

.heading.future {
  color: var(--ink-3);
}

.row {
  font: inherit;
  width: 100%;
  display: flex;
  gap: 8px;
  align-items: baseline;
  text-align: left;
  border: 0;
  border-radius: 2px;
  padding: 3px 6px;
  background: transparent;
  color: var(--ink);
  font-size: 12px;
  line-height: 1.4;
  cursor: pointer;
}

.row:hover:not(:disabled) {
  background: var(--paper-3);
}

.row:disabled {
  cursor: default;
}

.row[aria-current='step'] {
  background: var(--paper-3);
}

.row.future {
  color: var(--ink-3);
}

.row.next {
  background: var(--selected);
  color: var(--ink);
}

.row.mine .version {
  color: var(--seal);
}

.version {
  flex: 0 0 30px;
  font-size: 11px;
  color: var(--ink-3);
}

.text {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.next-tag {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--seal);
}
</style>
