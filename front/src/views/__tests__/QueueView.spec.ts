import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import { inverted } from '@/lib/page'
import { found, waiting } from '@/lib/queueing'
import { session } from '@/lib/session'
import QueueView from '@/views/QueueView.vue'

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
  {
    id: 'deck-1',
    name: 'Crab starter deck',
    owner_id: 'john',
    cards: { '01-shiro-nishiyama': 1 },
    stronghold: '01-shiro-nishiyama',
    clan: 'crab',
  },
  {
    id: 'crab-starter-deck',
    name: 'crab-starter-deck',
    owner_id: null,
    cards: { '01-shiro-nishiyama': 1 },
    stronghold: '01-shiro-nishiyama',
    clan: 'crab',
  },
  {
    id: 'crane-starter-deck',
    name: 'crane-starter-deck',
    owner_id: null,
    cards: { '01-shizuka-toshi': 1 },
    stronghold: '01-shizuka-toshi',
    clan: 'crane',
  },
]

async function mountQueue(path = '/queue') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'landing', component: { template: '<div />' } },
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
  /** the games of the user once they have joined the queue */
  let gamesOnceJoined: unknown[] | null
  /** the answer to a deck imported */
  let importAnswer: () => Response

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
    queue = []
    games = []
    gamesOnceJoined = null
    waiting.value = null
    found.value = null
    importAnswer = () =>
      json(201, {
        id: 'deck-9',
        name: 'Moist Crane Duel',
        owner_id: 'john',
        cards: { '01-shizuka-toshi': 1 },
        stronghold: '01-shizuka-toshi',
        clan: 'crane',
      })
    vi.stubGlobal('fetch', fetchMock)
    fetchMock.mockImplementation(async (input, init) => {
      const path = String(input)
      if (path === '/api/self/subscription') {
        return json(200, { hub: 'http://hub.test/', topic: '/users/john', expires_at: '' })
      }
      if (path === '/api/decks') {
        return init?.method === 'POST' ? importAnswer() : json(200, decks)
      }
      if (path === '/api/decks/deck-1' && init?.method === 'DELETE') {
        return new Response(null, { status: 204 })
      }
      if (path === '/api/self/games') {
        return json(200, games)
      }
      if (path === '/api/queue') {
        if (init?.method === 'POST' && gamesOnceJoined !== null) {
          games = gamesOnceJoined
        }

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
    waiting.value = null
    found.value = null
  })

  it('offers the decks of the user, their own deck of their clan chosen', async () => {
    const { wrapper } = await mountQueue()

    const pressed = wrapper.findAll('button.deck[aria-pressed="true"]')
    expect(pressed).toHaveLength(1)
    expect(pressed[0]!.text()).toContain('Crab starter deck')
    expect(wrapper.findAll('.decks').at(-1)!.text()).toContain('Crane')
    expect(inverted.value).toBe(false)
  })

  it('joins the queue with the deck chosen, then goes home to wait', async () => {
    queue = [() => notQueued(), () => json(200, { deck_id: 'crane-starter-deck' })]
    const { wrapper, router } = await mountQueue()

    const crane = wrapper.findAll('button.deck').find((button) => button.text().includes('Crane'))!
    await crane.trigger('click')
    await wrapper.find('.actions .btn-primary').trigger('click')
    await flushPromises()

    const post = fetchMock.mock.calls.find(([, init]) => init?.method === 'POST')!
    expect(post[1]!.body).toBe(JSON.stringify({ deck_id: 'crane-starter-deck' }))
    expect(waiting.value).toMatchObject({
      userId: 'john',
      deckId: 'crane-starter-deck',
      deckName: 'Crane',
      starter: true,
    })
    expect(waiting.value!.friendly).toBeNull()
    expect(router.currentRoute.value.fullPath).toBe('/')
    expect(inverted.value).toBe(false)
  })

  it('sends an account already waiting home', async () => {
    queue = [() => json(200, { deck_id: 'deck-1' })]
    const { router } = await mountQueue()

    expect(waiting.value).toMatchObject({ deckId: 'deck-1', deckName: 'Crab starter deck', starter: false })
    expect(router.currentRoute.value.fullPath).toBe('/')
  })

  it('finds the last game when the queue matched the user at once', async () => {
    gamesOnceJoined = [{ id: 'game-2', player_id: 'player-2', version: 3, ended: false, players: ['jane', 'john'] }]
    const { wrapper } = await mountQueue()

    await wrapper.find('.actions .btn-primary').trigger('click')
    await flushPromises()

    expect(found.value).toBe('game-2')
    expect(waiting.value).toBeNull()
  })

  it('offers no game while a game of the user is in progress', async () => {
    games = [{ id: 'game-2', player_id: 'player-2', version: 3, ended: false, players: ['jane', 'john'] }]
    const { wrapper } = await mountQueue()

    expect(wrapper.find('.actions .btn-primary').attributes('disabled')).toBeDefined()
    expect(wrapper.find('.actions .bot').attributes('disabled')).toBeDefined()
    expect(wrapper.find('.in-progress').text()).toContain('Your game is still in progress')
  })

  it('makes a guest wait in reverse, then leave the queue', async () => {
    session.value = { ...session.value!, userId: 'harry', username: null, preferredClan: null }
    queue = [() => notQueued(), () => json(200, { deck_id: 'deck-1' })]
    const { wrapper, router } = await mountQueue('/queue?code=KX7-QM4-T9P')

    await wrapper.find('.actions .btn-primary').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/queue?code=KX7-QM4-T9P')
    expect(wrapper.find('.waiting').text()).toContain('Waiting for Doji Hayaku')
    expect(wrapper.find('.waiting').text()).toContain('Crab starter deck')
    expect(inverted.value).toBe(true)

    await wrapper.find('.waiting .btn-ghost').trigger('click')
    await flushPromises()

    expect(fetchMock.mock.calls.some(([path, init]) => path === '/api/queue' && init?.method === 'DELETE')).toBe(true)
    expect(wrapper.find('.queue').exists()).toBe(true)
    expect(inverted.value).toBe(false)
  })

  it('enters the queue with the invitation of the host, then waits for their bot at home', async () => {
    queue = [() => notQueued(), () => json(200, { deck_id: 'deck-1' })]
    const { wrapper, router } = await mountQueue('/queue?invitation=invitation-1')

    expect(wrapper.find('.queue').text()).toContain("You face Hida Takeshi's bot, and nobody else.")
    await wrapper.find('.actions .btn-primary').trigger('click')
    await flushPromises()

    const post = fetchMock.mock.calls.find(([path, init]) => path === '/api/queue' && init?.method === 'POST')!
    expect(post[1]!.body).toBe(JSON.stringify({ deck_id: 'deck-1', invitation_id: 'invitation-1' }))
    expect(waiting.value!.friendly).toEqual({
      kind: 'bot',
      botName: "Hida Takeshi's bot",
      expiresAt: '2099-01-01T00:00:00+00:00',
    })
    expect(router.currentRoute.value.fullPath).toBe('/')
  })

  it('enters the queue with the code of a friend', async () => {
    queue = [() => notQueued(), () => json(200, { deck_id: 'deck-1' })]
    const { wrapper } = await mountQueue('/queue?code=KX7-QM4-T9P')

    await wrapper.find('.actions .btn-primary').trigger('click')
    await flushPromises()

    const post = fetchMock.mock.calls.find(([path, init]) => path === '/api/queue' && init?.method === 'POST')!
    expect(post[1]!.body).toBe(JSON.stringify({ deck_id: 'deck-1', invitation_code: 'KX7-QM4-T9P' }))
    expect(waiting.value!.friendly).toEqual({ kind: 'host', hostName: 'Doji Hayaku' })
  })

  it('sends the host of another invitation back to their invitations', async () => {
    const { router } = await mountQueue('/queue?invitation=invitation-0')

    expect(router.currentRoute.value.fullPath).toBe('/invite')
  })

  it('imports a deck from EmeraldDB, which is chosen, then joins the queue with it', async () => {
    queue = [() => notQueued(), () => json(200, { deck_id: 'deck-9' })]
    const { wrapper } = await mountQueue()

    await wrapper.find('#deck-url').setValue(' https://www.emeralddb.org/decks/d95c6eda ')
    await wrapper.find('form.import').trigger('submit')
    await flushPromises()

    const post = fetchMock.mock.calls.find(([path, init]) => path === '/api/decks' && init?.method === 'POST')!
    expect(post[1]!.body).toBe(JSON.stringify({ url: 'https://www.emeralddb.org/decks/d95c6eda' }))
    const mine = wrapper.findAll('.mine button.deck')
    expect(mine.map((button) => button.text())).toEqual([
      expect.stringContaining('Moist Crane Duel'),
      expect.stringContaining('Crab starter deck'),
    ])
    expect(mine[0]!.attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('.notice').text()).toContain('Moist Crane Duel imported from EmeraldDB')
    expect((wrapper.find('#deck-url').element as HTMLInputElement).value).toBe('')

    await wrapper.find('.actions .btn-primary').trigger('click')
    await flushPromises()
    const join = fetchMock.mock.calls.find(([path, init]) => path === '/api/queue' && init?.method === 'POST')!
    expect(join[1]!.body).toBe(JSON.stringify({ deck_id: 'deck-9' }))
  })

  it('tells why a deck cannot be imported, and keeps the URL', async () => {
    importAnswer = () =>
      json(422, {
        type: 'invalid_deck',
        detail: 'The deck cannot be imported.',
        violations: [{ property_path: 'cards[ashen-flame-plateau]', message: 'Unknown card.' }],
      })
    const { wrapper } = await mountQueue()

    await wrapper.find('#deck-url').setValue('https://www.emeralddb.org/decks/75ffc2ba')
    await wrapper.find('form.import').trigger('submit')
    await flushPromises()

    expect(wrapper.find('#import-problem').text()).toContain('not those of Emerald Legacy yet')
    expect(wrapper.find('#import-problem').text()).toContain('ashen-flame-plateau')
    expect(wrapper.find('#deck-url').attributes('aria-invalid')).toBe('true')
    expect((wrapper.find('#deck-url').element as HTMLInputElement).value).toBe(
      'https://www.emeralddb.org/decks/75ffc2ba',
    )
    expect(wrapper.findAll('.mine button.deck')).toHaveLength(1)
  })

  it('chooses a deck the user already has instead of importing it again', async () => {
    importAnswer = () => json(200, decks[0])
    const { wrapper } = await mountQueue()
    await wrapper.findAll('.grid')[1]!.find('button.deck').trigger('click')

    await wrapper.find('#deck-url').setValue('https://www.emeralddb.org/decks/d95c6eda')
    await wrapper.find('form.import').trigger('submit')
    await flushPromises()

    const mine = wrapper.findAll('.mine button.deck')
    expect(mine).toHaveLength(1)
    expect(mine[0]!.attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('.notice').text()).toBe('You already have Crab starter deck, chosen.')
  })

  it('deletes a deck of the user once confirmed, and the user chooses another', async () => {
    const { wrapper } = await mountQueue()
    expect(wrapper.find('.mine button.deck').attributes('aria-pressed')).toBe('true')
    expect(wrapper.findAll('.grid')[1]!.find('.trash').exists()).toBe(false)

    await wrapper.find('.mine .trash').trigger('click')
    expect(wrapper.find('.mine .confirm').text()).toContain('Delete Crab starter deck?')
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'DELETE')).toBe(false)
    await wrapper.find('.mine .confirm .btn-seal').trigger('click')
    await flushPromises()

    expect(fetchMock.mock.calls.some(([path, init]) => path === '/api/decks/deck-1' && init?.method === 'DELETE')).toBe(
      true,
    )
    expect(wrapper.findAll('.mine button.deck')).toHaveLength(0)
    expect(wrapper.find('.notice').text()).toBe('Crab starter deck deleted.')
    expect(wrapper.findAll('button.deck').every((deck) => deck.attributes('aria-pressed') === 'false')).toBe(true)
    expect(wrapper.find('.actions .btn-primary').attributes('disabled')).toBeDefined()
  })

  it('keeps the deck when the deletion is not confirmed', async () => {
    const { wrapper } = await mountQueue()

    await wrapper.find('.mine .trash').trigger('click')
    await wrapper.find('.mine .confirm .btn-link').trigger('click')

    expect(wrapper.find('.mine .confirm').exists()).toBe(false)
    expect(wrapper.findAll('.mine button.deck')).toHaveLength(1)
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'DELETE')).toBe(false)
  })

  it('offers the import in the queue of a friendly game too', async () => {
    const { wrapper } = await mountQueue('/queue?code=KX7-QM4-T9P')

    expect(wrapper.find('form.import').exists()).toBe(true)
    expect(wrapper.find('form.import button').attributes('disabled')).toBeDefined()
  })
})
