import { describe, expect, it } from 'vitest'

import type { Tournament, TournamentMatch, TournamentStatus, TournamentSummary } from '@/api/client'
import {
  bracketSize,
  conflictOf,
  countdownLabel,
  currentMatchOf,
  matchResultLabel,
  roundTitle,
  dayLabel,
  earliestStart,
  groupTournaments,
  inDaysLabel,
  isOpen,
  localDateOf,
  slotsOfDay,
  startLabel,
  weekdayTimeLabel,
} from '@/lib/tournaments'

const PARIS = 'Europe/Paris'
const now = new Date('2026-11-18T12:00:00Z')

function tournament(id: string, startsAt: string, status: TournamentStatus = 'open'): TournamentSummary {
  return {
    id,
    starts_at: startsAt,
    locks_at: new Date(Date.parse(startsAt) - 60 * 60 * 1000).toISOString(),
    status,
    format: 'single_elimination',
    organizer: { user_id: 'tsukune', name: 'Shiba Tsukune', banner: { mon: 'enso', pattern: 'none' } },
    entrants: 0,
    max_entrants: 64,
  }
}

describe('the dates of a tournament', () => {
  it('are told in the time zone of the viewer', () => {
    expect(dayLabel('2026-11-21T19:00:00Z', PARIS)).toBe('Saturday 21 November')
    expect(startLabel('2026-11-21T19:00:00Z', PARIS)).toBe('Saturday 21 November, 20:00')
    expect(startLabel('2026-11-21T19:00:00Z', 'America/New_York')).toBe('Saturday 21 November, 14:00')
    expect(weekdayTimeLabel('2026-11-21T18:00:00Z', PARIS)).toBe('Saturday 19:00')
    expect(localDateOf('2026-11-21T23:30:00Z', PARIS)).toBe('2026-11-22')
  })

  it('count the days of the calendar of the viewer', () => {
    expect(inDaysLabel('2026-11-18T20:00:00Z', now, PARIS)).toBe('today')
    expect(inDaysLabel('2026-11-18T23:30:00Z', now, PARIS)).toBe('tomorrow')
    expect(inDaysLabel('2026-11-21T19:00:00Z', now, PARIS)).toBe('in 3 days')
  })
})

describe('isOpen', () => {
  it('closes one hour before the start, and for good once cancelled', () => {
    const open = tournament('t', '2026-11-21T19:00:00Z')
    expect(isOpen(open, new Date('2026-11-21T17:59:59Z'))).toBe(true)
    expect(isOpen(open, new Date('2026-11-21T18:00:00Z'))).toBe(false)
    expect(isOpen({ ...open, status: 'cancelled' }, now)).toBe(false)
  })
})

describe('bracketSize', () => {
  it('is the smallest power of 2 holding the players', () => {
    expect([0, 1, 2, 3, 4, 5, 11, 17, 33, 64].map(bracketSize)).toEqual([null, null, 2, 4, 4, 8, 16, 32, 64, 64])
  })
})

describe('groupTournaments', () => {
  it('splits the list as /tournaments shows it, in its order', () => {
    const groups = groupTournaments([
      tournament('running', '2026-11-18T17:00:00Z', 'in_progress'),
      tournament('locked', '2026-11-18T20:00:00Z', 'locked'),
      tournament('called-off', '2026-11-19T20:00:00Z', 'cancelled'),
      tournament('open', '2026-11-21T19:00:00Z'),
    ])

    expect(groups.inProgress.map((t) => t.id)).toEqual(['running'])
    expect(groups.upcoming.map((t) => t.id)).toEqual(['locked', 'open'])
    expect(groups.cancelled.map((t) => t.id)).toEqual(['called-off'])
  })
})

describe('choosing the start', () => {
  it('begins on the half hour, at least 12 hours ahead', () => {
    expect(earliestStart(new Date('2026-11-18T12:00:00Z')).toISOString()).toBe('2026-11-19T00:00:00.000Z')
    expect(earliestStart(new Date('2026-11-18T12:00:01Z')).toISOString()).toBe('2026-11-19T00:30:00.000Z')
  })

  it('offers every half hour of the day of the viewer from the first start allowed', () => {
    const slots = slotsOfDay('2026-11-19', PARIS, now)

    // 01:00 in Paris is midnight UTC, 12 hours from now
    expect(slots[0]).toEqual({ iso: '2026-11-19T00:00:00.000Z', label: '01:00' })
    expect(slots.at(-1)).toEqual({ iso: '2026-11-19T22:30:00.000Z', label: '23:30' })
    expect(slots).toHaveLength(46)
    expect(slotsOfDay('2026-11-21', PARIS, now)).toHaveLength(48)
    expect(slotsOfDay('2026-11-18', PARIS, now)).toEqual([])
  })

  it('follows the offset of the time zone, half hours in UTC', () => {
    // Kathmandu is 5:45 ahead of UTC
    expect(slotsOfDay('2026-11-21', 'Asia/Kathmandu', now)[0]).toEqual({
      iso: '2026-11-20T18:30:00.000Z',
      label: '00:15',
    })
  })

  it('keeps 12 hours from the closest tournament still to be played', () => {
    const tournaments = [
      tournament('far', '2026-11-25T19:00:00Z'),
      tournament('near', '2026-11-21T17:00:00Z'),
      tournament('called-off', '2026-11-21T19:00:00Z', 'cancelled'),
    ]

    expect(conflictOf('2026-11-21T19:00:00Z', tournaments)).toEqual({
      other: tournaments[1],
      earlierLimit: '2026-11-21T05:00:00.000Z',
      laterLimit: '2026-11-22T05:00:00.000Z',
    })
    expect(conflictOf('2026-11-22T05:00:00Z', tournaments)).toBeNull()
  })
})

describe('the rounds', () => {
  const user = (id: string) => ({ user_id: id, name: id, banner: { mon: 'enso', pattern: 'none' } }) as const
  const match = (overrides: Partial<TournamentMatch>): TournamentMatch => ({
    id: 1,
    table: 1,
    players: [user('john'), user('jane')],
    game_id: null,
    winner_user_id: null,
    outcome: null,
    victory_condition: null,
    waiting: [],
    ...overrides,
  })

  it('names the last rounds', () => {
    expect([1, 2, 3, 4].map((number) => roundTitle(number, 4))).toEqual([
      'Round 1',
      'Round 2 · quarterfinals',
      'Round 3 · semifinals',
      'Round 4 · final',
    ])
  })

  it('tells how a match ended', () => {
    expect(matchResultLabel(match({}))).toBeNull()
    expect(matchResultLabel(match({ outcome: 'bye' }))).toBe('bye')
    expect(matchResultLabel(match({ outcome: 'forfeit' }))).toBe('won, forfeit')
    expect(matchResultLabel(match({ outcome: 'no_result' }))).toBe('both eliminated')
    expect(matchResultLabel(match({ outcome: 'game', victory_condition: 'stronghold_province_broken' }))).toBe(
      'won, stronghold broken',
    )
  })

  it('finds the match of the user in the current round', () => {
    const mine = match({ id: 2 })
    const tournament = {
      current_round: 2,
      rounds: [
        { number: 1, matches: [match({ id: 1, outcome: 'game' })] },
        { number: 2, matches: [match({ id: 3, players: [user('kaede')] }), mine] },
      ],
    } as unknown as Tournament

    expect(currentMatchOf(tournament, 'jane')).toBe(mine)
    expect(currentMatchOf(tournament, 'hotaru')).toBeNull()
    expect(currentMatchOf(tournament, null)).toBeNull()
  })

  it('counts down to a deadline', () => {
    const deadline = '2026-11-20T20:10:00Z'
    expect(countdownLabel(deadline, Date.parse('2026-11-20T20:01:36Z'))).toBe('8:24')
    expect(countdownLabel(deadline, Date.parse('2026-11-20T20:11:00Z'))).toBe('0:00')
  })
})
