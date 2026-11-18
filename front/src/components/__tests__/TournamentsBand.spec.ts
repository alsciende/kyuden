import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import type { TournamentStatus } from '@/api/client'
import TournamentsBand from '@/components/tournaments/TournamentsBand.vue'

function tournament(id: string, status: TournamentStatus) {
  return {
    id,
    starts_at: '2026-11-21T19:00:00Z',
    locks_at: '2026-11-21T18:00:00Z',
    status,
    format: 'single_elimination',
    organizer: { user_id: 'tsukune', name: 'Shiba Tsukune', banner: { mon: 'enso', pattern: 'none' } },
    entrants: 0,
    max_entrants: 64,
  }
}

async function mountBand(tournaments: unknown[]) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async () => new Response(JSON.stringify(tournaments), { status: 200 })),
  )
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/tournaments', name: 'tournaments', component: { template: '<div />' } }],
  })
  const wrapper = mount(TournamentsBand, { global: { plugins: [router] } })
  await flushPromises()

  return wrapper
}

describe('TournamentsBand', () => {
  enableAutoUnmount(afterEach)

  it('counts the tournaments in progress, upcoming and cancelled', async () => {
    const wrapper = await mountBand([
      tournament('running', 'in_progress'),
      tournament('locked', 'locked'),
      tournament('open', 'open'),
      tournament('called-off', 'cancelled'),
    ])

    expect(wrapper.findAll('.count').map((count) => count.text())).toEqual(['in progress1', 'upcoming2', 'cancelled1'])
  })

  it('stays hidden while no tournament is in progress nor upcoming', async () => {
    const wrapper = await mountBand([tournament('called-off', 'cancelled')])

    expect(wrapper.find('.band').exists()).toBe(false)
  })
})
