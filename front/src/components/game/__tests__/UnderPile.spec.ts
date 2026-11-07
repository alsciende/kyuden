import { enableAutoUnmount, mount } from '@vue/test-utils'
import { computed } from 'vue'
import { afterEach, describe, expect, it } from 'vitest'

import CharacterRow from '@/components/game/CharacterRow.vue'
import ProvinceRow from '@/components/game/ProvinceRow.vue'
import { card, game, player, printed, stack, underneath } from '@/lib/__tests__/gameFixture'
import { cardsUnder, charactersInPlay } from '@/lib/board'
import type { GameView } from '@/api/game'
import { CARDS_UNDER } from '@/lib/underneath'

enableAutoUnmount(afterEach)

const cards = {
  shihobu: printed('shihobu', { name: 'Kaiu Shihobu' }),
  forges: printed('forges', { type: 'holding', name: 'Kaiu Forges' }),
  mine: printed('mine', { type: 'holding', name: 'Iron Mine' }),
  scout: printed('scout', { name: 'Eager Scout' }),
  raid: printed('raid', { type: 'province', name: 'Night Raid', strength: 3 }),
  stronghold: printed('stronghold', { type: 'stronghold', name: 'Kyūden Kakita' }),
}

function provide(view: GameView) {
  return { provide: { [CARDS_UNDER as symbol]: computed(() => cardsUnder(view)) } }
}

describe('UnderPile', () => {
  const shihobu = card('shihobu')
  const scout = card('scout')
  const mineCard = card('mine', { side: 'dynasty', orientation: 'face_down' })
  const forges = card('forges', { side: 'dynasty', orientation: 'face_down' })
  const me = player('me', [
    stack('in_play', [shihobu]),
    stack('in_play', [scout]),
    underneath(shihobu, [mineCard, forges]),
  ])
  const view = game(me, player('opp'))

  function row() {
    return mount(CharacterRow, {
      props: { characters: charactersInPlay(me), cards, mine: true },
      global: provide(view),
      attachTo: document.body,
    })
  }

  it('shows the number of the cards under a card, their edges below it, and nothing under the others', () => {
    const wrapper = row()

    const badges = wrapper.findAll('.badge')
    expect(badges).toHaveLength(1)
    expect(badges[0]!.text()).toBe('2 under')
    expect(wrapper.findAll('.edge')).toHaveLength(2)
  })

  it('lists the cards under it, the last placed first, and opens the one clicked', async () => {
    const wrapper = row()

    await wrapper.find('.badge').trigger('click')

    expect(wrapper.find('.caption').text()).toBe('Under Kaiu Shihobu · 2')
    const items = wrapper.findAll('.pile-card')
    expect(items.map((item) => item.find('.pile-name').text())).toEqual(['Kaiu Forges', 'Iron Mine'])
    expect(items[0]!.find('.pile-face').text()).toBe('Face down, only you know it')

    await items[1]!.trigger('click')

    expect(wrapper.emitted('open')).toEqual([[mineCard.id]])
    expect(wrapper.find('.popover').exists()).toBe(false)
  })

  it('shows the cards under a province by a badge on its name, and those under the stronghold below it', () => {
    const province = card('raid', { side: 'province' })
    const stronghold = card('stronghold', { side: 'stronghold' })
    const hidden = card(null, { side: 'dynasty', orientation: 'face_down' })
    const opp = player('opp', [
      stack('stronghold_province', [stronghold, province]),
      underneath(province, [hidden]),
      underneath(stronghold, [card(null, { orientation: 'face_down' }), card(null, { orientation: 'face_down' })]),
    ])
    const seen = game(player('me'), opp)

    const wrapper = mount(ProvinceRow, { props: { game: seen, player: opp, cards }, global: provide(seen) })

    expect(wrapper.findAll('.badge').map((badge) => badge.text())).toEqual(['2 under', '1 under'])
    expect(wrapper.findAll('.edge')).toHaveLength(2)
    expect(wrapper.find('.province').classes()).toContain('carrying')
  })
})
