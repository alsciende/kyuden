import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import GameInProgressBanner from '@/components/GameInProgressBanner.vue'
import { inverted } from '@/lib/page'
import { session } from '@/lib/session'

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const userGame = (id: string, ended: boolean) => ({
  id,
  player_id: `${id}-john`,
  version: 12,
  ended,
  players: ['john', 'kyuden-bot'],
})

const gameView = {
  players: [
    { user_id: 'john', name: 'Hida Takeshi' },
    { user_id: 'kyuden-bot', name: 'Kyūden Bot' },
  ],
}

async function mountBanner() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'landing', component: { template: '<div />' } },
      { path: '/account', name: 'account', component: { template: '<div />' } },
      { path: '/games/:id', name: 'game', component: { template: '<div />' } },
    ],
  })
  await router.push('/')
  const wrapper = mount(GameInProgressBanner, { global: { plugins: [router] } })
  await flushPromises()

  return { wrapper, router }
}

describe('GameInProgressBanner', () => {
  enableAutoUnmount(afterEach)
  const fetchMock = vi.fn<typeof fetch>()
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
    games = []
    vi.stubGlobal('fetch', fetchMock)
    fetchMock.mockImplementation((input) =>
      Promise.resolve(String(input) === '/api/self/games' ? json(200, games) : json(200, gameView)),
    )
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
    session.value = null
    inverted.value = false
  })

  it('offers to rejoin the last game in progress, against the opponent named', async () => {
    games = [userGame('game-2', true), userGame('game-1', false)]

    const { wrapper } = await mountBanner()

    expect(wrapper.text()).toContain('Your game against Kyūden Bot is still in progress.')
    expect(wrapper.get('a').attributes('href')).toBe('/games/game-1')
    expect(fetchMock.mock.calls.map(([input]) => String(input))).toContain('/api/games/game-1')
  })

  it('shows nothing when every game has ended', async () => {
    games = [userGame('game-1', true)]

    const { wrapper } = await mountBanner()

    expect(wrapper.find('aside').exists()).toBe(false)
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('shows nothing without a session', async () => {
    session.value = null

    const { wrapper } = await mountBanner()

    expect(wrapper.find('aside').exists()).toBe(false)
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('reads the games again on another page, once the game has ended', async () => {
    games = [userGame('game-1', false)]
    const { wrapper, router } = await mountBanner()
    expect(wrapper.find('aside').exists()).toBe(true)

    games = [userGame('game-1', true)]
    await router.push('/account')
    await flushPromises()

    expect(wrapper.find('aside').exists()).toBe(false)
  })

  it('reads the games of the account the player signs in to', async () => {
    session.value = null
    games = [userGame('game-1', false)]
    const { wrapper } = await mountBanner()

    session.value = {
      userId: 'john',
      name: 'Hida Takeshi',
      username: 'john',
      preferredClan: 'crab',
      cardDisplay: 'drawn',
      theme: 'auto',
      token: 'l5r_b',
    }
    await flushPromises()

    expect(wrapper.find('aside').exists()).toBe(true)
  })

  it('is not shown on the dark screen of the queue', async () => {
    games = [userGame('game-1', false)]
    inverted.value = true

    const { wrapper } = await mountBanner()

    expect(wrapper.find('aside').exists()).toBe(false)
  })
})
