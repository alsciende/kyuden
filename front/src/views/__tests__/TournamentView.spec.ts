import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import type { Tournament, TournamentEntry, TournamentMatch, TournamentStatus } from '@/api/client'
import { waiting } from '@/lib/queueing'
import { session } from '@/lib/session'
import { tournamentEntries } from '@/lib/tournamentEntries'
import TournamentView from '@/views/TournamentView.vue'

const john = { user_id: 'john', name: 'John', banner: { mon: 'enso', pattern: 'none' } } as const

function tournament(status: TournamentStatus): Tournament {
  return {
    id: 't1',
    starts_at: '2099-11-20T20:00:00+00:00',
    locks_at: '2099-11-20T19:00:00+00:00',
    status,
    format: 'single_elimination',
    organizer: { ...john, user_id: 'jane', name: 'Jane' },
    max_entrants: 64,
    players: [john],
    size: null,
    round_count: null,
    current_round: 0,
    join_deadline: null,
    winner: null,
    rounds: [],
  }
}

const jane = { ...john, user_id: 'jane', name: 'Jane' }

/** a tournament of 2 players, john and jane, its final begun at 20:00 */
function final(match: Partial<TournamentMatch> = {}): Tournament {
  return {
    ...tournament('in_progress'),
    players: [john, jane],
    size: 2,
    round_count: 1,
    current_round: 1,
    join_deadline: '2099-11-20T20:10:00+00:00',
    rounds: [
      {
        number: 1,
        matches: [
          {
            id: 7,
            table: 1,
            players: [john, jane],
            game_id: null,
            winner_user_id: null,
            outcome: null,
            victory_condition: null,
            waiting: [],
            ...match,
          },
        ],
      },
    ],
  }
}

function entry(current: Tournament): TournamentEntry {
  const { id, starts_at, locks_at, status, format, organizer, max_entrants, players } = current
  return {
    tournament: { id, starts_at, locks_at, status, format, organizer, max_entrants, entrants: players.length },
    deck_id: 'd1',
    deck_name: 'My Lions',
    registered_at: '2099-11-18T12:00:00+00:00',
    eliminated: false,
  }
}

async function mountTournament(current: Tournament, registered: boolean) {
  const fetch = vi.fn(async (path: string, init?: RequestInit) => {
    if (init?.method === 'POST') {
      return new Response(null, { status: 204 })
    }
    const body =
      path === '/api/tournaments/t1'
        ? current
        : path === '/api/self/tournaments'
          ? registered
            ? [entry(current)]
            : []
          : path === '/api/queue'
            ? { deck_id: 'd1', match_id: 7 }
            : []
    return new Response(JSON.stringify(body), { status: 200 })
  })
  vi.stubGlobal('fetch', fetch)
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/tournaments/:id', name: 'tournament', component: TournamentView },
      { path: '/games/:id', name: 'game', component: { template: '<div />' } },
      ...['tournaments', 'signup', 'login', 'invite'].map((name) => ({
        path: `/${name}`,
        name,
        component: { template: '<div />' },
      })),
    ],
  })
  await router.push('/tournaments/t1')
  const wrapper = mount(TournamentView, { global: { plugins: [router] } })
  await flushPromises()

  return { wrapper, fetch }
}

describe('TournamentView', () => {
  enableAutoUnmount(afterEach)

  beforeEach(() => {
    session.value = {
      userId: 'john',
      name: 'John',
      username: 'john',
      preferredClan: 'lion',
      cardDisplay: 'drawn',
      theme: 'auto',
      tutorial: true,
      seenTips: [],
      token: 'l5r_a',
    }
    tournamentEntries.value = []
  })

  afterEach(() => {
    session.value = null
    waiting.value = null
    vi.unstubAllGlobals()
  })

  it('offers a registered player to add the tournament to their calendar', async () => {
    const { wrapper } = await mountTournament(tournament('open'), true)

    const link = wrapper.find('a.calendar')
    expect(link.attributes('href')).toBe('/api/tournaments/t1/calendar.ics')
    expect(link.attributes('download')).toBeDefined()
  })

  it('offers no calendar to a player not registered, nor once the tournament is cancelled', async () => {
    expect((await mountTournament(tournament('open'), false)).wrapper.find('a.calendar').exists()).toBe(false)
    expect((await mountTournament(tournament('cancelled'), true)).wrapper.find('a.calendar').exists()).toBe(false)
  })

  it('reads the tournament again once the player unregisters elsewhere (the alert of a cancelled tournament)', async () => {
    const cancelled = tournament('cancelled')
    const { wrapper } = await mountTournament(cancelled, true)
    expect(wrapper.find('.players li').text()).toContain('(you)')

    cancelled.players = []
    tournamentEntries.value = []
    await flushPromises()

    expect(wrapper.find('.players li').exists()).toBe(false)
    expect(wrapper.find('.players').text()).toContain('Nobody has registered yet.')
  })

  it('tells the bracket and its byes once locked', async () => {
    const locked = {
      ...tournament('locked'),
      players: [john, jane, { ...john, user_id: 'kaede', name: 'Kaede' }],
      size: 4,
    }
    const { wrapper } = await mountTournament(locked, true)

    expect(wrapper.find('#players-title').text()).toBe('3 players, bracket of 4')
    expect(wrapper.text()).toContain('1 bye, drawn with the pairings')
    expect(wrapper.text()).toContain('You will have 10 minutes.')
  })

  it('offers the player to join the queue with their match, the time left told', async () => {
    vi.useFakeTimers({ now: Date.parse('2099-11-20T20:01:36Z'), toFake: ['Date'] })
    const { wrapper, fetch } = await mountTournament(final(), true)

    expect(wrapper.find('#match-title').text()).toBe('You face Jane')
    expect(wrapper.find('[role="timer"]').text()).toContain('8:24')
    await wrapper.find('.match button.btn-primary').trigger('click')
    await flushPromises()

    const joined = fetch.mock.calls.find(([path, init]) => path === '/api/queue' && init?.method === 'POST')
    expect(JSON.parse(String(joined?.[1]?.body))).toEqual({ match_id: 7 })
    vi.useRealTimers()
  })

  it('links the game of a match in progress, to watch it', async () => {
    const { wrapper } = await mountTournament(final({ game_id: 'g1' }), false)

    expect(wrapper.find('.rounds a').attributes('href')).toBe('/games/g1')
    expect(wrapper.find('#match-title').exists()).toBe(false)
  })

  it('tells the winner once the tournament has ended', async () => {
    const ended = {
      ...final({ winner_user_id: 'jane', outcome: 'game', victory_condition: 'concession' }),
      status: 'ended' as const,
      winner: jane,
    }
    const { wrapper } = await mountTournament(ended, true)

    expect(wrapper.text()).toContain('Jane has won the tournament')
    expect(wrapper.find('.rounds .match').text()).toContain('won, concession')
    expect(wrapper.find('.rounds .lost').text()).toContain('John')
  })
})
