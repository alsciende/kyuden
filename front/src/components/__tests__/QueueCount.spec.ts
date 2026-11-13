import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'

import QueueCount from '@/components/queue/QueueCount.vue'
import { session } from '@/lib/session'
import { queueCountTexts, REFRESH_DELAY, useQueueCount } from '@/lib/useQueueCount'

enableAutoUnmount(afterEach)

describe('queueCountTexts', () => {
  it('tells that nobody waits', () => {
    expect(queueCountTexts(0)).toEqual({
      title: 'Nobody waits in the queue right now',
      hint: 'Join it: the next player who arrives faces you.',
    })
  })

  it('tells how many players wait, and that the game starts at once', () => {
    expect(queueCountTexts(1).title).toBe('1 player waits in the queue')
    expect(queueCountTexts(3).title).toBe('3 players wait in the queue')
    expect(queueCountTexts(1).hint).toBe('Join now: your game starts at once.')
  })
})

describe('QueueCount', () => {
  it('is live while somebody waits', () => {
    const wrapper = mount(QueueCount, { props: { count: 1 } })

    expect(wrapper.get('[role="status"]').classes()).toContain('live')
    expect(wrapper.text()).toContain('1 player waits in the queue')
  })

  it('is quiet when nobody waits', () => {
    const wrapper = mount(QueueCount, { props: { count: 0 } })

    expect(wrapper.get('[role="status"]').classes()).not.toContain('live')
  })

  it('is a line above the button', () => {
    const wrapper = mount(QueueCount, { props: { count: 2, inline: true } })

    expect(wrapper.get('[role="status"]').classes()).toContain('inline')
    expect(wrapper.text()).toContain('2 players wait in the queue · Join now')
  })
})

describe('useQueueCount', () => {
  const fetchMock = vi.fn<typeof fetch>()

  const Probe = defineComponent({
    setup() {
      const count = useQueueCount()
      return () => h('span', count.value === null ? 'unknown' : String(count.value))
    },
  })

  beforeEach(() => {
    vi.useFakeTimers()
    session.value = {
      userId: 'john',
      name: 'Hida Takeshi',
      username: 'john',
      preferredClan: 'crab',
      cardDisplay: 'drawn',
      theme: 'auto',
      token: 'l5r_a',
    }
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
    vi.useRealTimers()
    session.value = null
  })

  it('reads the count at once, then again', async () => {
    fetchMock.mockResolvedValueOnce(Response.json({ waiting: 0 })).mockResolvedValueOnce(Response.json({ waiting: 1 }))

    const wrapper = mount(Probe)
    await flushPromises()

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/queue/count')
    expect(wrapper.text()).toBe('0')

    await vi.advanceTimersByTimeAsync(REFRESH_DELAY)
    await flushPromises()

    expect(wrapper.text()).toBe('1')
  })

  it('keeps the count read before when it cannot be read again', async () => {
    fetchMock
      .mockResolvedValueOnce(Response.json({ waiting: 1 }))
      .mockResolvedValueOnce(new Response(null, { status: 503 }))

    const wrapper = mount(Probe)
    await flushPromises()
    await vi.advanceTimersByTimeAsync(REFRESH_DELAY)
    await flushPromises()

    expect(wrapper.text()).toBe('1')
  })
})
