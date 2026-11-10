<script setup lang="ts">
import { computed } from 'vue'

import type { GameView } from '@/api/game'
import CharacterRow from '@/components/game/CharacterRow.vue'
import PlayerChip from '@/components/game/PlayerChip.vue'
import SpecialEffects from '@/components/game/SpecialEffects.vue'
import type { Cards } from '@/lib/board'
import { DUEL_STEPS, duelBoard, type DuelSide } from '@/lib/duel'
import { CONFLICT_ICONS } from '@/lib/icons'
import type { Effect } from '@/lib/playerBar'

/**
 * The duel being resolved, in the middle of the board (mockup `E8u3G7M7AU4ZeEqkbGXdwf`): the side of the opponent
 * above the side of the viewer, each with its player and role on the left of its characters, then its honor bid, its
 * dueling statistic and its total; between them, where the duel stands. The steps in the header, and the "+" of the
 * special actions on the duel (cancelling it: Stay Your Hand). The actions of the steps are in the panel of actions.
 */
const props = defineProps<{ game: GameView; cards: Cards }>()

const emit = defineEmits<{ open: [id: string]; effect: [effect: Effect] }>()

const board = computed(() => duelBoard(props.game, props.cards)!)

const current = computed(() => DUEL_STEPS.findIndex((step) => step.id === board.value.step))

const effects = computed((): Effect[] =>
  props.game.available_actions.includes('cancel_duel')
    ? [{ type: 'cancel_duel', label: 'Cancel the duel', payload: {} }]
    : [],
)

/** the characters that win or lose, tagged so */
function tags(side: DuelSide): Record<string, string> {
  if (side.outcome === null) {
    return {}
  }

  return Object.fromEntries(
    side.characters.map((character) => [character.card.id, side.outcome === 'wins' ? 'Wins' : 'Loses']),
  )
}
</script>

<template>
  <section class="duel" aria-label="Duel">
    <header class="head">
      <span class="seal" aria-hidden="true">
        <!-- the star of Lucide (lucide.dev, ISC license): the icon font has no glyph for glory -->
        <svg
          v-if="board.duel.type === 'glory'"
          width="24"
          height="24"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linejoin="round"
        >
          <path
            d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"
          />
        </svg>
        <span v-else class="icon">{{ CONFLICT_ICONS[board.duel.type] }}</span>
      </span>
      <div class="title">
        <h2>{{ board.title }}</h2>
        <span class="context">{{ board.context }}</span>
      </div>
      <ol class="steps" aria-label="Steps of the duel">
        <li
          v-for="(step, index) in DUEL_STEPS"
          :key="step.id"
          :class="{ current: index === current, done: index < current }"
          :aria-current="index === current ? 'step' : undefined"
        >
          {{ step.label }}
        </li>
      </ol>
      <SpecialEffects
        class="more"
        :effects="effects"
        :game="game"
        :cards="cards"
        :sending="false"
        floating
        @run="emit('effect', $event)"
      />
    </header>

    <div class="grid">
      <span />
      <span class="colh">Honor bid</span>
      <span class="colh">{{ board.statistic }}</span>
      <span class="colh">Total</span>

      <template v-for="(side, index) in board.sides" :key="side.player.id">
        <div v-if="index === 1" class="band">
          <span class="band-title" :class="{ won: board.band.won }">{{ board.band.title }}</span>
          <span class="band-text">{{ board.band.text }}</span>
        </div>
        <div class="side">
          <div class="who">
            <PlayerChip :player="side.player" :viewer-id="game.viewer_id" />
            <span class="label">{{ side.role }}</span>
          </div>
          <CharacterRow
            :characters="side.characters"
            :cards="cards"
            :mine="index === 1"
            :tags="tags(side)"
            @open="emit('open', $event)"
          />
        </div>
        <div class="cell">
          <span class="dial" :class="{ secret: side.bid.secret, known: side.bid.value !== null }">{{
            side.bid.value ?? '?'
          }}</span>
          <span class="caption">{{ side.bid.caption }}</span>
        </div>
        <div class="cell">
          <span class="number" :class="{ muted: side.statistic === null }">{{ side.statistic ?? '–' }}</span>
          <span class="caption">{{ side.statisticCaption }}</span>
        </div>
        <div class="cell">
          <span
            class="number total"
            :class="{ muted: side.total === null, won: side.outcome === 'wins', lost: side.outcome === 'loses' }"
            >{{ side.total ?? '–' }}</span
          >
          <span v-if="side.total !== null" class="caption">bid + {{ board.statistic.toLowerCase() }}</span>
        </div>
      </template>
    </div>
  </section>
</template>

<style scoped>
.duel {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px 24px;
  border: 1.5px solid var(--ink);
  border-radius: 2px;
  background: var(--card);
}

.head {
  display: flex;
  align-items: center;
  gap: 18px;
}

.seal {
  flex: none;
  width: 52px;
  height: 52px;
  box-sizing: border-box;
  border-radius: 50%;
  border: 2px solid var(--seal);
  color: var(--seal);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
}

.title {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

h2 {
  font-size: 26px;
  line-height: 1;
}

.context {
  font-size: 13px;
  color: var(--ink-2);
}

.steps {
  list-style: none;
  margin: 0 0 0 auto;
  padding: 0;
  display: flex;
  gap: 6px;
}

.steps li {
  font-size: 12px;
  font-weight: 700;
  padding: 5px 10px;
  border-radius: 2px;
  border: 1px dashed var(--line);
  color: var(--ink-3);
}

.steps li.done {
  border-style: solid;
  color: var(--ink-2);
}

.steps li.current {
  border: 1px solid var(--ink);
  background: var(--ink);
  color: var(--paper);
}

.more {
  flex: none;
}

.grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) repeat(3, 120px);
  column-gap: 12px;
  align-items: center;
}

.colh {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-3);
  text-align: center;
}

.side {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 10px 0;
  min-width: 0;
}

.who {
  flex: none;
  width: 110px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

.label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

.band {
  grid-column: 1 / -1;
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 6px 14px;
  padding: 10px 0;
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
}

.band-title {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 20px;
}

.band-title.won {
  color: var(--seal);
}

.band-text {
  font-size: 13px;
  color: var(--ink-2);
}

.cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.dial {
  width: 58px;
  height: 58px;
  box-sizing: border-box;
  border-radius: 50%;
  border: 2px solid var(--ink);
  background: var(--ink);
  color: var(--paper);
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 30px;
}

/* chosen and not revealed: the value for its owner only, a question mark for the others */
.dial.secret {
  border: 1.5px dashed var(--ink-3);
  background: transparent;
  color: var(--ink-3);
}

.dial.secret.known {
  border: 2px dashed var(--seal);
  color: var(--seal);
}

.number {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 40px;
  line-height: 1;
}

.number.total {
  font-size: 52px;
}

.number.muted {
  color: var(--ink-3);
}

.number.won {
  color: var(--seal);
}

.number.lost {
  color: var(--ink-3);
}

.caption {
  min-height: 15px;
  font-size: 12px;
  color: var(--ink-2);
  white-space: nowrap;
}
</style>
