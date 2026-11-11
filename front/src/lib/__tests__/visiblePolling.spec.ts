import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { visiblePolling } from '@/lib/visiblePolling'

let visibility: DocumentVisibilityState = 'visible'

function setVisibility(state: DocumentVisibilityState): void {
  visibility = state
  document.dispatchEvent(new Event('visibilitychange'))
}

describe('visiblePolling', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    visibility = 'visible'
    vi.spyOn(document, 'visibilityState', 'get').mockImplementation(() => visibility)
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('reads at once, then after each delay', () => {
    const read = vi.fn()
    const polling = visiblePolling(read, 1000)
    expect(read).toHaveBeenCalledTimes(1)
    vi.advanceTimersByTime(999)
    expect(read).toHaveBeenCalledTimes(1)
    vi.advanceTimersByTime(1)
    expect(read).toHaveBeenCalledTimes(2)
    vi.advanceTimersByTime(1000)
    expect(read).toHaveBeenCalledTimes(3)
    polling.stop()
  })

  it('reads nothing while the page is hidden, and at once when it is visible again', () => {
    const read = vi.fn()
    const polling = visiblePolling(read, 1000)
    setVisibility('hidden')
    vi.advanceTimersByTime(5000)
    expect(read).toHaveBeenCalledTimes(1)
    setVisibility('visible')
    expect(read).toHaveBeenCalledTimes(2)
    vi.advanceTimersByTime(1000)
    expect(read).toHaveBeenCalledTimes(3)
    polling.stop()
  })

  it('reads once when it starts in the background, then waits for the page to be visible', () => {
    visibility = 'hidden'
    const read = vi.fn()
    const polling = visiblePolling(read, 1000)
    expect(read).toHaveBeenCalledTimes(1)
    vi.advanceTimersByTime(5000)
    expect(read).toHaveBeenCalledTimes(1)
    setVisibility('visible')
    expect(read).toHaveBeenCalledTimes(2)
    polling.stop()
  })

  it('reads nothing once stopped', () => {
    const read = vi.fn()
    visiblePolling(read, 1000).stop()
    vi.advanceTimersByTime(5000)
    setVisibility('hidden')
    setVisibility('visible')
    expect(read).toHaveBeenCalledTimes(1)
  })
})
