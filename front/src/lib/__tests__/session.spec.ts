import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { CLANS } from '@/data/clans'
import { loadSession, startIntroductoryGame } from '@/lib/session'

const crab = CLANS[0]!

const botGame = {
  game_id: 'game-1',
  player_id: 'player-1',
  opponent: { user_id: 'kyuden-bot', name: 'Kyūden Bot', deck_id: 'lion-starter-deck' },
}

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

describe('startIntroductoryGame', () => {
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    localStorage.clear()
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
  })

  it('creates a guest with the name at court, then a game with the starter deck of the clan', async () => {
    fetchMock
      .mockResolvedValueOnce(json(201, { user_id: 'guest-1', name: 'Hida Takeshi', token: 'l5r_token' }))
      .mockResolvedValueOnce(json(201, botGame))

    const game = await startIntroductoryGame('Hida Takeshi', crab)

    expect(game).toEqual(botGame)
    expect(fetchMock.mock.calls[0]![0]).toBe('/api/guests')
    expect(JSON.parse(fetchMock.mock.calls[0]![1]!.body as string)).toEqual({ name: 'Hida Takeshi' })
    expect(fetchMock.mock.calls[1]![0]).toBe('/api/bot-games')
    expect(JSON.parse(fetchMock.mock.calls[1]![1]!.body as string)).toEqual({ deck_id: 'crab-starter-deck' })
    expect((fetchMock.mock.calls[1]![1]!.headers as Record<string, string>).Authorization).toBe('Bearer l5r_token')
    expect(loadSession()).toEqual({ userId: 'guest-1', name: 'Hida Takeshi', token: 'l5r_token' })
  })

  it('keeps the guest of the same name', async () => {
    localStorage.setItem('kyuden.session', JSON.stringify({ userId: 'guest-1', name: 'Hida Takeshi', token: 'l5r_a' }))
    fetchMock.mockResolvedValueOnce(json(201, botGame))

    await startIntroductoryGame('Hida Takeshi', crab)

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock.mock.calls[0]![0]).toBe('/api/bot-games')
  })

  it('creates a new guest when the token is no longer valid', async () => {
    localStorage.setItem(
      'kyuden.session',
      JSON.stringify({ userId: 'guest-1', name: 'Hida Takeshi', token: 'l5r_old' }),
    )
    fetchMock
      .mockResolvedValueOnce(json(401, { type: 'unauthorized', detail: 'Invalid credentials.' }))
      .mockResolvedValueOnce(json(201, { user_id: 'guest-2', name: 'Hida Takeshi', token: 'l5r_new' }))
      .mockResolvedValueOnce(json(201, botGame))

    await expect(startIntroductoryGame('Hida Takeshi', crab)).resolves.toEqual(botGame)

    expect(loadSession()?.token).toBe('l5r_new')
  })

  it('reports a problem of the API', async () => {
    localStorage.setItem('kyuden.session', JSON.stringify({ userId: 'guest-1', name: 'Hida Takeshi', token: 'l5r_a' }))
    fetchMock.mockResolvedValueOnce(json(503, { type: 'bot_unavailable', detail: 'No bot can play right now.' }))

    await expect(startIntroductoryGame('Hida Takeshi', crab)).rejects.toMatchObject({
      status: 503,
      type: 'bot_unavailable',
    })
  })
})
