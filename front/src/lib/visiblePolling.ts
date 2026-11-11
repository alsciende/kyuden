export interface VisiblePolling {
  /** nothing more to read */
  stop(): void
}

/**
 * Calls `read` at once, then every `delay` milliseconds while the page is visible, until it is stopped. A page put in
 * the background reads nothing; once visible again, it reads at once, then every `delay` milliseconds again.
 */
export function visiblePolling(read: () => void, delay: number, doc: Document = document): VisiblePolling {
  let timer: ReturnType<typeof setInterval> | null = null

  function pause(): void {
    if (timer !== null) {
      clearInterval(timer)
      timer = null
    }
  }

  function resume(): void {
    pause()
    read()
    timer = setInterval(read, delay)
  }

  function onVisibilityChange(): void {
    if (doc.visibilityState === 'hidden') {
      pause()
    } else {
      resume()
    }
  }

  doc.addEventListener('visibilitychange', onVisibilityChange)
  if (doc.visibilityState === 'hidden') {
    read()
  } else {
    resume()
  }

  return {
    stop(): void {
      doc.removeEventListener('visibilitychange', onVisibilityChange)
      pause()
    },
  }
}
