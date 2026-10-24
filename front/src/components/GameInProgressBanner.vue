<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'

import { findGameInProgress, type GameInProgress } from '@/lib/gameInProgress'
import { inverted } from '@/lib/page'
import { session } from '@/lib/session'

/**
 * Under the header, the game the player left in progress, to rejoin it: read again on every page, since the player
 * may have finished it, or signed in to an account that plays one.
 */
const route = useRoute()

const game = ref<GameInProgress | null>(null)
let reading = 0

watch(
  [() => route.name, () => session.value?.token, () => session.value?.userId],
  async ([, token, userId]) => {
    const current = ++reading
    if (!token || !userId) {
      game.value = null

      return
    }
    try {
      const found = await findGameInProgress(token, userId)
      // a later read wins
      if (current === reading) {
        game.value = found
      }
    } catch {
      if (current === reading) {
        game.value = null
      }
    }
  },
  { immediate: true },
)
</script>

<template>
  <!-- not on the dark screen of the wait in the queue -->
  <aside v-if="game && !inverted" class="in-progress" aria-label="Game in progress">
    <span class="text">
      <template v-if="game.opponent">
        Your game against <strong class="opponent">{{ game.opponent }}</strong> is still in progress.
      </template>
      <template v-else>Your game is still in progress.</template>
    </span>
    <RouterLink :to="{ name: 'game', params: { id: game.id } }" class="btn btn-primary">Rejoin</RouterLink>
  </aside>
</template>

<style scoped>
.in-progress {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
  padding: 10px 48px;
  background: var(--paper-2);
  border-bottom: 1px solid var(--line);
  font-size: 15px;
  color: var(--ink-2);
}

.text {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.opponent {
  font-family: var(--font-display);
  font-size: 17px;
  font-weight: 700;
  color: var(--ink);
}

.btn {
  min-height: 40px;
  padding: 0 18px;
  font-size: 15px;
}
</style>
