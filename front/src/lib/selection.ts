import type { ConflictType, Element } from '@/api/game'

/**
 * What the player picks on the board before declaring a conflict or its defenders.
 */
export interface Selection {
  ring: Element | null
  type: ConflictType | null
  provinceId: string | null
  attackerIds: string[]
  defenderIds: string[]
}

export function emptySelection(): Selection {
  return { ring: null, type: null, provinceId: null, attackerIds: [], defenderIds: [] }
}

export function toggle(ids: string[], id: string): string[] {
  return ids.includes(id) ? ids.filter((candidate) => candidate !== id) : [...ids, id]
}
