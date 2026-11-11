<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { useRouter } from 'vue-router'

import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import { follow } from '@/lib/hub'
import { CHECK_DELAY, FOUND_DELAY, checkQueue, found, gameFound, now, waiting } from '@/lib/queueing'
import { session } from '@/lib/session'
import { visiblePolling, type VisiblePolling } from '@/lib/visiblePolling'

/**
 * Follows the wait of the user in the queue on every page (`lib/queueing.ts`): the queue is read when the session
 * changes, then, while the user waits, the hub tells once the game has started (`game_started` on their topic) and the
 * queue is read again every `CHECK_DELAY` should the hub not tell. Once found, "Opponent found" shows over the page
 * for `FOUND_DELAY`, then the game opens: the page left, a game watched is no longer watched.
 */
const router = useRouter()

let unfollow: (() => void) | null = null
let polling: VisiblePolling | null = null
let ticking: ReturnType<typeof setInterval> | null = null
let opening: ReturnType<typeof setTimeout> | null = null

function check(): void {
  const current = session.value
  if (current === null) {
    return
  }
  checkQueue(current.token, current.userId).catch(() => {
    // read again later
  })
}

function stop(): void {
  unfollow?.()
  unfollow = null
  polling?.stop()
  polling = null
  if (ticking !== null) {
    clearInterval(ticking)
    ticking = null
  }
}

watch(
  [() => session.value?.token, () => session.value?.userId],
  ([token]) => {
    if (!token) {
      waiting.value = null

      return
    }
    check()
  },
  { immediate: true },
)

watch(
  () => (waiting.value === null || !session.value ? null : session.value.token),
  (token) => {
    stop()
    if (token === null) {
      return
    }
    unfollow = follow(
      token,
      (subscription) => [subscription.topic],
      (data) => {
        if (data.event === 'game_started' && typeof data.id === 'string' && waiting.value !== null) {
          gameFound(data.id)
        }
      },
    )
    // the queue has just been read: the polling reads it again later only
    let first = true
    polling = visiblePolling(() => {
      if (!first) {
        check()
      }
      first = false
    }, CHECK_DELAY)
    now.value = Date.now()
    ticking = setInterval(() => (now.value = Date.now()), 1000)
  },
  { immediate: true },
)

watch(found, (gameId) => {
  if (gameId === null) {
    return
  }
  opening = setTimeout(() => {
    opening = null
    found.value = null
    void router.push({ name: 'game', params: { id: gameId } })
  }, FOUND_DELAY)
})

onBeforeUnmount(() => {
  stop()
  if (opening !== null) {
    clearTimeout(opening)
  }
})
</script>

<template>
  <div v-if="found !== null" class="found inverted" role="status">
    <ClanMon :src="enso" :size="120" color="var(--seal)" />
    <h1>Opponent found</h1>
    <p class="lead">Your game opens.</p>
  </div>
</template>

<style scoped>
.found {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 20px;
  text-align: center;
  background: color-mix(in srgb, var(--paper) 92%, transparent);
  color: var(--ink);
  animation: fade 0.2s ease-out both;
}

h1 {
  font-size: 56px;
  line-height: 1;
}

.lead {
  font-size: 17px;
  color: var(--ink-2);
}
</style>
