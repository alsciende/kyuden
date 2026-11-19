import { describe, expect, it } from 'vitest'

import type { UserTrophy } from '@/api/client'
import { TROPHIES, trophyText } from '@/data/trophies'
import { earnedHeading, earnedInGame, earnedOn, isWorn, trophyCards, withReward } from '@/lib/trophies'

const trophy = (overrides: Partial<UserTrophy>): UserTrophy => ({
  trophy: 'clan_crab',
  reward_mon: 'crab',
  reward_pattern: null,
  earned: false,
  earned_at: null,
  game_id: null,
  ...overrides,
})

describe('the trophies', () => {
  it('names the trophy of the house bot, one of each Great Clan and those of the tournaments, in the order of the catalogue', () => {
    expect(TROPHIES.map((text) => text.id)).toEqual([
      'house_bot_game',
      'clan_crab',
      'clan_crane',
      'clan_dragon',
      'clan_lion',
      'clan_phoenix',
      'clan_scorpion',
      'clan_unicorn',
      'tournament_game',
      'tournament_win',
    ])
    expect(trophyText('tournament_win')).toEqual({
      id: 'tournament_win',
      name: 'Emerald Champion',
      goal: 'Finish first in a tournament.',
    })
    expect(trophyText('clan_lion')).toEqual({
      id: 'clan_lion',
      name: 'Champion of the Lion',
      goal: 'Win a standard game with a Lion stronghold.',
    })
  })

  it('names a trophy it does not know by its value', () => {
    expect(trophyText('five_rings').name).toBe('five_rings')
  })

  it('tells the reward and the day it was earned', () => {
    const [crab, plain] = trophyCards([
      trophy({ earned: true, earned_at: '2026-11-12T12:00:00+00:00', game_id: 'g1' }),
      trophy({ trophy: 'house_bot_game', reward_mon: null, reward_pattern: 'plain' }),
    ])

    expect(crab).toMatchObject({
      name: 'Champion of the Crab',
      reward: 'the mon of the Crab',
      rewardChange: { mon: 'crab' },
      earnedOn: '12 November 2026',
      gameId: 'g1',
    })
    expect(plain).toMatchObject({ reward: 'the plain pattern', rewardChange: { pattern: 'plain' }, earnedOn: null })
  })

  it('tells the patterns of the trophies of the tournaments', () => {
    const [game, win] = trophyCards([
      trophy({ trophy: 'tournament_game', reward_mon: null, reward_pattern: 'tatejima' }),
      trophy({ trophy: 'tournament_win', reward_mon: null, reward_pattern: 'dandara' }),
    ])

    expect(game).toMatchObject({ name: 'Into the Lists', reward: 'the tatejima pattern' })
    expect(win).toMatchObject({
      name: 'Emerald Champion',
      reward: 'the dandara pattern',
      rewardChange: { pattern: 'dandara' },
    })
  })

  it('announces the trophies earned in the game only', () => {
    const cards = trophyCards([
      trophy({ earned: true, earned_at: '2026-11-12T12:00:00+00:00', game_id: 'g1' }),
      trophy({
        trophy: 'clan_lion',
        reward_mon: 'lion',
        earned: true,
        earned_at: '2026-11-10T12:00:00+00:00',
        game_id: 'g0',
      }),
      trophy({ trophy: 'clan_crane', reward_mon: 'crane' }),
    ])

    expect(earnedInGame(cards, 'g1').map((card) => card.id)).toEqual(['clan_crab'])
    expect(earnedHeading(1)).toBe('Trophy earned')
    expect(earnedHeading(3)).toBe('3 trophies earned')
  })

  it('raises the reward on the nobori, keeping the other part', () => {
    const [crab] = trophyCards([trophy({})])

    expect(withReward(crab!, { mon: 'enso', pattern: 'seigaiha' })).toEqual({ mon: 'crab', pattern: 'seigaiha' })
    expect(isWorn(crab!, { mon: 'enso', pattern: 'seigaiha' })).toBe(false)
    expect(isWorn(crab!, { mon: 'crab', pattern: 'seigaiha' })).toBe(true)
  })

  it('writes the day in full', () => {
    expect(earnedOn('2026-01-03T10:00:00')).toBe('3 January 2026')
  })
})
