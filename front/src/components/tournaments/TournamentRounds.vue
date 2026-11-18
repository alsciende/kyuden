<script setup lang="ts">
import { computed } from 'vue'

import type { Tournament, TournamentMatch, TournamentUser } from '@/api/client'
import { matchResultLabel, roundTitle } from '@/lib/tournaments'

/**
 * The rounds of a tournament under way, one column each: those begun with their matches (the winner, the player
 * eliminated struck, how it ended; the game in progress to watch; who waits in the queue), the rounds to come as
 * placeholders, their pairings revealed once the previous round has ended.
 */
const props = defineProps<{ tournament: Tournament; userId: string | null }>()

const columns = computed(() => {
  const count = props.tournament.round_count ?? 0

  return Array.from({ length: count }, (_, index) => {
    const number = index + 1
    const round = props.tournament.rounds.find((candidate) => candidate.number === number)
    const current = number === props.tournament.current_round && props.tournament.status === 'in_progress'

    return {
      number,
      title: roundTitle(number, props.tournament.round_count),
      state: current ? 'now' : round !== undefined ? 'ended' : null,
      matches: round?.matches ?? null,
      final: number === count,
    }
  })
})

function lost(match: TournamentMatch, player: TournamentUser): boolean {
  return match.outcome !== null && match.outcome !== 'bye' && match.winner_user_id !== player.user_id
}

function playing(match: TournamentMatch): boolean {
  return match.outcome === null && match.game_id !== null
}

function isMine(match: TournamentMatch): boolean {
  return match.players.some((player) => player.user_id === props.userId)
}

/** who of its players waits in the queue with it, before its game */
function waitingLabel(match: TournamentMatch): string | null {
  if (match.outcome !== null || match.game_id !== null) {
    return null
  }
  const waiting = match.players.filter((player) => match.waiting.includes(player.user_id))
  if (waiting.length === 0) {
    return 'Waiting for its players'
  }

  return `${waiting.map((player) => (player.user_id === props.userId ? 'You' : player.name)).join(', ')} in the queue`
}
</script>

<template>
  <div class="rounds">
    <section v-for="column in columns" :key="column.number" :aria-labelledby="`round-${column.number}`">
      <h3 :id="`round-${column.number}`" :class="{ now: column.state === 'now' }">
        {{ column.title }}<template v-if="column.state"> · {{ column.state }}</template>
      </h3>
      <template v-if="column.matches">
        <div v-for="match in column.matches" :key="match.id" class="match" :class="{ mine: isMine(match) }">
          <span
            v-for="player in match.players"
            :key="player.user_id"
            :class="{
              won: match.winner_user_id === player.user_id && match.outcome !== 'bye',
              lost: lost(match, player),
            }"
          >
            {{ player.name }}<span v-if="player.user_id === userId" class="hint"> (you)</span>
          </span>
          <span v-if="matchResultLabel(match)" class="result">{{ matchResultLabel(match) }}</span>
          <RouterLink v-else-if="playing(match)" :to="{ name: 'game', params: { id: match.game_id } }" class="watch"
            >In progress · Watch</RouterLink
          >
          <span v-else class="result pending">{{ waitingLabel(match) }}</span>
        </div>
      </template>
      <p v-else class="placeholder">
        {{
          column.final
            ? 'The winner of the final wins the tournament.'
            : 'Pairings revealed once the previous round has ended.'
        }}
      </p>
    </section>
  </div>
</template>

<style scoped>
.rounds {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(220px, 1fr);
  gap: 20px;
  align-items: start;
  overflow-x: auto;
}

section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

h3 {
  margin: 0 0 4px;
  font-family: var(--font-ui);
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-3);
}

h3.now {
  color: var(--seal);
}

.match {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 14px;
  font-size: 15px;
  background: var(--card);
  border: 1.5px solid var(--line);
  overflow-wrap: anywhere;
}

.match.mine {
  border-color: var(--seal);
}

.won {
  font-weight: 700;
}

.lost {
  color: var(--ink-2);
  text-decoration: line-through;
}

.result,
.watch {
  font-size: 14px;
  color: var(--ink-2);
}

.watch {
  font-weight: 600;
  color: var(--ink);
}

.pending {
  font-weight: 600;
}

.hint {
  font-size: 14px;
  color: var(--ink-2);
}

.placeholder {
  display: flex;
  align-items: center;
  min-height: 96px;
  margin: 0;
  padding: 12px 14px;
  font-size: 14px;
  color: var(--ink-2);
  border: 1.5px dashed var(--line);
}
</style>
