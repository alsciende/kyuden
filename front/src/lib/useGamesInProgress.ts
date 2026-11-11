import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue'

import { ApiProblem } from '@/api/client'
import { fetchGamesInProgress } from '@/api/game'
import { session } from '@/lib/session'
import { visiblePolling, type VisiblePolling } from '@/lib/visiblePolling'
import { watchableGameRow, type WatchableGameRow } from '@/lib/watch'

/** how often the games in progress are read again, as games start and end */
export const REFRESH_DELAY = 15_000

export interface GamesInProgress {
  /** null while the games are read for the first time */
  games: Ref<WatchableGameRow[] | null>
  /** why the games could not be read last time, the games read before kept */
  error: Ref<string | null>
}

/**
 * The games in progress an account may watch, read while the component is mounted: at once, then every
 * `REFRESH_DELAY` while the page is visible.
 */
export function useGamesInProgress(): GamesInProgress {
  const games = ref<WatchableGameRow[] | null>(null)
  const error = ref<string | null>(null)
  let polling: VisiblePolling | null = null

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
    polling = visiblePolling(() => void read(), REFRESH_DELAY)
  })

  onBeforeUnmount(() => {
    polling?.stop()
  })

  return { games, error }
}
