import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import { newcomer } from '@/lib/newcomer'
import LandingView from '@/views/LandingView.vue'

async function mountLanding() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'landing', component: LandingView },
      { path: '/name', name: 'name', component: { template: '<div />' } },
    ],
  })
  await router.push('/')
  const wrapper = mount(LandingView, { global: { plugins: [router] } })
  await flushPromises()

  return { wrapper, router }
}

describe('LandingView', () => {
  beforeEach(() => {
    newcomer.clanId = null
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => new Response(JSON.stringify({ code: '', name: 'A card', image_url: null }), { status: 200 })),
    )
  })

  it('shows the starter deck of the clan picked, and hides it when the clan is picked again', async () => {
    const { wrapper } = await mountLanding()
    const crab = wrapper.findAll('.clan')[0]!

    await crab.trigger('click')

    expect(newcomer.clanId).toBe('crab')
    expect(crab.attributes('aria-pressed')).toBe('true')
    expect(wrapper.find('.panel h2').text()).toBe('The Crab Clan')

    await crab.trigger('click')

    expect(newcomer.clanId).toBeNull()
    expect(wrapper.find('.panel h2').text()).toBe('The seven Great Clans')
  })

  it('leads to the choice of a name once a clan is picked', async () => {
    const { wrapper, router } = await mountLanding()

    await wrapper.findAll('.clan')[3]!.trigger('click')
    await wrapper.find('.play').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('name')
  })
})
