import { describe, expect, it } from 'vitest'

import type { LoggedMessage } from '@/api/game'
import type { ReplayView } from '@/api/replay'
import {
  actionMarks,
  currentRound,
  entriesSince,
  jumpTo,
  nextEntries,
  progress,
  replayLogRows,
  roundMarks,
  stoppedBefore,
} from '@/lib/replay'

const entry = (template: string) => ({ template, params: [] as [] })

/** a short game: me (`me`) and the opponent (`opp`) */
const log: LoggedMessage[] = [
  { version: 1, type: 'create_game', player_id: null, entries: [entry('The game is created')] },
  { version: 2, type: 'join_game', player_id: 'me', entries: [entry('{player} joins the game')] },
  {
    version: 3,
    type: 'join_game',
    player_id: 'opp',
    entries: [entry('{player} joins the game'), entry('The dynasty phase begins')],
  },
  { version: 4, type: 'pass_opportunity', player_id: 'me', entries: [entry('{player} passes')] },
  { version: 5, type: 'pass_opportunity', player_id: 'opp', entries: [entry('The draw phase begins')] },
  { version: 6, type: 'set_character_skill', player_id: 'opp', entries: [] },
  { version: 7, type: 'bid_honor', player_id: 'me', entries: [entry('{player} bids {amount} honor')] },
  { version: 8, type: 'pass_opportunity', player_id: 'opp', entries: [entry('The dynasty phase begins')] },
]

function replay(version: number, next: number | null): ReplayView {
  return {
    game_id: 'game',
    replay_game_id: 'copy',
    player_id: 'me',
    version,
    total: 8,
    next: next === null ? null : { version: next, type: 'bid_honor' },
    ended: version === 8,
  }
}

describe('stoppedBefore', () => {
  it('is the action of the player when it is the next message', () => {
    expect(stoppedBefore(replay(6, 7))?.version).toBe(7)
  })

  it('is nothing when the replay stands before other messages (a jump)', () => {
    expect(stoppedBefore(replay(5, 7))).toBeNull()
    expect(stoppedBefore(replay(8, null))).toBeNull()
  })
})

describe('replayLogRows', () => {
  it('heads each phase with its round, and tells what is replayed, next and to come', () => {
    const rows = replayLogRows(log, replay(6, 7))

    expect(rows.filter((row) => row.kind === 'heading').map((row) => [row.text, row.future])).toEqual([
      ['Round 1 · Dynasty phase', false],
      ['Round 1 · Draw phase', false],
      ['Round 2 · Dynasty phase', true],
    ])
    expect(rows.flatMap((row) => (row.kind === 'message' ? [[row.version, row.state, row.mine]] : []))).toEqual([
      [1, 'past', false],
      // joining is the platform's
      [2, 'past', false],
      [3, 'past', false],
      [4, 'past', true],
      [5, 'past', false],
      // a message writing nothing has no row
      [7, 'next', true],
      [8, 'future', false],
    ])
  })
})

describe('timeline', () => {
  it('marks the rounds and the actions of the player', () => {
    expect(roundMarks(log, 8)).toEqual([
      { at: 2 / 8, label: 'R1' },
      { at: 7 / 8, label: 'R2' },
    ])
    expect(actionMarks(log, replay(6, 7)).map((mark) => mark.label)).toEqual(['#4', '#7'])
    expect(progress(replay(6, 7))).toBe(6 / 8)
    expect(currentRound(log, 6)).toBe(1)
    expect(currentRound(log, 8)).toBe(2)
  })
})

describe('what the player does next and what happened since', () => {
  it('tells the action the replay stopped before', () => {
    expect(nextEntries(log, replay(6, 7))).toEqual([entry('{player} bids {amount} honor')])
    expect(nextEntries(log, replay(5, 7))).toEqual([])
  })

  it('tells what a step has replayed', () => {
    expect(entriesSince(log, 3, 6)).toEqual([entry('{player} passes'), entry('The draw phase begins')])
  })
})

describe('jumpTo', () => {
  it('goes on to a message to come, starts again to one replayed', () => {
    expect(jumpTo(replay(4, 7), 7)).toEqual({ direction: 'forward', until: 7 })
    expect(jumpTo(replay(6, 7), 2)).toEqual({ direction: 'back', until: 2 })
    expect(jumpTo(replay(6, 7), 6)).toBeNull()
    expect(jumpTo(replay(6, 7), 99)).toEqual({ direction: 'forward', until: 8 })
  })
})
