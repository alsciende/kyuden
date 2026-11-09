import { describe, expect, it } from 'vitest'

import type { UserGame } from '@/api/client'
import { endedLabel, playedGameRows } from '@/lib/history'

const now = new Date(2026, 10, 8, 18, 0)

function played(id: string, endedAt: string | null, winnerId: string | null, condition: string | null): UserGame {
  return {
    id,
    player_id: 'my-player',
    version: 120,
    ended: endedAt !== null,
    friendly: false,
    players: ['karo', 'me'],
    seats: [
      { user_id: 'karo', name: 'Kyūden no Karō', clan: 'scorpion', bot: true },
      { user_id: 'me', name: 'Akodo Haru', clan: 'lion', bot: false },
    ],
    ended_at: endedAt,
    winner_id: winnerId,
    victory_condition: condition,
  }
}

describe('endedLabel', () => {
  it('tells the day as a player says it', () => {
    expect(endedLabel(new Date(2026, 10, 8, 17, 12).toISOString(), now)).toBe('Today, 17:12')
    expect(endedLabel(new Date(2026, 10, 7, 9, 5).toISOString(), now)).toBe('Yesterday, 09:05')
    expect(endedLabel(new Date(2026, 10, 3, 22, 47).toISOString(), now)).toBe('3 Nov, 22:47')
    expect(endedLabel(new Date(2025, 0, 2, 8, 0).toISOString(), now)).toBe('2 Jan 2025, 08:00')
    expect(endedLabel(null, now)).toBe('')
  })
})

describe('playedGameRows', () => {
  it('lists the games that have ended, the last first, the user first, the result from their side', () => {
    const rows = playedGameRows(
      [
        played('older', new Date(2026, 10, 6, 19, 30).toISOString(), 'other-player', 'stronghold_province_broken'),
        played('in-progress', null, null, null),
        played('last', new Date(2026, 10, 8, 17, 12).toISOString(), 'my-player', 'concession'),
      ],
      'me',
      now,
    )

    expect(rows.map((row) => row.id)).toEqual(['last', 'older'])
    expect(rows[0]).toEqual({
      id: 'last',
      players: [
        { name: 'Akodo Haru', tint: '#d9c59f', bot: false },
        { name: 'Kyūden no Karō', tint: '#d8a9a3', bot: true },
      ],
      bot: true,
      friendly: false,
      result: 'Victory',
      won: true,
      how: 'The opponent conceded.',
      ended: 'Today, 17:12',
    })
    expect([rows[1]!.result, rows[1]!.how]).toEqual(['Defeat', 'The opponent broke your stronghold province.'])
  })
})
