import type { LoggedMessage, LogParam, StackType } from '@/api/game'
import type { Message } from '@/api/messages'
import type { Card } from '@/api/client'
import { playActions, type HostChoice, type PlayAction, type ProvinceChoice } from '@/lib/cardActions'

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
  /** the message: `look_at_cards`, `look_at_card`… */
  type: string
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

/**
 * The cards the viewer has looked at or revealed in these messages, the oldest first: one inspection per entry, but
 * the provinces of a player, one entry each (`look_at_cards` on the provinces), seen together.
 */
export function inspectionsOf(messages: LoggedMessage[], viewerId: string): Inspection[] {
  return messages.flatMap((message) => {
    const mode = MODES[message.type]
    if (mode === undefined || message.player_id !== viewerId) {
      return []
    }

    const inspections: Inspection[] = []
    for (const entry of message.entries) {
      const params = Array.isArray(entry.params) ? {} : entry.params
      const stack = params.stack
      if (stack?.type !== 'stack' || params.cards?.type !== 'list') {
        continue
      }
      const cards = cardsOf(params.cards)
      const previous = inspections.at(-1)
      if (
        stack.stack_type === 'regular_province' &&
        previous?.stackType === 'regular_province' &&
        previous.ownerId === stack.player_id
      ) {
        previous.cards.push(...cards)
        continue
      }
      inspections.push({
        version: message.version,
        type: message.type,
        mode,
        stackType: stack.stack_type,
        ownerId: stack.player_id,
        cards,
      })
    }

    return inspections
  })
}

/** the cards of the provinces of a player looked at (Governor's Spy): the window redistributes them */
export function isRedistribution(inspection: Inspection): boolean {
  return inspection.type === 'look_at_cards' && inspection.stackType === 'regular_province'
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

/** what a card of a deck can be put into play as, by an effect */
export interface IntoPlay {
  /** the type of the card as printed: a character, an attachment, a holding */
  type: string | null | undefined
  /** whether the controller of the card (the viewer for a card of the opponent) takes part in the conflict */
  conflict: boolean
  /** the characters in play an attachment can go on */
  characters: { id: string; name: string; mine: boolean }[]
  /** the regular provinces of the owner a holding can go into */
  provinces: ProvinceChoice[]
}

/**
 * What can be done with a card looked at or revealed, from where it is: added to its owner's hand (a conflict card of
 * a deck), put into play by an effect from a deck (a character, at home or into the conflict: Favorable Dealbroker,
 * Shinjo Altansarnai; an attachment on a character: Illustrious Forge; a holding into a province: Frontline Engineer),
 * under the control of the viewer for a card of the opponent, put on top of its deck or at its bottom, shuffled into it, put into a province of its owner, face up or
 * face down (a card of the dynasty deck: Walking the Way), discarded, and, once looked at, revealed to the opponent (it
 * can still be acted on then), removed from the game, face up or face down (Stolen Secrets), placed face down under a
 * card of the viewer (Kaiu Shihobu, Daidoji Uji). A province looked at stays where it is: nothing to do.
 *
 * @param side the side of the card (`conflict`, `dynasty`, `province`…), from the cards as printed
 * @param provinces the provinces of the owner of the card
 * @param hosts the cards of the viewer it can be placed under (`hostChoices()`)
 * @param intoPlay what it can be put into play as, from a deck
 */
export function inspectionActions(
  inspection: Inspection,
  card: InspectedCard,
  viewerId: string,
  side: string | null | undefined,
  provinces: ProvinceChoice[] = [],
  hosts: HostChoice[] = [],
  intoPlay: IntoPlay = { type: null, conflict: false, characters: [], provinces: [] },
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
  if (DECKS.includes(inspection.stackType)) {
    actions.push(...intoPlayActions(card, yours ? null : viewerId, intoPlay))
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
  if (inspection.stackType === 'dynasty_draw_deck' && side === 'dynasty') {
    for (const province of provinces) {
      for (const face_up of [true, false]) {
        actions.push({
          type: 'put_card_into_province',
          label: `Into ${province.name}, ${face_up ? 'faceup' : 'facedown'}`,
          done: `In ${province.name}`,
          payload: { ...payload, province_id: province.id, face_up },
        })
      }
    }
  }
  for (const face_down of [false, true]) {
    actions.push({
      type: 'remove_from_game',
      label: `Remove from the game${face_down ? ', facedown' : ''}`,
      done: 'Removed from the game',
      payload: { ...payload, face_down },
    })
  }
  for (const host of hosts) {
    actions.push({
      type: 'place_card_under',
      label: `Under ${host.name}, facedown`,
      done: `Under ${host.name}`,
      payload: { ...payload, host_card_id: host.id, face_down: true },
    })
  }
  if (inspection.mode === 'look') {
    actions.push({ type: 'reveal_card', label: 'Reveal it', done: 'Revealed', stays: true, payload })
  }
  actions.push({ type: 'discard_card', label: 'Discard', done: 'Discarded', danger: true, payload })

  return actions
}

function intoPlayActions(card: InspectedCard, controllerId: string | null, intoPlay: IntoPlay): InspectionAction[] {
  const controller = controllerId === null ? {} : { controller_id: controllerId }
  const control = controllerId === null ? '' : ' under your control'
  switch (intoPlay.type) {
    case 'character':
      return [false, true]
        .filter((into_conflict) => !into_conflict || intoPlay.conflict)
        .map((into_conflict) => ({
          type: 'put_character_into_play',
          label: `Put into play${into_conflict ? ' in the conflict' : ''}${control}`,
          done: into_conflict ? 'In the conflict' : 'In play',
          payload: { card_id: card.id, into_conflict, ...controller },
        }))
    case 'attachment':
      return intoPlay.characters.map((character) => ({
        type: 'put_attachment_into_play',
        label: `Attach to ${character.name}${character.mine ? '' : " (Opp.'s)"}${control}`,
        done: `Attached to ${character.name}`,
        payload: { card_id: card.id, target_card_id: character.id, ...controller },
      }))
    case 'holding':
      return intoPlay.provinces.map((province) => ({
        type: 'put_holding_into_province',
        label: `Into play in ${province.name}`,
        done: `In ${province.name}`,
        payload: { card_id: card.id, province_id: province.id },
      }))
    default:
      return []
  }
}

/**
 * The cards left in a deck lose their ids when a card is shuffled into it: they can no longer be told apart.
 */
export function lostInTheShuffle(inspection: Inspection, action: InspectionAction): boolean {
  return action.type === 'shuffle_into_deck' && DECKS.includes(inspection.stackType)
}

/**
 * The ways a card of a deck looked at or revealed is played, as if it were in hand (Artisan Academy, Pillow Book,
 * Infiltrator, Kyuden Hida…: the engine does not check the effect allowing it): the card of an opponent too, under the
 * control of the viewer.
 */
export function inspectionPlays(inspection: Inspection, card: Card | undefined, available: Set<string>): PlayAction[] {
  return DECKS.includes(inspection.stackType) ? playActions(card, available) : []
}
