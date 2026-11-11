import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import QueueBanner from '@/components/queue/QueueBanner.vue'
import QueueWaiting from '@/components/queue/QueueWaiting.vue'
import { now, waiting, type Waiting } from '@/lib/queueing'
import { session } from '@/lib/session'

const wait: Waiting = {
  userId: 'john',
  deckId: 'lion-starter-deck',
  deckName: 'Lion',
  starter: true,
  since: 0,
  friendly: null,
}

describe('QueueWaiting and QueueBanner', () => {
  enableAutoUnmount(afterEach)
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    session.value = {
      userId: 'john',
      name: 'Hida Takeshi',
      username: 'john',
      preferredClan: 'crab',
      cardDisplay: 'drawn',
      theme: 'auto',
      token: 'l5r_a',
    }
    now.value = 102_000
    vi.stubGlobal('fetch', fetchMock)
    fetchMock.mockResolvedValue(new Response(null, { status: 204 }))
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
    waiting.value = null
  })

  async function makeRouter() {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', name: 'landing', component: { template: '<div />' } },
        { path: '/invite', name: 'invite', component: { template: '<div />' } },
      ],
    })
    await router.push('/')

    return router
  }

  it('tells the wait in the tile of the home, which offers to watch a game meanwhile', async () => {
    const router = await makeRouter()
    const wrapper = mount(QueueWaiting, { props: { waiting: wait, tile: true }, global: { plugins: [router] } })

    expect(wrapper.find('.eyebrow').text()).toBe('Play · In the queue · 1:42')
    expect(wrapper.find('h2').text()).toBe('Waiting for an opponent')
    expect(wrapper.find('.lead').text()).toContain('You play the Lion starter deck.')
    expect(wrapper.find('.meanwhile').text()).toContain('Watch a game meanwhile')
  })

  it('leaves the queue from the banner', async () => {
    waiting.value = wait
    const router = await makeRouter()
    const wrapper = mount(QueueBanner, { props: { waiting: wait }, global: { plugins: [router] } })

    expect(wrapper.find('.banner').text()).toContain('Waiting for an opponent')
    await wrapper.find('.banner .btn').trigger('click')
    await flushPromises()

    expect(fetchMock.mock.calls.some(([path, init]) => path === '/api/queue' && init?.method === 'DELETE')).toBe(true)
    expect(waiting.value).toBeNull()
  })

  it('sends the host back to their invitations once leaving has cancelled it', async () => {
    const hosting: Waiting = { ...wait, friendly: { kind: 'friend', expiresAt: '2099-01-01T00:00:00+00:00' } }
    waiting.value = hosting
    const router = await makeRouter()
    const wrapper = mount(QueueBanner, { props: { waiting: hosting, compact: true }, global: { plugins: [router] } })

    expect(wrapper.find('.compact .btn-link').text()).toBe('Leave the queue and cancel the invitation')
    await wrapper.find('.compact .btn-link').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/invite')
  })
})
