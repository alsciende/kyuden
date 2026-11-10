import { enableAutoUnmount, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'

import ProvinceRow from '@/components/game/ProvinceRow.vue'
import { card, game, player, printed, stack } from '@/lib/__tests__/gameFixture'

enableAutoUnmount(afterEach)

const cards = {
  raid: printed('raid', { type: 'province', name: 'Night Raid', strength: 3 }),
  samurai: printed('samurai', { name: 'Brash Samurai' }),
  marketplace: printed('marketplace', { type: 'holding', name: 'Daidoji Marketplace' }),
  garden: printed('garden', { type: 'province', name: 'Manicured Garden', strength: 4 }),
}

describe('ProvinceRow', () => {
  const samurai = card('samurai', { side: 'dynasty' })
  const marketplace = card('marketplace', { side: 'dynasty' })
  const hidden = card(null, { side: 'dynasty', orientation: 'face_down' })
  const me = player('me', [
    stack('regular_province', [card('raid', { side: 'province' }), marketplace, samurai]),
    stack('regular_province', [card('garden', { side: 'province' }), hidden]),
  ])
  const view = game(me, player('opp'))

  function row() {
    return mount(ProvinceRow, { props: { game: view, player: me, cards }, attachTo: document.body })
  }

  it('shows the number of the cards beneath the top one of a province holding several, and nothing otherwise', () => {
    const wrapper = row()

    const badges = wrapper.findAll('.more')
    expect(badges).toHaveLength(1)
    expect(badges[0]!.text()).toBe('+1')
    expect(wrapper.findAll('.depth')).toHaveLength(1)
  })

  it('lists the cards of the province, the top first, and opens the one clicked', async () => {
    const wrapper = row()

    await wrapper.find('.more').trigger('click')

    expect(wrapper.find('.caption').text()).toBe('Night Raid · 2 cards')
    const items = wrapper.findAll('.pile-card')
    expect(items.map((item) => item.find('.pile-name').text())).toEqual(['Brash Samurai', 'Daidoji Marketplace'])
    expect(items.map((item) => item.find('.pile-face').text())).toEqual(['Face up', 'Face up'])

    await items[1]!.trigger('click')

    expect(wrapper.emitted('open')).toEqual([[marketplace.id]])
    expect(wrapper.find('.popover').exists()).toBe(false)
  })

  it('marks the face-down provinces its owner knows, not those nobody sees', () => {
    const owner = player('me', [
      stack('regular_province', [card('raid', { side: 'province', orientation: 'face_down' })]),
      stack('regular_province', [card('garden', { side: 'province' })]),
    ])
    const opponent = player('opp', [
      stack('regular_province', [card(null, { side: 'province', orientation: 'face_down' })]),
    ])
    const shown = (who: typeof owner) =>
      mount(ProvinceRow, { props: { game: game(owner, opponent), player: who, cards } })
        .findAll('.tag')
        .map((tag) => tag.find('.tag-state').exists())

    expect(shown(owner)).toEqual([true, false])
    expect(shown(opponent)).toEqual([false])
  })

  it('hides the badge while a province is chosen', () => {
    const wrapper = mount(ProvinceRow, { props: { game: view, player: me, cards, selectable: [] } })

    expect(wrapper.find('.more').exists()).toBe(false)
  })
})
