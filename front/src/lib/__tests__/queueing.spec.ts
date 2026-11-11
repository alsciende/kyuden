import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { checkQueue, found, leave, startWaiting, waiting, waitingTexts, type Waiting } from '@/lib/queueing'

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const notQueued = () => json(404, { type: 'not_queued', detail: 'The user is not in the queue.' })

const wait: Waiting = {
  userId: 'john',
  deckId: 'lion-starter-deck',
  deckName: 'Lion',
  starter: true,
  since: 0,
  friendly: null,
}

describe('waitingTexts', () => {
  it('tells the wait for an opponent of the queue', () => {
    expect(waitingTexts(wait, 102_000)).toEqual({
      eyebrow: 'In the queue · 1:42',
      title: 'Waiting for an opponent',
      deck: 'Lion starter deck',
      opens: 'The game opens as soon as another player joins.',
      expiry: null,
      leave: 'Leave the queue',
    })
  })

  it('tells the wait of the host for their bot, and when the invitation expires', () => {
    const texts = waitingTexts(
      {
        ...wait,
        deckName: 'Moist Crane Duel',
        starter: false,
        friendly: { kind: 'bot', botName: null, expiresAt: new Date(3_600_000).toISOString() },
      },
      0,
    )

    expect(texts.eyebrow).toBe('A friendly game · In the queue · 0:00')
    expect(texts.title).toBe('Waiting for your bot')
    expect(texts.deck).toBe('Moist Crane Duel')
    expect(texts.opens).toBe('The game opens as soon as your bot enters the queue with its key.')
    expect(texts.expiry).toMatch(/^The invitation expires in .+ if nobody comes\.$/)
    expect(texts.leave).toBe('Leave the queue and cancel the invitation')
  })

  it('tells the wait of a friend for the host', () => {
    const texts = waitingTexts({ ...wait, deckName: null, friendly: { kind: 'host', hostName: 'Doji Hayaku' } }, 0)

    expect(texts.title).toBe('Waiting for Doji Hayaku')
    expect(texts.deck).toBeNull()
    expect(texts.opens).toBe('The game opens as soon as Doji Hayaku enters the queue.')
    expect(texts.expiry).toBeNull()
    expect(texts.leave).toBe('Leave the queue')
  })
})

describe('checkQueue', () => {
  const fetchMock = vi.fn<typeof fetch>()
  let queue: () => Response
  let games: unknown[]

  beforeEach(() => {
    waiting.value = null
    found.value = null
    queue = notQueued
    games = []
    vi.stubGlobal('fetch', fetchMock)
    fetchMock.mockImplementation(async (input, init) => {
      const path = String(input)
      if (path === '/api/queue') {
        return init?.method === 'DELETE' ? notQueued() : queue()
      }
      if (path === '/api/self/games') {
        return json(200, games)
      }
      if (path === '/api/decks') {
        return json(200, [
          {
            id: 'lion-starter-deck',
            name: 'lion-starter-deck',
            owner_id: null,
            cards: { '01-shiro-no-yogasha': 1 },
            stronghold: '01-shiro-no-yogasha',
            clan: 'lion',
          },
        ])
      }

      return json(404, {})
    })
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
    waiting.value = null
    found.value = null
  })

  it('reads a wait the tab did not know, with the name of its deck', async () => {
    queue = () => json(200, { deck_id: 'lion-starter-deck' })

    await checkQueue('l5r_a', 'john')

    expect(waiting.value).toMatchObject({ userId: 'john', deckId: 'lion-starter-deck', starter: true, friendly: null })
  })

  it('keeps the wait the tab knows', async () => {
    queue = () => json(200, { deck_id: 'lion-starter-deck' })
    waiting.value = { ...wait, friendly: { kind: 'host', hostName: 'Doji Hayaku' } }

    await checkQueue('l5r_a', 'john')

    expect(waiting.value).toMatchObject({ since: 0, friendly: { kind: 'host', hostName: 'Doji Hayaku' } })
    expect(fetchMock.mock.calls.some(([path]) => path === '/api/decks')).toBe(false)
  })

  it('finds the game once the queue has matched the user', async () => {
    waiting.value = wait
    games = [
      { id: 'game-1', ended: true },
      { id: 'game-2', ended: false },
    ]

    await checkQueue('l5r_a', 'john')

    expect(waiting.value).toBeNull()
    expect(found.value).toBe('game-2')
  })

  it('forgets the wait the user left elsewhere', async () => {
    waiting.value = wait

    await checkQueue('l5r_a', 'john')

    expect(waiting.value).toBeNull()
    expect(found.value).toBeNull()
  })

  it('forgets the wait of another user', async () => {
    waiting.value = { ...wait, userId: 'jane' }

    await checkQueue('l5r_a', 'john')

    expect(waiting.value).toBeNull()
    expect(fetchMock.mock.calls.some(([path]) => path === '/api/self/games')).toBe(false)
  })

  it('leaves a wait begun while the queue was read to the next read', async () => {
    let answer: (response: Response) => void = () => undefined
    fetchMock.mockImplementationOnce(() => new Promise((resolve) => (answer = resolve)))
    const reading = checkQueue('l5r_a', 'john')

    startWaiting('john', { id: 'lion-starter-deck', name: 'Lion', starter: true }, null)
    answer(notQueued())
    await reading

    expect(waiting.value).toMatchObject({ deckId: 'lion-starter-deck' })
  })

  it('finds the game when the user was matched as they left', async () => {
    waiting.value = wait
    games = [{ id: 'game-2', ended: false }]

    await leave('l5r_a', 'john')

    expect(found.value).toBe('game-2')
  })
})
