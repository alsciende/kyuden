import { beforeEach, describe, expect, it } from 'vitest'
import { nextTick, ref } from 'vue'

import type { GameView, ReactionWindowView } from '@/api/game'
import { ELEMENT_NAMES } from '@/lib/icons'
import { offerOf, shownWindows, triggerText, useDeclinedReactions } from '@/lib/reactions'

import { card, game, player, printed, stack } from './gameFixture'

const cards = {
  raid: printed('raid', { name: 'Night Raid', type: 'province' }),
  outflank: printed('outflank', { name: 'Outflank', type: 'event' }),
  seeker: printed('seeker', { name: 'Seeker of Air', type: 'role' }),
}

function window(
  id: string,
  cardIds: string[],
  params: Record<string, string> = { province: 'raid' },
): ReactionWindowView {
  return { id, trigger: 'province_revealed', params, card_ids: cardIds }
}

function view(windows: ReactionWindowView[], changes: Partial<GameView> = {}): GameView {
  const me = player(
    'me',
    [
      stack('regular_province', [card('raid', { id: 'raid' })]),
      stack('hand', [card('outflank', { id: 'outflank' })]),
      stack('role', [card('seeker', { id: 'seeker' })]),
    ],
    { reaction_windows: windows },
  )

  return game(me, player('opp'), changes)
}

describe('triggerText', () => {
  it('names the province revealed', () => {
    expect(triggerText(window('1', []), view([]), cards)).toBe('After Night Raid is revealed')
  })

  it('falls back on a province the viewer does not know', () => {
    expect(triggerText(window('1', [], { province: 'unknown' }), view([]), cards)).toBe('After a province is revealed')
  })
})

describe('triggerText of the other triggering conditions', () => {
  const other = (trigger: string, params: Record<string, string>): ReactionWindowView => ({
    id: '1',
    trigger,
    params,
    card_ids: [],
  })

  it('names the attacking player', () => {
    expect(triggerText(other('conflict_declared', { attacker: 'me' }), view([]), cards)).toBe(
      'After you declare a conflict',
    )
    expect(triggerText(other('conflict_declaration_completed', { attacker: 'opp' }), view([]), cards)).toBe(
      'After your opponent declares a conflict',
    )
  })

  it('names the character moving to the conflict', () => {
    expect(triggerText(other('character_moved_to_conflict', { card: 'raid' }), view([]), cards)).toBe(
      'After Night Raid moves to the conflict',
    )
  })

  it('names the player taking the fate of a ring', () => {
    expect(triggerText(other('ring_fate_taken', { player: 'opp', ring: 'fire' }), view([]), cards)).toBe(
      `After your opponent takes the fate of the ${ELEMENT_NAMES.fire} ring`,
    )
  })

  it('names the player passing in the dynasty phase', () => {
    expect(triggerText(other('dynasty_phase_passed', { player: 'me' }), view([]), cards)).toBe(
      'After you pass in the dynasty phase',
    )
  })

  it('names the hand discarded from or looked at', () => {
    expect(triggerText(other('cards_discarded_from_hand', { player: 'opp' }), view([]), cards)).toBe(
      'After your opponent discards from hand',
    )
    expect(triggerText(other('cards_looked_at', { player: 'me', owner: 'opp' }), view([]), cards)).toBe(
      'After you look at a hand',
    )
  })

  it('names the character honored or dishonored', () => {
    expect(triggerText(other('personal_honor_changed', { card: 'raid', action: 'dishonored' }), view([]), cards)).toBe(
      'After Night Raid is dishonored',
    )
    expect(triggerText(other('status_token_placed', { card: 'raid' }), view([]), cards)).toBe(
      'After Night Raid receives a status token',
    )
  })

  it('names the player gaining, losing or giving honor', () => {
    expect(triggerText(other('honor_gained', { player: 'opp' }), view([]), cards)).toBe(
      'After your opponent gains honor',
    )
    expect(triggerText(other('honor_lost', { player: 'me' }), view([]), cards)).toBe('After you lose honor')
    expect(triggerText(other('honor_transferred', { giver: 'opp', recipient: 'me' }), view([]), cards)).toBe(
      'After your opponent gives honor',
    )
  })

  it('names the character fate is placed on or removed from', () => {
    expect(triggerText(other('fate_placed_on_card', { card: 'raid' }), view([]), cards)).toBe(
      'After fate is placed on Night Raid',
    )
    expect(triggerText(other('fate_removed_from_card', { card: 'raid' }), view([]), cards)).toBe(
      'After fate is removed from Night Raid',
    )
  })

  it('names the winner of a duel', () => {
    expect(triggerText(other('duel_won', { player: 'me' }), view([]), cards)).toBe('After you win the duel')
    expect(triggerText(other('duel_ended', {}), view([]), cards)).toBe('After the duel resolves')
  })

  it('names the card played and its player', () => {
    expect(triggerText(other('card_played', { card: 'outflank', player: 'opp' }), view([]), cards)).toBe(
      'After your opponent plays Outflank',
    )
  })

  it('names the card entering play', () => {
    expect(triggerText(other('card_entered_play', { card: 'raid', player: 'me' }), view([]), cards)).toBe(
      'After Night Raid enters play',
    )
  })

  it('names the phase beginning', () => {
    expect(triggerText(other('phase_began', { phase: 'conflict' }), view([]), cards)).toBe(
      'After the conflict phase begins',
    )
  })

  it('names the province broken and the player who broke it', () => {
    expect(triggerText(other('province_broken', { province: 'raid', breaker: 'opp' }), view([]), cards)).toBe(
      'After your opponent breaks Night Raid',
    )
  })

  it('tells the honor dials of a duel', () => {
    expect(triggerText(other('honor_dials_revealed', { bid: 'draw' }), view([]), cards)).toBe(
      'After the honor dials are revealed',
    )
    expect(triggerText(other('honor_dials_revealed', { bid: 'duel' }), view([]), cards)).toBe(
      'After the honor dials of the duel are revealed',
    )
  })

  it('names the player passing on declaring a conflict', () => {
    expect(triggerText(other('conflict_passed', { player: 'me' }), view([]), cards)).toBe(
      'After you pass on declaring a conflict',
    )
  })

  it('names the ring claimed and its player', () => {
    expect(triggerText(other('ring_claimed', { player: 'opp', ring: 'water' }), view([]), cards)).toBe(
      'After your opponent claims the Water ring',
    )
  })

  it('names the defending player', () => {
    expect(triggerText(other('defenders_declared', { player: 'opp' }), view([]), cards)).toBe(
      'After your opponent declares defenders',
    )
  })

  it('names the winner of the conflict', () => {
    expect(triggerText(other('conflict_won', { winner: 'me' }), view([]), cards)).toBe('After you win the conflict')
    expect(triggerText(other('conflict_won', { winner: 'opp' }), view([]), cards)).toBe(
      'After your opponent wins the conflict',
    )
  })
})

describe('shownWindows', () => {
  it('lists the cards offered in each window, where they are', () => {
    const windows = shownWindows(view([window('1', ['raid', 'outflank']), window('2', ['seeker'])]), cards, new Set())

    expect(windows).toEqual([
      {
        id: '1',
        trigger: 'After Night Raid is revealed',
        offers: [
          { windowId: '1', cardId: 'raid', name: 'Night Raid', kind: 'province' },
          { windowId: '1', cardId: 'outflank', name: 'Outflank', kind: 'event · hand' },
        ],
      },
      {
        id: '2',
        trigger: 'After Night Raid is revealed',
        offers: [{ windowId: '2', cardId: 'seeker', name: 'Seeker of Air', kind: 'role' }],
      },
    ])
  })

  it('leaves out the cards declined, and the windows left empty', () => {
    const windows = shownWindows(
      view([window('1', ['raid', 'outflank']), window('2', ['seeker'])]),
      cards,
      new Set(['1:raid', '2:seeker']),
    )

    expect(windows.map((shown) => shown.offers.map((offer) => offer.cardId))).toEqual([['outflank']])
  })

  it('offers nothing to a spectator', () => {
    expect(shownWindows(view([window('1', ['raid'])], { spectating: true }), cards, new Set())).toEqual([])
  })
})

describe('offerOf', () => {
  it('finds the first offer of a card, with its trigger', () => {
    const windows = shownWindows(view([window('1', ['outflank']), window('2', ['outflank'])]), cards, new Set())

    expect(offerOf(windows, 'outflank')).toMatchObject({ windowId: '1', trigger: 'After Night Raid is revealed' })
    expect(offerOf(windows, 'seeker')).toBeNull()
  })
})

describe('useDeclinedReactions', () => {
  beforeEach(() => sessionStorage.clear())

  it('keeps the cards declined for the tab, and forgets those of the windows closed', async () => {
    const current = ref<GameView | null>(view([window('1', ['raid']), window('2', ['seeker'])]))
    const { declined, decline } = useDeclinedReactions(current)

    decline({ windowId: '1', cardId: 'raid' })
    decline({ windowId: '2', cardId: 'seeker' })
    expect([...declined.value]).toEqual(['1:raid', '2:seeker'])
    // a reload of the page
    expect([...useDeclinedReactions(ref(current.value)).declined.value]).toEqual(['1:raid', '2:seeker'])

    current.value = view([window('2', ['seeker'])])
    await nextTick()
    expect([...declined.value]).toEqual(['2:seeker'])
    expect([...useDeclinedReactions(ref(current.value)).declined.value]).toEqual(['2:seeker'])
  })
})
