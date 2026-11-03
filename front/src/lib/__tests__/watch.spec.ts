import { describe, expect, it } from 'vitest'

import type { WatchableGame } from '@/api/game'
import { spectatorsLabel, watchableGameRow } from '@/lib/watch'

const watchable: WatchableGame = {
  id: 'game',
  version: 12,
  place: 'play_cards_from_provinces',
  friendly: true,
  players: [
    { user_id: 'akiko', name: 'Doji Akiko', clan: 'crane', bot: false },
    { user_id: 'karo', name: 'Kyūden no Karō', clan: null, bot: true },
  ],
  spectators: 1,
}

describe('watchableGameRow', () => {
  it('tells who plays, where they are and how many watch', () => {
    expect(watchableGameRow(watchable, 'someone')).toEqual({
      id: 'game',
      players: [
        { name: 'Doji Akiko', tint: '#bacad3', bot: false },
        { name: 'Kyūden no Karō', tint: '#e2d8c4', bot: true },
      ],
      bot: true,
      friendly: true,
      yours: false,
      phase: 'Dynasty',
      step: 'Play cards from provinces',
      spectators: 1,
    })
  })

  it('tells the user their own games', () => {
    expect(watchableGameRow(watchable, 'akiko').yours).toBe(true)
  })
})

describe('spectatorsLabel', () => {
  it('counts the spectators', () => {
    expect(spectatorsLabel(1)).toBe('1 spectator')
    expect(spectatorsLabel(3)).toBe('3 spectators')
  })
})
