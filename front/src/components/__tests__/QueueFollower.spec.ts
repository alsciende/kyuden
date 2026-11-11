import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import QueueFollower from '@/components/QueueFollower.vue'
import { FOUND_DELAY, found, waiting } from '@/lib/queueing'
import { session } from '@/lib/session'

class FakeEventSource {
  static readonly CLOSED = 2
  static opened: FakeEventSource[] = []

  readonly url: URL
  readyState = 1
  onmessage: ((message: MessageEvent<string>) => void) | null = null
  onerror: (() => void) | null = null

  constructor(url: URL) {
    this.url = url
    FakeEventSource.opened.push(this)
  }

  close(): void {
    this.readyState = FakeEventSource.CLOSED
  }

  receive(data: unknown): void {
    this.onmessage?.(new MessageEvent('message', { data: JSON.stringify(data) }))
  }
}

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

describe('QueueFollower', () => {
  enableAutoUnmount(afterEach)
  const fetchMock = vi.fn<typeof fetch>()

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
    waiting.value = null
    found.value = null
    FakeEventSource.opened = []
    vi.stubGlobal('EventSource', FakeEventSource)
    vi.stubGlobal('fetch', fetchMock)
    fetchMock.mockImplementation(async (input) => {
      const path = String(input)
      if (path === '/api/self/subscription') {
        return json(200, { hub: 'http://hub.test/', topic: '/users/john', expires_at: '' })
      }
      if (path === '/api/queue') {
        return json(200, { deck_id: 'deck-1' })
      }
      if (path === '/api/decks') {
        return json(200, [
          {
            id: 'deck-1',
            name: 'Crab deck',
            owner_id: 'john',
            cards: { '01-shiro-nishiyama': 1 },
            stronghold: '01-shiro-nishiyama',
            clan: 'crab',
          },
        ])
      }

      return json(404, {})
    })
  })

  afterEach(() => {
    vi.useRealTimers()
    fetchMock.mockReset()
    vi.unstubAllGlobals()
    waiting.value = null
    found.value = null
  })

  async function mountFollower() {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/watch', name: 'watch', component: { template: '<div />' } },
        { path: '/games/:id', name: 'game', component: { template: '<div />' } },
      ],
    })
    await router.push('/watch')
    const wrapper = mount(QueueFollower, { global: { plugins: [router] } })
    await flushPromises()

    return { wrapper, router }
  }

  it('reads the wait from the queue, then follows the topic of the user while they wait', async () => {
    await mountFollower()

    expect(waiting.value).toMatchObject({ deckId: 'deck-1', deckName: 'Crab deck' })
    expect(FakeEventSource.opened[0]!.url.searchParams.getAll('match')).toEqual(['/users/john'])
  })

  it('shows the opponent found, then opens the game', async () => {
    const { wrapper, router } = await mountFollower()

    FakeEventSource.opened[0]!.receive({ event: 'game_started', id: 'game-1', player_ids: { john: 'player-1' } })
    await flushPromises()

    expect(wrapper.find('.found').text()).toContain('Opponent found')
    expect(wrapper.find('.found').text()).toContain('Your game opens.')
    expect(waiting.value).toBeNull()
    expect(FakeEventSource.opened[0]!.readyState).toBe(FakeEventSource.CLOSED)
    expect(router.currentRoute.value.fullPath).toBe('/watch')

    await vi.advanceTimersByTimeAsync(FOUND_DELAY)
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/games/game-1')
    expect(wrapper.find('.found').exists()).toBe(false)
  })

  it('forgets the wait once the session is gone', async () => {
    await mountFollower()

    session.value = null
    await flushPromises()

    expect(waiting.value).toBeNull()
    expect(FakeEventSource.opened[0]!.readyState).toBe(FakeEventSource.CLOSED)
  })
})
