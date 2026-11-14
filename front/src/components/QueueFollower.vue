<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

import { fetchGame, type GameViewData } from '@/api/game'
import enso from '@/assets/mons/neutral.svg'
import ClanMon from '@/components/ClanMon.vue'
import MatchAnnouncement from '@/components/MatchAnnouncement.vue'
import { follow } from '@/lib/hub'
import {
  ANNOUNCEMENT_DELAY,
  ANNOUNCEMENT_LEAVE,
  ANNOUNCEMENT_READ_LIMIT,
  TABLE_WAIT_LIMIT,
  announcementOf,
  tableSet,
  type Announcement,
} from '@/lib/matchAnnouncement'
import { CHECK_DELAY, FOUND_DELAY, checkQueue, found, gameFound, now, waiting } from '@/lib/queueing'
import { session } from '@/lib/session'
import { vTopLayer } from '@/lib/topLayer'
import { visiblePolling, type VisiblePolling } from '@/lib/visiblePolling'

/**
 * Follows the wait of the user in the queue on every page (`lib/queueing.ts`): the queue is read when the session
 * changes, then, while the user waits, the hub tells once the game has started (`game_started` on their topic) and the
 * queue is read again every `CHECK_DELAY` should the hub not tell. Once found (a game of the queue, of an invitation
 * or against the house bot), the game is read and the match announced over the page for `ANNOUNCEMENT_DELAY`: both
 * players and their nobori (`MatchAnnouncement`); should the game not be read within `ANNOUNCEMENT_READ_LIMIT`, a
 * plain "Opponent found" shows for `FOUND_DELAY`. Then the game opens (the page left, a game watched is no longer
 * watched) under the announcement, which stays until the table is drawn (`tableSet`, at most `TABLE_WAIT_LIMIT`),
 * then fades out over it: nothing shows between them.
 */
const router = useRouter()

let unfollow: (() => void) | null = null
let polling: VisiblePolling | null = null
let ticking: ReturnType<typeof setInterval> | null = null

/** the match announced, once the game is read */
const announcement = ref<Announcement | null>(null)
/** the game found is being read: a dark stage meanwhile */
const reading = ref(false)
/** the table is drawn: the announcement fades out over it */
const leaving = ref(false)

/** the view of the game found, or null when it is not read in time */
async function readFound(gameId: string): Promise<GameViewData | null> {
  const token = session.value?.token
  if (!token) {
    return null
  }
  const limit = new Promise<null>((resolve) => setTimeout(() => resolve(null), ANNOUNCEMENT_READ_LIMIT))

  return Promise.race([fetchGame(token, gameId).catch(() => null), limit])
}

/** resolves once the table of the game is drawn, or after `TABLE_WAIT_LIMIT` */
function tableDrawn(gameId: string): Promise<void> {
  if (tableSet.value === gameId) {
    return Promise.resolve()
  }

  return new Promise((resolve) => {
    const limit = setTimeout(done, TABLE_WAIT_LIMIT)
    const unwatch = watch(tableSet, (set) => {
      if (set === gameId) {
        done()
      }
    })
    function done(): void {
      clearTimeout(limit)
      unwatch()
      resolve()
    }
  })
}

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

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

watch(found, async (gameId) => {
  announcement.value = null
  leaving.value = false
  if (gameId === null) {
    return
  }
  tableSet.value = null
  reading.value = true
  const view = await readFound(gameId)
  reading.value = false
  if (found.value !== gameId) {
    return
  }
  announcement.value = view === null ? null : announcementOf(view)
  await delay(announcement.value === null ? FOUND_DELAY : ANNOUNCEMENT_DELAY)
  if (found.value !== gameId) {
    return
  }
  // the game opens under the announcement, which goes once its table is drawn
  await router.push({ name: 'game', params: { id: gameId } })
  await tableDrawn(gameId)
  if (found.value !== gameId) {
    return
  }
  leaving.value = true
  await delay(ANNOUNCEMENT_LEAVE)
  if (found.value === gameId) {
    found.value = null
  }
})

onBeforeUnmount(stop)
</script>

<template>
  <template v-if="found !== null">
    <MatchAnnouncement
      v-if="announcement"
      v-top-layer
      :announcement="announcement"
      :duration="ANNOUNCEMENT_DELAY"
      :leaving="leaving"
    />
    <div v-else-if="reading" v-top-layer class="stage" aria-hidden="true" />
    <div v-else v-top-layer class="found inverted" :class="{ leaving }" role="status">
      <ClanMon :src="enso" :size="120" color="var(--seal)" />
      <h1>Opponent found</h1>
      <p class="lead">Your game opens.</p>
    </div>
  </template>
</template>

<style scoped>
/* the dark stage of the announcement, while the game is read */
.stage {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: #0d0b09;
}

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

.found.leaving {
  animation: leave 0.25s ease-in both;
}

@keyframes leave {
  to {
    opacity: 0;
  }
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
