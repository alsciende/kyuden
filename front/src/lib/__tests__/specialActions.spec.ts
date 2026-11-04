import { describe, expect, it } from 'vitest'

import { cardMenu } from '@/lib/cardActions'
import { groupSpecial, neededCount, sourcesLabel, specialActions } from '@/lib/specialActions'

import { card, game, player, printed, stack } from './gameFixture'

const cards = {
  kaezin: printed('kaezin', { name: 'Kakita Kaezin' }),
  giver: printed('giver', { name: 'Diplomatic Gift-Giver' }),
  feint: printed('feint', { name: 'Feint', type: 'event' }),
}

function board() {
  const kaezin = card('kaezin')
  const giver = card('giver')
  const feint = card('feint')
  const view = game(
    player('me', [stack('in_play', [kaezin]), stack('in_play', [giver]), stack('hand', [feint])]),
    player('opp'),
    {
      available_actions: ['bow_card', 'move_fate', 'put_card_on_deck', 'shuffle_into_deck', 'discard_card'],
      special_actions: [
        { type: 'move_fate', card_ids: [giver.id] },
        { type: 'put_card_on_deck', card_ids: [feint.id, giver.id] },
        { type: 'shuffle_into_deck', card_ids: [] },
      ],
    },
  )

  return { view, kaezin, giver, feint }
}

describe('specialActions', () => {
  it('knows the special actions and those the cards of the viewer need', () => {
    const { view } = board()
    const specials = specialActions(view, cards)

    expect(['bow_card', 'move_fate', 'shuffle_into_deck'].map((type) => specials.isSpecial(type))).toEqual([
      false,
      true,
      true,
    ])
    expect(['move_fate', 'shuffle_into_deck'].map((type) => specials.isNeeded(type))).toEqual([true, false])
  })

  it('names the cards needing them, and where they are, once each', () => {
    const { view } = board()

    expect(sourcesLabel(specialActions(view, cards), ['move_fate', 'put_card_on_deck'])).toBe(
      'Diplomatic Gift-Giver, in play · Feint, in your hand',
    )
  })

  it('groups the special actions of a list, those needed first, and counts them', () => {
    const { view } = board()
    const items = [
      { type: 'bow_card', label: 'Bow' },
      { type: 'shuffle_into_deck', label: 'Shuffle' },
      { type: 'put_card_on_deck', label: 'Top' },
      { type: 'put_card_on_deck', label: 'Bottom' },
    ]

    const groups = groupSpecial(specialActions(view, cards), items, ['move_fate', 'bow_card'])

    expect(groups.needed.map((group) => [group.type, group.items.length, group.form])).toEqual([
      ['put_card_on_deck', 2, false],
      ['move_fate', 0, true],
    ])
    expect(groups.others.map((group) => group.type)).toEqual(['shuffle_into_deck'])
    expect(neededCount(groups.needed)).toBe(3)
  })

  it('offers the special actions of a card apart from the others', () => {
    const { view, kaezin } = board()

    const menu = cardMenu(view, cards, kaezin.id)!

    expect(menu.more.map((action) => action.label)).toEqual([
      'Put on top of its deck',
      'Put at the bottom of its deck',
      'Shuffle into its deck',
    ])
    expect(menu.removals.map((action) => action.type)).toEqual(['discard_card'])
    expect(menu.state.map((action) => action.type)).toContain('bow_card')
  })
})
