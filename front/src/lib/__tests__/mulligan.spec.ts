import { describe, expect, it } from 'vitest'

import type { LoggedMessage, LogEntry } from '@/api/game'
import { mulliganWindow } from '@/lib/mulligan'

import { card, game, player, stack } from './gameFixture'

function look(cardId: string, code: string | null, province: string, who = 'me'): LogEntry {
  return {
    template: '{player} looks at {cards} in {stack}',
    params: {
      player: { type: 'player', id: who },
      cards: { type: 'list', items: [{ type: 'card', id: cardId, code }] },
      stack: { type: 'stack', stack_type: 'regular_province', player_id: who, id: province },
    },
  }
}

/** the strongholds chosen: setup, then each player looks at their provinces */
const setUp: LoggedMessage = {
  version: 5,
  type: 'choose_stronghold_province',
  player_id: 'opp',
  entries: [look('d1', 'whisperer', 'p1'), look('d2', 'storyteller', 'p2'), look('o1', null, 'q1', 'opp')],
}

const myDynastyMulligan: LoggedMessage = {
  version: 6,
  type: 'mulligan_dynasty_cards',
  player_id: 'me',
  entries: [look('d1', 'whisperer', 'p1'), look('d3', 'challenger', 'p2')],
}

const dynastyStep = (active: string) =>
  game(player('me', [stack('hand', [])]), player('opp'), { place: 'dynasty_mulligan', active_player_ids: [active] })

describe('mulliganWindow', () => {
  it('shows the dynasty cards the viewer looked at to the active player', () => {
    const window = mulliganWindow(dynastyStep('me'), [setUp], 0)

    expect(window).toMatchObject({ step: 'dynasty', phase: 'choose', version: null })
    expect(window?.cards).toEqual([
      { id: 'd1', code: 'whisperer', isNew: false, provinceId: 'p1' },
      { id: 'd2', code: 'storyteller', isNew: false, provinceId: 'p2' },
    ])
  })

  it('lets the second player choose while the first one decides', () => {
    expect(mulliganWindow(dynastyStep('opp'), [setUp], 0)?.phase).toBe('wait')
  })

  it('shows the new cards once the viewer has mulliganed, until they are done', () => {
    const view = dynastyStep('opp')
    const window = mulliganWindow(view, [setUp, myDynastyMulligan], 5)

    expect(window).toMatchObject({ step: 'dynasty', phase: 'result', version: 6 })
    expect(window?.cards.map((item) => [item.id, item.isNew])).toEqual([
      ['d1', false],
      ['d3', true],
    ])
    expect(window?.setAside).toEqual([{ id: 'd2', code: 'storyteller' }])
    // done: nothing to show while the opponent decides
    expect(mulliganWindow(view, [setUp, myDynastyMulligan], 6)).toBeNull()
  })

  it('keeps the cards when the viewer mulligans none', () => {
    const kept: LoggedMessage = { ...myDynastyMulligan, entries: [] }
    const window = mulliganWindow(dynastyStep('opp'), [setUp, kept], 5)

    expect(window?.cards.map((item) => item.id)).toEqual(['d1', 'd2'])
    expect(window?.cards.some((item) => item.isNew)).toBe(false)
    expect(window?.setAside).toEqual([])
  })

  it('does not show again the result of a mulligan read when the page opened', () => {
    expect(mulliganWindow(dynastyStep('opp'), [setUp, myDynastyMulligan], 6)).toBeNull()
  })

  it('shows the starting hand in the conflict mulligan, then the cards drawn', () => {
    const hand = [card('banzai', { id: 'h1' }), card('katana', { id: 'h2' })]
    const choosing = game(player('me', [stack('hand', hand)]), player('opp'), {
      place: 'conflict_mulligan',
      active_player_ids: ['me'],
    })

    expect(mulliganWindow(choosing, [setUp, myDynastyMulligan], 6)).toMatchObject({
      step: 'conflict',
      phase: 'choose',
      cards: [
        { id: 'h1', code: 'banzai', isNew: false, provinceId: null },
        { id: 'h2', code: 'katana', isNew: false, provinceId: null },
      ],
    })

    const drawn = card('fan', { id: 'h3' })
    const mine: LoggedMessage = {
      version: 8,
      type: 'mulligan_conflict_cards',
      player_id: 'me',
      entries: [
        {
          template: '{player} draws {card}',
          params: { player: { type: 'player', id: 'me' }, card: { type: 'card', id: 'h3', code: 'fan' } },
        },
        {
          template: '{card} is shuffled from {from} into {to}',
          params: { card: { type: 'card', id: 'h2', code: 'katana' } },
        },
      ],
    }
    const after = game(player('me', [stack('hand', [hand[0]!, drawn])]), player('opp'), {
      place: 'reveal_facedown_dynasty_cards',
      active_player_ids: [],
    })
    const window = mulliganWindow(after, [setUp, myDynastyMulligan, mine], 6)

    expect(window).toMatchObject({ step: 'conflict', phase: 'result', version: 8 })
    expect(window?.cards.map((item) => [item.id, item.isNew])).toEqual([
      ['h1', false],
      ['h3', true],
    ])
    expect(window?.setAside).toEqual([{ id: 'h2', code: 'katana' }])
    expect(mulliganWindow(after, [setUp, myDynastyMulligan, mine], 8)).toBeNull()
  })

  it('shows nothing to a spectator', () => {
    expect(mulliganWindow({ ...dynastyStep('me'), spectating: true }, [setUp], 0)).toBeNull()
  })
})
