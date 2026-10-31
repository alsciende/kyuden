import type { Element, GameView } from '@/api/game'
import { rings, type Ring } from '@/lib/board'
import type { Effect } from '@/lib/playerBar'

/**
 * The window of a ring, opened from the bar: where the ring is, and the effects on it the engine does not apply itself
 * (claim it, switch it with another ring, move fate from or to it), for the costs and effects of the cards.
 */

/** who holds a ring in their claimed ring pool, null for the unclaimed ring pool or the contested ring */
function holderOf(game: GameView, ring: Ring): string | null {
  return game.rings[ring.element]?.claimed_by_id ?? null
}

/**
 * The rings a ring can switch places with, as the engine accepts it: a ring of another place, and the contested ring
 * only with a ring of the unclaimed or claimed ring pool of the attacking player.
 */
export function ringsToSwitchWith(game: GameView, element: Element): Ring[] {
  const all = rings(game)
  const ring = all.find((candidate) => candidate.element === element)
  if (ring === undefined) {
    return []
  }

  return all.filter((other) => {
    if (other.element === element) {
      return false
    }
    if (ring.status !== 'contested' && other.status !== 'contested') {
      return holderOf(game, other) !== holderOf(game, ring)
    }
    const incoming = ring.status === 'contested' ? other : ring
    const holder = holderOf(game, incoming)

    return holder === null || holder === game.conflict?.attacking_player_id
  })
}

/**
 * The effects on a ring, with an amount of fate: claiming a ring nobody has claimed (in the type of the conflict being
 * resolved, else in the type chosen), taking the fate on it, placing fate on it from the fate pool of the viewer; only
 * those the engine makes available now.
 */
export function ringEffects(game: GameView, element: Element, amount: number): Effect[] {
  const ring = game.rings[element]
  if (ring === undefined) {
    return []
  }
  const viewer = game.viewer_id
  const opponent = game.players.find((player) => player.id !== viewer)
  const claim = (targetPlayerId: string, label: string, conflictType?: 'military' | 'political'): Effect => ({
    type: 'claim_ring',
    label,
    payload: { ring: element, target_player_id: targetPlayerId, ...(conflictType && { conflict_type: conflictType }) },
  })
  let claims: (Effect | undefined)[] = []
  if (ring.claimed_by_id === null) {
    claims =
      game.conflict === null
        ? [claim(viewer, 'Claim it, military', 'military'), claim(viewer, 'Claim it, political', 'political')]
        : [claim(viewer, 'Claim it'), opponent && claim(opponent.id, 'Opp. claims it')]
  }
  const effects: (Effect | undefined | false)[] = [
    ...claims,
    ring.fate > 0 && {
      type: 'move_fate',
      label: `Take ${amount} fate`,
      payload: { source: 'ring', source_id: element, target: 'player', target_id: viewer, amount },
    },
    {
      type: 'move_fate',
      label: `Place ${amount} from your pool`,
      payload: { source: 'player', source_id: viewer, target: 'ring', target_id: element, amount },
    },
  ]
  const available = new Set(game.available_actions)

  return effects.filter((effect): effect is Effect => !!effect && available.has(effect.type))
}
