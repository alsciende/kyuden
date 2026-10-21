import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import { session } from '@/lib/session'
import SignupView from '@/views/SignupView.vue'

async function mountSignup() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'landing', component: { template: '<div />' } },
      { path: '/signup', name: 'signup', component: SignupView },
      { path: '/account', name: 'account', component: { template: '<div />' } },
    ],
  })
  await router.push('/signup')
  const wrapper = mount(SignupView, { global: { plugins: [router] } })
  await flushPromises()

  return { wrapper, router }
}

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

describe('SignupView', () => {
  enableAutoUnmount(afterEach)
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    localStorage.clear()
    const guest = { userId: 'user-1', name: 'Hida Takeshi', username: null, token: 'l5r_a' }
    localStorage.setItem('kyuden.session', JSON.stringify(guest))
    session.value = guest
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
  })

  it('shows the name of the guest, and checks the credentials before sending them', async () => {
    const { wrapper } = await mountSignup()

    expect(wrapper.find('h1').text()).toBe('Hida Takeshi')

    await wrapper.find('#username').setValue('hida takeshi')
    await wrapper.find('#password').setValue('short')
    await wrapper.find('form').trigger('submit')

    expect(fetchMock).not.toHaveBeenCalled()
    expect(wrapper.findAll('.form-error')).toHaveLength(2)
  })

  it('shows a username taken, then leads to the account once created', async () => {
    fetchMock
      .mockResolvedValueOnce(
        json(422, {
          type: 'validation_failed',
          detail: 'Invalid.',
          violations: [{ property_path: 'username', message: 'This username is taken.' }],
        }),
      )
      .mockResolvedValueOnce(
        json(201, { user_id: 'user-1', name: 'Hida Takeshi', username: 'takeshi_2', token: 'l5r_b' }),
      )
    const { wrapper, router } = await mountSignup()

    await wrapper.find('#username').setValue('takeshi')
    await wrapper.find('#password').setValue('wallmusthold')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('.form-error').text()).toBe('This username is taken.')

    await wrapper.find('#username').setValue('takeshi_2')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('account')
    expect(router.currentRoute.value.query.created).toBe('1')
    expect(session.value?.username).toBe('takeshi_2')
  })
})
