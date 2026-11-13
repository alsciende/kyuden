import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

import { fetchQueueCount } from '@/api/client'
import { session } from '@/lib/session'
import { visiblePolling, type VisiblePolling } from '@/lib/visiblePolling'

/** how often the players in the queue are counted again, as they join it and are matched */
export const REFRESH_DELAY = 15_000

/**
 * How many players wait in the open queue for an opponent, the user left out, read while the component is mounted: at
 * once, then every `REFRESH_DELAY` while the page is visible. Null until it is read, and kept as it was when it cannot
 * be read again (nothing to tell then: it only invites the player to join).
 */
export function useQueueCount(): Ref<number | null> {
  const count = ref<number | null>(null)
  let polling: VisiblePolling | null = null

  async function read(): Promise<void> {
    const current = session.value
    if (current === null) {
      return
    }
    try {
      count.value = (await fetchQueueCount(current.token)).waiting
    } catch {
      // the count stays as it was
    }
  }

  onMounted(() => {
    polling = visiblePolling(() => void read(), REFRESH_DELAY)
  })

  onBeforeUnmount(() => {
    polling?.stop()
  })

  return count
}

/** what the count tells: how many players wait, and what joining the queue does then */
export function queueCountTexts(count: number): { title: string; hint: string } {
  if (count === 0) {
    return { title: 'Nobody waits in the queue right now', hint: 'Join it: the next player who arrives faces you.' }
  }

  return {
    title: count === 1 ? '1 player waits in the queue' : `${count} players wait in the queue`,
    hint: 'Join now: your game starts at once.',
  }
}
