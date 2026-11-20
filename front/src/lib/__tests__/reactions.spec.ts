import { beforeEach, describe, expect, it } from 'vitest'
import { nextTick, ref } from 'vue'

import type { GameView, ReactionWindowView } from '@/api/game'
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
