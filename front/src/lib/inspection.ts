import type { LoggedMessage, LogParam, StackType } from '@/api/game'
import type { Message } from '@/api/messages'

/**
 * The cards a player looks at or reveals (`look_at_cards`, `look_at_card`, `reveal_cards`, `reveal_card`): the engine
 * records nothing, the log gives the cards to the player, whose client keeps them in a window of their own until the
 * player closes it, once they have resolved the effect with the actions on the cards.
 */

export type InspectionMode = 'look' | 'reveal'

const MODES: Record<string, InspectionMode> = {
  look_at_cards: 'look',
  look_at_card: 'look',
  reveal_cards: 'reveal',
  reveal_card: 'reveal',
}

export interface InspectedCard {
  id: string
  code: string
}

export interface Inspection {
  /** the version of the game the message led to */
  version: number
  mode: InspectionMode
  /** where the cards are: a deck (from its top), a hand, a province */
  stackType: StackType
  ownerId: string
  cards: InspectedCard[]
}

function cardsOf(param: LogParam | undefined): InspectedCard[] {
  if (param?.type !== 'list') {
    return []
  }

  return param.items.flatMap((item) =>
    item.type === 'card' && item.id !== null && item.code !== null ? [{ id: item.id, code: item.code }] : [],
  )
}

/** the cards the viewer has looked at or revealed in these messages, the oldest first */
export function inspectionsOf(messages: LoggedMessage[], viewerId: string): Inspection[] {
  return messages.flatMap((message) => {
    const mode = MODES[message.type]
    if (mode === undefined || message.player_id !== viewerId) {
      return []
    }

    return message.entries.flatMap((entry) => {
      const params = Array.isArray(entry.params) ? {} : entry.params
      const stack = params.stack
      if (stack?.type !== 'stack' || params.cards?.type !== 'list') {
        return []
      }

      return [
        {
          version: message.version,
          mode,
          stackType: stack.stack_type,
          ownerId: stack.player_id,
          cards: cardsOf(params.cards),
        },
      ]
    })
  })
}

export type InspectionAction = Message & {
  label: string
  /** what the card has become, shown on it once done */
  done: string
  /** the card stays where it is and can still be acted on (revealed) */
  stays?: boolean
  primary?: boolean
  danger?: boolean
}

const DECKS: StackType[] = ['conflict_draw_deck', 'dynasty_draw_deck']

/**
 * What can be done with a card looked at or revealed, from where it is: added to its owner's hand (a conflict card of
 * a deck), put on top of its deck or at its bottom, shuffled into it, discarded, and, once looked at, revealed to the
 * opponent (it can still be acted on then). A province looked at stays where it is: nothing to do.
 *
 * @param side the side of the card (`conflict`, `dynasty`, `province`…), from the cards as printed
 */
export function inspectionActions(
  inspection: Inspection,
  card: InspectedCard,
  viewerId: string,
  side: string | null | undefined,
): InspectionAction[] {
  if (side === 'province') {
    return []
  }
  const payload = { card_id: card.id }
  const yours = inspection.ownerId === viewerId
  const actions: InspectionAction[] = []
  if (inspection.stackType === 'conflict_draw_deck') {
    actions.push({
      type: 'add_card_to_hand',
      label: yours ? 'Add to your hand' : "Add to Opp.'s hand",
      done: yours ? 'In your hand' : "In Opp.'s hand",
      primary: true,
      payload,
    })
  }
  const deck = DECKS.includes(inspection.stackType) ? 'the deck' : 'its deck'
  actions.push(
    {
      type: 'put_card_on_deck',
      label: `On top of ${deck}`,
      done: `On top of ${deck}`,
      payload: { ...payload, position: 'top' },
    },
    {
      type: 'put_card_on_deck',
      label: 'At the bottom',
      done: 'At the bottom',
      payload: { ...payload, position: 'bottom' },
    },
    { type: 'shuffle_into_deck', label: `Shuffle into ${deck}`, done: 'Shuffled in', payload },
  )
  if (inspection.mode === 'look') {
    actions.push({ type: 'reveal_card', label: 'Reveal it', done: 'Revealed', stays: true, payload })
  }
  actions.push({ type: 'discard_card', label: 'Discard', done: 'Discarded', danger: true, payload })

  return actions
}

/**
 * The cards left in a deck lose their ids when a card is shuffled into it: they can no longer be told apart.
 */
export function lostInTheShuffle(inspection: Inspection, action: InspectionAction): boolean {
  return action.type === 'shuffle_into_deck' && DECKS.includes(inspection.stackType)
}
