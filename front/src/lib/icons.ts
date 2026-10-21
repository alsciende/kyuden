import type { ConflictType, Element } from '@/api/game'

/**
 * The glyphs of the icon font of fiveringsdb-ui (`fiveringsdb.woff`, the `icon` class).
 */
export const ELEMENT_ICONS: Record<Element, string> = {
  air: '',
  earth: '',
  fire: '',
  void: '',
  water: '',
}

export const CONFLICT_ICONS: Record<ConflictType, string> = {
  military: '',
  political: '',
}

export const ELEMENT_NAMES: Record<Element, string> = {
  air: 'Air',
  earth: 'Earth',
  fire: 'Fire',
  void: 'Void',
  water: 'Water',
}

/** the effect of each ring, resolved by the attacker who wins a conflict for it */
export const RING_EFFECTS: Record<Element, string> = {
  air: 'Take 1 honor from the opponent, or gain 2 honor.',
  earth: 'Draw 1 card, and the opponent discards 1 card at random.',
  fire: 'Honor or dishonor a character.',
  void: 'Remove 1 fate from a character.',
  water: 'Ready a character, or bow a character without fate.',
}

export function ringTip(element: Element): string {
  return `${ELEMENT_NAMES[element]} ring · ${RING_EFFECTS[element]}`
}
