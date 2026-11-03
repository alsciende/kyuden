import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { IDLE_DELAY, idleWatch } from '@/lib/idleWatch'

describe('idleWatch', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('watches nothing before the first touch', () => {
    const onIdle = vi.fn()
    idleWatch(onIdle)

    vi.advanceTimersByTime(IDLE_DELAY * 3)

    expect(onIdle).not.toHaveBeenCalled()
  })

  it('calls back once nothing has happened for the delay, then again while nothing happens', () => {
    const onIdle = vi.fn()
    const watch = idleWatch(onIdle, 1000)
    watch.touch()

    vi.advanceTimersByTime(999)
    expect(onIdle).not.toHaveBeenCalled()
    vi.advanceTimersByTime(1)
    expect(onIdle).toHaveBeenCalledTimes(1)
    vi.advanceTimersByTime(1000)
    expect(onIdle).toHaveBeenCalledTimes(2)
  })

  it('starts the delay again when something happens', () => {
    const onIdle = vi.fn()
    const watch = idleWatch(onIdle, 1000)
    watch.touch()

    vi.advanceTimersByTime(800)
    watch.touch()
    vi.advanceTimersByTime(800)

    expect(onIdle).not.toHaveBeenCalled()
    vi.advanceTimersByTime(200)
    expect(onIdle).toHaveBeenCalledTimes(1)
  })

  it('calls back no more once stopped', () => {
    const onIdle = vi.fn()
    const watch = idleWatch(onIdle, 1000)
    watch.touch()

    watch.stop()
    vi.advanceTimersByTime(5000)

    expect(onIdle).not.toHaveBeenCalled()
  })
})
