import { mount, RouterLinkStub } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'

async function mountFooter() {
  vi.resetModules()
  const { default: AppFooter } = await import('@/components/AppFooter.vue')
  return mount(AppFooter, { global: { stubs: { RouterLink: RouterLinkStub } } })
}

describe('AppFooter', () => {
  afterEach(() => {
    vi.unstubAllEnvs()
  })

  it('links to the official Discord server, in a new tab', async () => {
    vi.stubEnv('VITE_DISCORD_URL', 'https://discord.gg/kyuden')

    const link = (await mountFooter()).get('a.discord')

    expect(link.attributes('href')).toBe('https://discord.gg/kyuden')
    expect(link.attributes('target')).toBe('_blank')
    expect(link.attributes('rel')).toBe('noopener noreferrer')
    expect(link.text()).toBe('Discord')
    expect(link.find('svg').exists()).toBe(true)
  })

  it('has no link to Discord without its URL', async () => {
    vi.stubEnv('VITE_DISCORD_URL', '')

    expect((await mountFooter()).find('a.discord').exists()).toBe(false)
  })
})
