<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { ApiProblem, fetchSelfGames } from '@/api/client'
import MatchUp from '@/components/MatchUp.vue'
import { playedGameRows, type PlayedGameRow } from '@/lib/history'
import { session } from '@/lib/session'

/**
 * The games the user has played, the last ended first: who played, the result from their side, when it ended, and the
 * way to replay each one as they saw it.
 */

/** null while the games are read */
const games = ref<PlayedGameRow[] | null>(null)
const error = ref<string | null>(null)

onMounted(async () => {
  const current = session.value
  if (current === null) {
    return
  }
  try {
    games.value = playedGameRows(await fetchSelfGames(current.token), current.userId)
  } catch (problem) {
    error.value = problem instanceof ApiProblem ? problem.detail : 'Your games cannot be read.'
  }
})
</script>

<template>
  <div class="history screen">
    <div class="heading">
      <div class="heading-text">
        <div class="eyebrow">Your games</div>
        <h1>Games played</h1>
        <p class="lead">
          Replay a game as you saw it: the board stops before each of your actions. Nothing of your opponent is revealed
          that you did not see.
        </p>
      </div>
      <RouterLink to="/" class="btn-link">Back</RouterLink>
    </div>

    <section class="games" aria-label="Games played">
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <template v-else-if="games !== null && games.length === 0">
        <p class="empty">You have not finished a game yet.</p>
        <RouterLink :to="{ name: 'queue' }" class="btn btn-primary btn-large empty-action">Play a game</RouterLink>
      </template>
      <template v-else-if="games !== null">
        <div class="columns" aria-hidden="true">
          <span class="form-label">Players</span>
          <span class="form-label">Result</span>
          <span class="form-label">Ended</span>
          <span></span>
        </div>
        <ul>
          <li v-for="game in games" :key="game.id" class="game">
            <div class="players">
              <MatchUp :players="game.players" />
              <span v-if="game.bot" class="tag">Bot</span>
              <span v-if="game.friendly" class="tag">Friendly</span>
            </div>
            <div class="result">
              <span class="title" :class="{ won: game.won }">{{ game.result }}</span>
              <span class="how">{{ game.how }}</span>
            </div>
            <span class="ended">{{ game.ended }}</span>
            <RouterLink
              :to="{ name: 'replay', params: { id: game.id } }"
              class="btn btn-ghost go"
              :aria-label="`Replay your game against ${game.players
                .slice(1)
                .map((player) => player.name)
                .join(' and ')}`"
            >
              Replay
            </RouterLink>
          </li>
        </ul>
      </template>
    </section>
  </div>
</template>

<style scoped>
.history {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 36px;
  padding: 52px 96px 48px;
}

.heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
}

.heading-text {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

h1 {
  font-size: 64px;
  line-height: 1;
}

.lead {
  max-width: 640px;
  font-size: 16px;
  color: var(--ink-2);
}

.games {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 24px;
  border-top: 1px solid var(--line);
}

.games ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.columns,
.game {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px 150px 140px;
  align-items: center;
  gap: 24px;
}

.columns {
  padding: 0 21px;
}

.columns .form-label {
  color: var(--ink-3);
}

.game {
  min-height: 72px;
  padding: 0 20px;
  background: var(--card);
  border: 1.5px solid var(--line);
  border-radius: 2px;
}

.game:hover {
  border-color: var(--ink);
}

.players {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.tag {
  flex: none;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ink-3);
  border: 1px solid var(--line);
  border-radius: 2px;
  padding: 2px 6px;
}

.result {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.title {
  font-size: 15px;
  font-weight: 700;
  color: var(--seal);
}

.title.won {
  color: var(--ink);
}

.how,
.ended {
  font-size: 14px;
  color: var(--ink-2);
}

.go {
  min-height: 44px;
  padding: 0 20px;
  font-size: 15px;
}

.empty {
  font-size: 17px;
  color: var(--ink-2);
}

.empty-action {
  align-self: flex-start;
}
</style>
