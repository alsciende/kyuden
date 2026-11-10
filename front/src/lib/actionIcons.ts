import type { Message } from '@/api/messages'

/**
 * The icons of the actions on a card, drawn in a row of square buttons whose label is their tooltip. Paths of Lucide
 * (lucide.dev, v1.66.0, ISC license), on a grid of 24, stroked.
 */
export const ACTION_ICONS = {
  point: [
    'M22 14a8 8 0 0 1-8 8',
    'M18 11v-1a2 2 0 0 0-2-2a2 2 0 0 0-2 2',
    'M14 10V9a2 2 0 0 0-2-2a2 2 0 0 0-2 2v1',
    'M10 9.5V4a2 2 0 0 0-2-2a2 2 0 0 0-2 2v10',
    'M18 11a2 2 0 1 1 4 0v3a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15',
  ],
  top: ['M5 3h14', 'm18 13-6-6-6 6', 'M12 7v14'],
  bottom: ['M12 17V3', 'm6 11 6 6 6-6', 'M19 21H5'],
  shuffle: [
    'm18 14 4 4-4 4',
    'm18 2 4 4-4 4',
    'M2 18h1.973a4 4 0 0 0 3.3-1.7l5.454-8.6a4 4 0 0 1 3.3-1.7H22',
    'M2 6h1.972a4 4 0 0 1 3.6 2.2',
    'M22 18h-6.041a4 4 0 0 1-3.3-1.8l-.359-.45',
  ],
  discard: [
    'M10 11v6',
    'M14 11v6',
    'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6',
    'M3 6h18',
    'M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
  ],
  hand: ['M9 14 4 9l5-5', 'M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11'],
  sacrifice: ['M12 3q1 4 4 6.5t3 5.5a1 1 0 0 1-14 0 5 5 0 0 1 1-3 1 1 0 0 0 5 0c0-2-1.5-3-1.5-5q0-2 2.5-4'],
  control: [
    'M18 11.5V9a2 2 0 0 0-2-2a2 2 0 0 0-2 2v1.4',
    'M14 10V8a2 2 0 0 0-2-2a2 2 0 0 0-2 2v2',
    'M10 9.9V9a2 2 0 0 0-2-2a2 2 0 0 0-2 2v5',
    'M6 14a2 2 0 0 0-2-2a2 2 0 0 0-2 2',
    'M18 11a2 2 0 1 1 4 0v3a8 8 0 0 1-8 8h-4a8 8 0 0 1-8-8 2 2 0 1 1 4 0',
  ],
  duplicate: [
    'M4 16a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2',
    'M10 8h10a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H10a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2z',
    'm12.5 12.5 5 5',
    'm12.5 17.5 5-5',
  ],
} as const satisfies Record<string, readonly string[]>

export type ActionIconName = keyof typeof ACTION_ICONS

/** the label written under the icon on a touch screen, where its tooltip does not show */
export const ACTION_SHORT_LABELS: Record<ActionIconName, string> = {
  point: 'Point',
  top: 'Top',
  bottom: 'Bottom',
  shuffle: 'Shuffle',
  discard: 'Discard',
  hand: 'To hand',
  sacrifice: 'Sacrifice',
  control: 'Control',
  duplicate: 'Duplicate',
}

/** the icon of an action on a card, none for an action that keeps its label */
export function actionIcon(action: Message): ActionIconName | null {
  switch (action.type) {
    case 'point_at_card':
      return 'point'
    case 'put_card_on_deck':
      return action.payload.position === 'bottom' ? 'bottom' : 'top'
    case 'shuffle_into_deck':
      return 'shuffle'
    case 'discard_card':
      return 'discard'
    case 'return_card_to_hand':
    case 'add_card_to_hand':
      return 'hand'
    case 'sacrifice_card':
      return 'sacrifice'
    case 'discard_duplicate':
      return 'duplicate'
    case 'take_control':
      return 'control'
    default:
      return null
  }
}
