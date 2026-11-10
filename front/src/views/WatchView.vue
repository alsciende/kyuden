<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { ApiProblem } from '@/api/client'
import { fetchGamesInProgress } from '@/api/game'
import EyeIcon from '@/components/EyeIcon.vue'
import MatchUp from '@/components/MatchUp.vue'
import { session } from '@/lib/session'
import { spectatorsLabel, watchableGameRow, type WatchableGameRow } from '@/lib/watch'

/**
 * The games in progress, which an account watches: who plays, where they are, how many watch. The user joins their
 * own games as their player. The list is read again from time to time, as games start and end.
 */
const REFRESH_DELAY = 15_000

/** null while the games are read */
const games = ref<WatchableGameRow[] | null>(null)
const error = ref<string | null>(null)
let timer: ReturnType<typeof setInterval> | null = null

async function read(): Promise<void> {
  const current = session.value
  if (current === null) {
    return
  }
  try {
    games.value = (await fetchGamesInProgress(current.token)).map((game) => watchableGameRow(game, current.userId))
    error.value = null
  } catch (problem) {
    error.value = problem instanceof ApiProblem ? problem.detail : 'The games cannot be read.'
  }
}

onMounted(() => {
  void read()
  timer = setInterval(() => void read(), REFRESH_DELAY)
})

onBeforeUnmount(() => {
  if (timer !== null) {
    clearInterval(timer)
  }
})
</script>

<template>
  <div class="watch screen">
    <div class="heading">
      <div class="heading-text">
        <div class="eyebrow">Watch a game</div>
        <h1>Games in progress</h1>
        <p class="lead">
          The players see your name among the spectators. You see what is on the table, never a hand nor a face-down
          card.
        </p>
      </div>
      <RouterLink to="/" class="btn-link">Back</RouterLink>
    </div>

    <section class="games" aria-label="Games in progress">
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <template v-else-if="games !== null && games.length === 0">
        <p class="empty">No game in progress right now.</p>
        <RouterLink :to="{ name: 'queue' }" class="btn btn-primary btn-large empty-action">Play a game</RouterLink>
      </template>
      <template v-else-if="games !== null">
        <div class="columns" aria-hidden="true">
          <span class="form-label">Players</span>
          <span class="form-label">Where they are</span>
          <span class="form-label">Spectators</span>
          <span></span>
        </div>
        <ul>
          <li v-for="game in games" :key="game.id" class="game">
            <div class="players">
              <MatchUp :players="game.players" />
              <span v-if="game.bot" class="tag">Bot</span>
              <span v-if="game.friendly" class="tag">Friendly</span>
              <span v-if="game.yours" class="tag yours">Your game</span>
            </div>
            <div class="where">
              <span class="phase">{{ game.phase }}</span>
              <span class="step">{{ game.step }}</span>
            </div>
            <span class="spectators" :aria-label="spectatorsLabel(game.spectators)"
              ><EyeIcon :size="18" />{{ game.spectators }}</span
            >
            <RouterLink
              :to="{ name: 'game', params: { id: game.id } }"
              class="btn go"
              :class="game.yours ? 'btn-primary' : 'btn-ghost'"
              :aria-label="
                game.yours
                  ? `Join your game against ${game.players.map((player) => player.name).join(' and ')}`
                  : `Watch ${game.players.map((player) => player.name).join(' against ')}`
              "
            >
              {{ game.yours ? 'Join' : 'Watch' }}
            </RouterLink>
          </li>
        </ul>
      </template>
    </section>
  </div>
</template>

<style scoped>
.watch {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 28px;
  padding: 44px 96px 40px;
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
  grid-template-columns: minmax(0, 1fr) 260px 120px 140px;
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

@media (hover: hover) {
  .game:hover {
    border-color: var(--ink);
  }
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

.tag.yours {
  color: var(--seal);
  border-color: var(--seal);
}

.where {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.phase {
  font-size: 15px;
  font-weight: 700;
}

.step {
  font-size: 14px;
  color: var(--ink-2);
}

.spectators {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 15px;
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
