import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import type { Tournament, TournamentEntry, TournamentStatus } from '@/api/client'
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
  }
}

function entry(current: Tournament): TournamentEntry {
  const { players, ...summary } = current
  return {
    tournament: { ...summary, entrants: players.length },
    deck_id: 'd1',
    deck_name: 'My Lions',
    registered_at: '2099-11-18T12:00:00+00:00',
  }
}

async function mountTournament(current: Tournament, registered: boolean) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (path: string) => {
      const body =
        path === '/api/tournaments/t1'
          ? current
          : path === '/api/self/tournaments'
            ? registered
              ? [entry(current)]
              : []
            : []
      return new Response(JSON.stringify(body), { status: 200 })
    }),
  )
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/tournaments/:id', name: 'tournament', component: TournamentView },
      ...['tournaments', 'signup', 'login'].map((name) => ({
        path: `/${name}`,
        name,
        component: { template: '<div />' },
      })),
    ],
  })
  await router.push('/tournaments/t1')
  const wrapper = mount(TournamentView, { global: { plugins: [router] } })
  await flushPromises()

  return wrapper
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
    vi.unstubAllGlobals()
  })

  it('offers a registered player to add the tournament to their calendar', async () => {
    const wrapper = await mountTournament(tournament('open'), true)

    const link = wrapper.find('a.calendar')
    expect(link.attributes('href')).toBe('/api/tournaments/t1/calendar.ics')
    expect(link.attributes('download')).toBeDefined()
  })

  it('offers no calendar to a player not registered, nor once the tournament is cancelled', async () => {
    expect((await mountTournament(tournament('open'), false)).find('a.calendar').exists()).toBe(false)
    expect((await mountTournament(tournament('cancelled'), true)).find('a.calendar').exists()).toBe(false)
  })
})
