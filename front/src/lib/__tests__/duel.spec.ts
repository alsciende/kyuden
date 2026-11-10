import { describe, expect, it } from 'vitest'

import type { DuelView, GameView, PlayerView } from '@/api/game'
import { charactersInPlay } from '@/lib/board'
import {
  duelBoard,
  duelCardIdsOf,
  duelStatistic,
  duelStepOf,
  playableDuringDuel,
  statisticName,
  stepWithDuel,
} from '@/lib/duel'

import { card, game, player, printed, stack } from './gameFixture'

const cards = {
  kaezin: printed('kaezin', { name: 'Kakita Kaezin', military: '3', political: '3', glory: 2 }),
  gunso: printed('gunso', { name: 'Akodo Gunsō', military: '2', political: '1', glory: 1 }),
  katana: printed('katana', { type: 'attachment', military_bonus: '+2' }),
  stay: printed('stay', { type: 'event', ability_timings: ['interrupt'] }),
  banzai: printed('banzai', { type: 'event', ability_timings: ['action'] }),
  fan: printed('fan', { type: 'attachment' }),
}

function duelGame(duel: Partial<DuelView> = {}, mine: Partial<PlayerView> = {}, theirs: Partial<PlayerView> = {}) {
  const gunso = card('gunso', { id: 'gunso' })
  const kaezin = card('kaezin', { id: 'kaezin' })
  const me = player('me', [stack('in_play', [gunso])], mine)
  const opp = player('opp', [stack('in_play', [kaezin, card('katana')])], theirs)
  const view: GameView = game(me, opp, {
    duel: {
      challenger_id: 'opp',
      challenged_id: 'me',
      type: 'military',
      challenger_card_ids: ['kaezin'],
      challenged_card_ids: ['gunso'],
      revealed: false,
      challenger_statistic: null,
      challenged_statistic: null,
      resolved: false,
      winner_id: null,
      loser_id: null,
      ...duel,
    },
  })

  return view
}

describe('the steps of a duel', () => {
  it('goes from the bid to the statistic once the dials are revealed, then to the result', () => {
    expect(duelStepOf(duelGame().duel!)).toBe('bid')
    expect(duelStepOf(duelGame({ revealed: true }).duel!)).toBe('statistic')
    expect(duelStepOf(duelGame({ revealed: true, resolved: true }).duel!)).toBe('result')
  })

  it('names the statistic after the type of the duel', () => {
    expect(statisticName(duelGame().duel!)).toBe('Military skill')
    expect(statisticName(duelGame({ type: 'glory' }).duel!)).toBe('Glory')
  })

  it('tells the step of the duel in the bar, rather than the step of the game place', () => {
    expect(stepWithDuel(duelGame()).title).toBe('Duel · honor bid')
    expect(stepWithDuel(duelGame({ revealed: true })).title).toBe('Duel · statistic')
    expect(stepWithDuel(game(player('me'), player('opp'))).title).not.toContain('Duel')
  })
})

describe('the duel on the board', () => {
  it('shows the opponent above the viewer, each with their role and their characters', () => {
    const board = duelBoard(duelGame(), cards)!

    expect(board.sides.map((side) => [side.player.id, side.role])).toEqual([
      ['opp', 'challenger'],
      ['me', 'challenged'],
    ])
    expect(board.sides[0].characters.map((character) => character.card.id)).toEqual(['kaezin'])
    expect(board.context).toBe('Opp. challenges · outside a conflict')
    expect(duelCardIdsOf(duelGame())).toEqual(['kaezin', 'gunso'])
  })

  it('keeps the bids secret until they are revealed: the value for its owner only', () => {
    const board = duelBoard(
      duelGame({}, { pending_honor_bid: 2, honor_bid_selected: true }, { honor_bid_selected: true }),
      cards,
    )!

    expect(board.sides[0].bid).toEqual({ value: null, secret: true, caption: 'Bid placed' })
    expect(board.sides[1].bid).toEqual({ value: 2, secret: true, caption: 'Not revealed yet' })
  })

  it('adds the honor bid to the statistic declared, and tells who must still declare', () => {
    const board = duelBoard(
      {
        ...duelGame({ revealed: true, challenger_statistic: 5 }, { honor_bid: 1 }, { honor_bid: 3 }),
        available_actions: ['declare_duel_statistic'],
      },
      cards,
    )!

    expect(board.band.title).toBe('Dials revealed: 3 and 1')
    expect(board.sides[0]).toMatchObject({ statistic: 5, total: 8, statisticCaption: 'Declared' })
    expect(board.sides[1]).toMatchObject({ statistic: null, total: null, statisticCaption: 'Your turn' })
  })

  it('tells the winner, the loser and the honor transferred once the result is known', () => {
    const board = duelBoard(
      duelGame(
        {
          revealed: true,
          resolved: true,
          challenger_statistic: 5,
          challenged_statistic: 3,
          winner_id: 'opp',
          loser_id: 'me',
        },
        { honor_bid: 1 },
        { honor_bid: 3 },
      ),
      cards,
    )!

    expect(board.sides.map((side) => side.outcome)).toEqual(['wins', 'loses'])
    expect(board.sides.map((side) => side.bid.caption)).toEqual(['Gave 2 honor', 'Received 2 honor'])
    expect(board.band).toMatchObject({ title: 'Opp. wins the duel, 8 to 4', won: true })
    expect(board.band.text).toContain('Kakita Kaezin wins, Akodo Gunsō loses')
  })

  it('tells a tie', () => {
    const board = duelBoard(
      duelGame(
        { revealed: true, resolved: true, challenger_statistic: 4, challenged_statistic: 4 },
        { honor_bid: 2 },
        { honor_bid: 2 },
      ),
      cards,
    )!

    expect(board.sides.map((side) => side.outcome)).toEqual([null, null])
    expect(board.band.title).toBe('A tie, 6 to 6')
  })

  it('names the players for a spectator', () => {
    const board = duelBoard(
      {
        ...duelGame(
          { revealed: true, resolved: true, challenger_statistic: 5, challenged_statistic: 6, winner_id: 'me' },
          { honor_bid: 1 },
          { honor_bid: 1 },
        ),
        spectating: true,
      },
      cards,
    )!

    expect(board.band.title).toBe('me wins the duel, 7 to 6')
    expect(board.context).toBe('opp challenges · outside a conflict')
  })
})

describe('the prefill of the dueling statistic', () => {
  it('reads the skill of the type from the cards, without the honor bid', () => {
    const view = duelGame({}, { honor_bid: 3 })
    const opp = view.players.find((candidate) => candidate.id === 'opp')!

    expect(duelStatistic(charactersInPlay(opp), cards, view.duel!)).toBe(5)
    expect(duelStatistic(charactersInPlay(opp), cards, { ...view.duel!, type: 'glory' })).toBe(2)
  })
})

describe('the hand during a duel', () => {
  it('only plays the interrupts and the reactions', () => {
    expect(playableDuringDuel('stay', cards)).toBe(true)
    expect(playableDuringDuel('banzai', cards)).toBe(false)
    expect(playableDuringDuel('fan', cards)).toBe(false)
    expect(playableDuringDuel(null, cards)).toBe(true)
  })
})
