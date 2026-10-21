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
