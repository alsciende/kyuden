/**
 * How long a game may stay at the same version before its player reads it again, in milliseconds: a notification of
 * the hub may be lost.
 */
export const IDLE_DELAY = 15_000

export interface IdleWatch {
  /** something has happened: the delay starts again */
  touch(): void
  /** nothing more to watch */
  stop(): void
}

/**
 * Calls `onIdle` once nothing has happened for `delay` milliseconds since the last `touch()`, then every `delay`
 * milliseconds while nothing happens, until it is stopped. Nothing is watched before the first `touch()`.
 */
export function idleWatch(onIdle: () => void, delay: number = IDLE_DELAY): IdleWatch {
  let timer: ReturnType<typeof setTimeout> | null = null

  function stop(): void {
    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
  }

  function touch(): void {
    stop()
    timer = setTimeout(() => {
      touch()
      onIdle()
    }, delay)
  }

  return { touch, stop }
}
