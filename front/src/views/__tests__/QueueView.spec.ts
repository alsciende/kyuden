import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import { inverted } from '@/lib/page'
import { session } from '@/lib/session'
import QueueView from '@/views/QueueView.vue'

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

const invitation = {
  id: 'invitation-1',
  kind: 'bot',
  code: null,
  bot: { user_id: 'bot-1', name: "Hida Takeshi's bot" },
  token: null,
  expires_at: '2099-01-01T00:00:00+00:00',
}

const notQueued = () => json(404, { type: 'not_queued', detail: 'The user is not in the queue.' })

const decks = [
  { id: 'deck-1', name: 'Crab starter deck', owner_id: 'john', cards: { '01-shiro-nishiyama': 1 } },
  { id: 'crab-starter-deck', name: 'crab-starter-deck', owner_id: null, cards: { '01-shiro-nishiyama': 1 } },
  { id: 'crane-starter-deck', name: 'crane-starter-deck', owner_id: null, cards: { '01-shizuka-toshi': 1 } },
]

async function mountQueue(path = '/queue') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/queue', name: 'queue', component: QueueView },
      { path: '/invite', name: 'invite', component: { template: '<div />' } },
      { path: '/join/:code', name: 'join', component: { template: '<div />' } },
      { path: '/games/:id', name: 'game', component: { template: '<div />' } },
    ],
  })
  await router.push(path)
  const wrapper = mount(QueueView, { global: { plugins: [router] } })
  await flushPromises()

  return { wrapper, router }
}

describe('QueueView', () => {
  enableAutoUnmount(afterEach)
  const fetchMock = vi.fn<typeof fetch>()
  /** the answers of the queue, in order; the decks, the cards and the subscription are always answered */
  let queue: (() => Response)[]
  let games: unknown[]

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
    FakeEventSource.opened = []
    queue = []
    games = []
    vi.stubGlobal('EventSource', FakeEventSource)
    vi.stubGlobal('fetch', fetchMock)
    fetchMock.mockImplementation(async (input, init) => {
      const path = String(input)
      if (path === '/api/self/subscription') {
        return json(200, { hub: 'http://hub.test/', token: 'jwt', topic: '/users/john', expires_at: '' })
      }
      if (path === '/api/decks') {
        return json(200, decks)
      }
      if (path === '/api/self/games') {
        return json(200, games)
      }
      if (path === '/api/queue') {
        return init?.method === 'POST' || init?.method === 'DELETE'
          ? new Response(null, { status: 204 })
          : (queue.shift() ?? notQueued)()
      }
      if (path === '/api/self/invitation') {
        return init?.method === 'DELETE' ? new Response(null, { status: 204 }) : json(200, invitation)
      }
      if (path === '/api/invitations/KX7-QM4-T9P') {
        return json(200, { host_name: 'Doji Hayaku', state: 'pending' })
      }
      if (path.startsWith('/api/cards/')) {
        return json(200, { code: path.slice(11), name: 'Shiro Nishiyama', type: 'stronghold', image_url: null })
      }

      return json(404, {})
    })
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
    inverted.value = false
  })

  it('offers the decks of the user, their own deck of their clan chosen', async () => {
    const { wrapper } = await mountQueue()

    const pressed = wrapper.findAll('button.deck[aria-pressed="true"]')
    expect(pressed).toHaveLength(1)
    expect(pressed[0]!.text()).toContain('Crab starter deck')
    expect(wrapper.findAll('.decks').at(-1)!.text()).toContain('Crane')
    expect(inverted.value).toBe(false)
  })

  it('joins the queue with the deck chosen, then waits in reverse', async () => {
    queue = [() => notQueued(), () => json(200, { deck_id: 'crane-starter-deck' })]
    const { wrapper } = await mountQueue()

    const crane = wrapper.findAll('button.deck').find((button) => button.text().includes('Crane'))!
    await crane.trigger('click')
    await wrapper.find('.actions .btn-primary').trigger('click')
    await flushPromises()

    const post = fetchMock.mock.calls.find(([, init]) => init?.method === 'POST')!
    expect(post[1]!.body).toBe(JSON.stringify({ deck_id: 'crane-starter-deck' }))
    expect(wrapper.find('.waiting').text()).toContain('Waiting for an opponent')
    expect(wrapper.find('.waiting').text()).toContain('Crane starter deck')
    expect(inverted.value).toBe(true)
  })

  it('opens the game the hub tells about', async () => {
    queue = [() => json(200, { deck_id: 'deck-1' })]
    const { wrapper, router } = await mountQueue()
    expect(wrapper.find('.waiting').exists()).toBe(true)

    expect(FakeEventSource.opened[0]!.url.searchParams.getAll('topic')).toEqual(['/users/john'])
    FakeEventSource.opened[0]!.receive({
      event: 'game_started',
      id: 'game-1',
      player_ids: { john: 'player-1', jane: 'player-2' },
    })
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/games/game-1')
  })

  it('opens the last game when the queue matched the user at once', async () => {
    games = [{ id: 'game-2', player_id: 'player-2', version: 3, ended: false, players: ['jane', 'john'] }]
    const { wrapper, router } = await mountQueue()

    await wrapper.find('.actions .btn-primary').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/games/game-2')
  })

  it('leaves the queue', async () => {
    queue = [() => json(200, { deck_id: 'deck-1' })]
    const { wrapper } = await mountQueue()

    await wrapper.find('.waiting .btn-ghost').trigger('click')
    await flushPromises()

    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'DELETE')).toBe(true)
    expect(wrapper.find('.queue').exists()).toBe(true)
    expect(inverted.value).toBe(false)
  })

  it('enters the queue with the invitation of the host, then waits for their bot', async () => {
    queue = [() => notQueued(), () => json(200, { deck_id: 'deck-1' })]
    const { wrapper } = await mountQueue('/queue?invitation=invitation-1')

    expect(wrapper.find('.queue').text()).toContain("You face Hida Takeshi's bot, and nobody else.")
    await wrapper.find('.actions .btn-primary').trigger('click')
    await flushPromises()

    const post = fetchMock.mock.calls.find(([path, init]) => path === '/api/queue' && init?.method === 'POST')!
    expect(post[1]!.body).toBe(JSON.stringify({ deck_id: 'deck-1', invitation_id: 'invitation-1' }))
    expect(wrapper.find('.waiting').text()).toContain('Waiting for your bot')
    expect(wrapper.find('.waiting .btn-ghost').text()).toBe('Leave the queue and cancel the invitation')
  })

  it('goes back to the invitations when leaving the queue cancels the invitation', async () => {
    queue = [() => json(200, { deck_id: 'deck-1' })]
    const { wrapper, router } = await mountQueue('/queue?invitation=invitation-1')

    await wrapper.find('.waiting .btn-ghost').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/invite')
  })

  it('enters the queue with the code of a friend', async () => {
    queue = [() => notQueued(), () => json(200, { deck_id: 'deck-1' })]
    const { wrapper } = await mountQueue('/queue?code=KX7-QM4-T9P')

    await wrapper.find('.actions .btn-primary').trigger('click')
    await flushPromises()

    const post = fetchMock.mock.calls.find(([path, init]) => path === '/api/queue' && init?.method === 'POST')!
    expect(post[1]!.body).toBe(JSON.stringify({ deck_id: 'deck-1', invitation_code: 'KX7-QM4-T9P' }))
    expect(wrapper.find('.waiting').text()).toContain('Waiting for Doji Hayaku')
  })

  it('sends the host of another invitation back to their invitations', async () => {
    const { router } = await mountQueue('/queue?invitation=invitation-0')

    expect(router.currentRoute.value.fullPath).toBe('/invite')
  })
})
