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

/** the size of the bracket of a tournament won, and the name of its trophy */
const TOURNAMENT_WINS: [2 | 4 | 8 | 16 | 32 | 64, string][] = [
  [2, 'Victor of the Duel Ground'],
  [4, 'Victor of the Dojo'],
  [8, 'Victor of the Province'],
  [16, 'Champion of the Winter Court'],
  [32, 'Topaz Champion'],
  [64, 'Emerald Champion'],
]

export const TROPHIES: TrophyText[] = [
  { id: 'house_bot_game', name: 'Guest of the Palace', goal: 'Finish a game against Kyūden no Karō.' },
  ...CLANS.map((clan): TrophyText => ({
    id: `clan_${clan.id}`,
    name: `Champion of the ${clan.name}`,
    goal: `Win a standard game with a ${clan.name} stronghold.`,
  })),
  { id: 'tournament_game', name: 'Into the Lists', goal: 'Finish a game of a tournament.' },
  ...TOURNAMENT_WINS.map(([size, name]): TrophyText => ({
    id: `tournament_win_${size}`,
    name,
    goal: `Win a tournament with a bracket of ${size}.`,
  })),
]

/** a trophy the client does not know (a newer API) is named by its value */
export function trophyText(id: string): TrophyText {
  return TROPHIES.find((trophy) => trophy.id === id) ?? { id: id as TrophyId, name: id, goal: '' }
}
