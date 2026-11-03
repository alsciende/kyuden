<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { fetchGamesInProgress } from '@/api/game'
import EyeIcon from '@/components/EyeIcon.vue'
import MatchUp from '@/components/MatchUp.vue'
import { HOUSE_BOT } from '@/data/houseBot'
import { session } from '@/lib/session'
import { watchableGameRow, spectatorsLabel, type WatchableGameRow } from '@/lib/watch'

/**
 * The start of an account: play a game (the queue, the house bot, a friendly game), or watch one of the games in
 * progress, the first ones shown.
 */
const SHOWN = 2

/** null while the games are read, or when they cannot be */
const games = ref<WatchableGameRow[] | null>(null)

const shown = computed(() => games.value?.slice(0, SHOWN) ?? [])
const more = computed(() => Math.max((games.value?.length ?? 0) - SHOWN, 0))

onMounted(async () => {
  const current = session.value
  if (current === null) {
    return
  }
  try {
    games.value = (await fetchGamesInProgress(current.token)).map((game) => watchableGameRow(game, current.userId))
  } catch {
    // the tile leads to the list, which tells
  }
})
</script>

<template>
  <div class="home screen">
    <div class="heading">
      <div class="eyebrow">Welcome back to court</div>
      <h1>{{ session?.name }}</h1>
    </div>
    <div class="tiles">
      <section class="tile" aria-labelledby="play">
        <div class="form-label">Play</div>
        <h2 id="play">Play a game</h2>
        <p class="lead">
          Choose one of your decks, then face the next player who joins the queue, or train against
          <strong>{{ HOUSE_BOT.name }}</strong
          >, {{ HOUSE_BOT.title }}.
        </p>
        <div class="tile-actions">
          <RouterLink :to="{ name: 'queue' }" class="btn btn-primary btn-large">Choose a deck</RouterLink>
          <RouterLink :to="{ name: 'invite' }" class="btn-link">Play a friendly game</RouterLink>
        </div>
      </section>
      <section class="tile" aria-labelledby="watch">
        <div class="form-label">Watch</div>
        <h2 id="watch">Watch a game</h2>
        <p class="lead">
          <template v-if="games === null">The games in progress at court.</template>
          <template v-else-if="games.length === 0">No game in progress right now.</template>
          <template v-else> {{ games.length }} game{{ games.length === 1 ? '' : 's' }} in progress at court. </template>
          You see what both players see on the table, never their hands.
        </p>
        <ul v-if="shown.length > 0" class="games">
          <li v-for="game in shown" :key="game.id" class="game">
            <MatchUp :players="game.players" />
            <span class="spectators" :aria-label="spectatorsLabel(game.spectators)"
              ><EyeIcon />{{ game.spectators }}</span
            >
          </li>
          <li v-if="more > 0" class="game more">and {{ more }} more</li>
        </ul>
        <div class="tile-actions">
          <RouterLink :to="{ name: 'watch' }" class="btn btn-ghost btn-large">See the games</RouterLink>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.home {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 40px;
  padding: 52px 96px 48px;
}

.heading {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

h1 {
  font-size: 64px;
  line-height: 1;
  overflow-wrap: anywhere;
}

.tiles {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 32px;
}

.tile {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 36px 36px 32px;
  background: var(--card);
  border: 1.5px solid var(--line);
  border-radius: 2px;
}

.tile .form-label {
  color: var(--ink-3);
}

h2 {
  font-size: 40px;
  line-height: 1.05;
}

.lead {
  font-size: 17px;
  line-height: 1.5;
  color: var(--ink-2);
  max-width: 520px;
}

.lead strong {
  color: var(--ink);
}

.tile-actions {
  margin-top: auto;
  display: flex;
  align-items: center;
  gap: 24px;
}

.games {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
}

.game {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  font-size: 15px;
  border-top: 1px solid var(--paper-3);
}

.game:last-child {
  border-bottom: 1px solid var(--paper-3);
}

.more {
  color: var(--ink-3);
}

.spectators {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 14px;
  color: var(--ink-2);
}
</style>
