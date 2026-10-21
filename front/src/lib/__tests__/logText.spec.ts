import { describe, expect, it } from 'vitest'

import { entryText } from '@/lib/logText'

import { game, player, printed } from './gameFixture'

const view = game(player('me'), player('opp'))
const cards = { kaezin: printed('kaezin', { name: 'Kakita Kaezin' }) }

describe('entryText', () => {
  it('names the players from the point of view of the viewer, in the second person for the viewer', () => {
    const entry = (id: string) => ({
      template: '{player} chooses {province} as their stronghold province',
      params: {
        player: { type: 'player' as const, id },
        province: { type: 'card' as const, id: null, code: null },
      },
    })

    expect(entryText(entry('me'), view, cards)).toBe('You choose a hidden card as your stronghold province')
    expect(entryText(entry('opp'), view, cards)).toBe('Opp. chooses a hidden card as their stronghold province')
  })

  it('conjugates the verbs of the viewer', () => {
    const text = (template: string) =>
      entryText({ template, params: { player: { type: 'player', id: 'me' } } }, view, cards)

    expect(text('{player} passes')).toBe('You pass')
    expect(text('{player} is the first player')).toBe('You are the first player')
    expect(text('{player} has no honor left')).toBe('You have no honor left')
  })

  it('shows the cards, rings, stacks and values as the viewer knows them', () => {
    const text = entryText(
      {
        template: '{card} moves from {from} for {ring}: {amount}, {secret}',
        params: {
          card: { type: 'card', id: 'c', code: 'kaezin' },
          from: { type: 'stack', stack_type: 'hand', player_id: 'opp', id: 's' },
          ring: { type: 'ring', element: 'void' },
          amount: { type: 'value', value: 2 },
          secret: { type: 'value', hidden: true },
        },
      },
      view,
      cards,
    )

    expect(text).toBe("Kakita Kaezin moves from the opponent's hand for the Void ring: 2, ?")
  })

  it('keeps a template without params as is', () => {
    expect(entryText({ template: 'The conflict phase begins', params: [] }, view, cards)).toBe(
      'The conflict phase begins',
    )
  })
})
