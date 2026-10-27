import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import { newcomer } from '@/lib/newcomer'
import { session } from '@/lib/session'
import JoinView from '@/views/JoinView.vue'

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

async function mountJoin() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'landing', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/queue', name: 'queue', component: { template: '<div />' } },
      { path: '/join/:code', name: 'join', component: JoinView },
    ],
  })
  await router.push('/join/KX7-QM4-T9P')
  const wrapper = mount(JoinView, { global: { plugins: [router] } })
  await flushPromises()

  return { wrapper, router }
}

describe('JoinView', () => {
  enableAutoUnmount(afterEach)
  const fetchMock = vi.fn<typeof fetch>()
  let state: string

  beforeEach(() => {
    session.value = null
    localStorage.clear()
    newcomer.clanId = 'crane'
    state = 'pending'
    vi.stubGlobal('fetch', fetchMock)
    fetchMock.mockImplementation(async (input, init) => {
      const path = String(input)
      if (path === '/api/invitations/KX7-QM4-T9P') {
        return json(200, { host_name: 'Hida Takeshi', state })
      }
      if (path === '/api/guests') {
        const body = JSON.parse(String(init?.body)) as { name: string }

        return json(201, { user_id: 'harry', name: body.name, token: 'l5r_guest' })
      }
      if (path === '/api/queue') {
        return new Response(null, { status: 204 })
      }

      return json(404, {})
    })
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
    newcomer.clanId = null
  })

  it('makes a visitor a guest who enters the queue with the starter deck of their clan', async () => {
    const { wrapper, router } = await mountJoin()

    expect(wrapper.find('h1').text()).toBe('Hida Takeshi invites you')
    await wrapper.find('#court-name').setValue('Doji Hayaku')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const post = fetchMock.mock.calls.find(([path]) => path === '/api/queue')!
    expect(post[1]!.body).toBe(JSON.stringify({ deck_id: 'crane-starter-deck', invitation_code: 'KX7-QM4-T9P' }))
    expect(session.value?.name).toBe('Doji Hayaku')
    expect(router.currentRoute.value.fullPath).toBe('/queue?code=KX7-QM4-T9P')
  })

  it('sends a visitor with a session to the queue', async () => {
    session.value = {
      userId: 'jane',
      name: 'Doji Hayaku',
      username: 'jane',
      preferredClan: 'crane',
      cardDisplay: 'drawn',
      theme: 'auto',
      token: 'l5r_jane',
    }

    const { router } = await mountJoin()

    expect(router.currentRoute.value.fullPath).toBe('/queue?code=KX7-QM4-T9P')
  })

  it('says when the invitation can no longer be used', async () => {
    state = 'used'

    const { wrapper } = await mountJoin()

    expect(wrapper.find('h1').text()).toBe('This invitation is no longer valid')
    expect(wrapper.text()).toContain('Ask Hida Takeshi for a new link.')
  })
})
