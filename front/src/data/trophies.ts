import type { TrophyId } from '@/api/client'
import { CLANS } from '@/data/clans'

/**
 * The trophies of the catalogue (TROPHIES.md), in its order: a name in the spirit of Rokugan and the goal written
 * plainly under it. Their rewards come from the API, which awards them.
 */
export interface TrophyText {
  id: TrophyId
  name: string
  goal: string
}

export const TROPHIES: TrophyText[] = [
  { id: 'house_bot_game', name: 'Guest of the Palace', goal: 'Finish a game against Kyūden no Karō.' },
  ...CLANS.map((clan): TrophyText => ({
    id: `clan_${clan.id}`,
    name: `Champion of the ${clan.name}`,
    goal: `Win a standard game with a ${clan.name} stronghold.`,
  })),
]

/** a trophy the client does not know (a newer API) is named by its value */
export function trophyText(id: string): TrophyText {
  return TROPHIES.find((trophy) => trophy.id === id) ?? { id: id as TrophyId, name: id, goal: '' }
}
