import type { UserTrophy } from '@/api/client'
import { monOf, patternOf, type Banner } from '@/data/nobori'
import { trophyText } from '@/data/trophies'

/**
 * The trophies of the user as the pages show them: their name, their goal, their reward for the nobori, and once
 * earned, when and in which game.
 */
export interface TrophyCard {
  id: string
  name: string
  goal: string
  earned: boolean
  /** "the mon of the Crab", "the plain pattern"; null without a reward */
  reward: string | null
  /** what to send to wear the reward: a part of the nobori */
  rewardChange: Partial<Banner> | null
  /** "12 November 2026", once earned */
  earnedOn: string | null
  gameId: string | null
}

const MONTHS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
]

/** "12 November 2026", in the time zone of the browser */
export function earnedOn(iso: string): string {
  const date = new Date(iso)

  return `${date.getDate()} ${MONTHS[date.getMonth()]} ${date.getFullYear()}`
}

function rewardOf(trophy: UserTrophy): { text: string; change: Partial<Banner> } | null {
  if (trophy.reward_mon !== null) {
    return { text: `the mon of the ${monOf(trophy.reward_mon).label}`, change: { mon: trophy.reward_mon } }
  }
  if (trophy.reward_pattern !== null) {
    return {
      text: `the ${patternOf(trophy.reward_pattern).name.toLowerCase()} pattern`,
      change: { pattern: trophy.reward_pattern },
    }
  }

  return null
}

export function trophyCards(trophies: UserTrophy[]): TrophyCard[] {
  return trophies.map((trophy) => {
    const text = trophyText(trophy.trophy)
    const reward = rewardOf(trophy)

    return {
      id: trophy.trophy,
      name: text.name,
      goal: text.goal,
      earned: trophy.earned,
      reward: reward?.text ?? null,
      rewardChange: reward?.change ?? null,
      earnedOn: trophy.earned_at === null ? null : earnedOn(trophy.earned_at),
      gameId: trophy.game_id,
    }
  })
}

/** the trophies earned in a game, to announce at its end */
export function earnedInGame(cards: TrophyCard[], gameId: string): TrophyCard[] {
  return cards.filter((card) => card.earned && card.gameId === gameId)
}

/** the nobori given with the reward raised on it, null without a reward */
export function withReward(card: TrophyCard, banner: Banner): Banner | null {
  return card.rewardChange === null ? null : { ...banner, ...card.rewardChange }
}

/** whether the nobori shows the reward already */
export function isWorn(card: TrophyCard, banner: Banner): boolean {
  const change = card.rewardChange

  return (
    change !== null &&
    (change.mon ?? banner.mon) === banner.mon &&
    (change.pattern ?? banner.pattern) === banner.pattern
  )
}

/** "3 trophies earned", "Trophy earned" */
export function earnedHeading(count: number): string {
  return count === 1 ? 'Trophy earned' : `${count} trophies earned`
}
