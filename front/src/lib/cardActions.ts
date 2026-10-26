import type { AbilityTiming, Card } from '@/api/client'
import type { GameView } from '@/api/game'
import type { Message, MessagePayloads, MessageType } from '@/api/messages'
import { locate, type Cards, type Located } from '@/lib/board'

/**
 * What a player can do with a card, from where it is and from the actions the engine makes available now. Board
 * manipulations apply to the cards of every player: the engine does not know which ability is being resolved.
 */

export type SimpleAction = Message & { label: string }

/** the messages playing a card, by the way it is played */
interface PlayTypes {
  from_province: 'play_character_from_province'
  from_hand: 'play_character_from_hand'
  attachment: 'play_attachment'
  event: 'play_action_event' | 'play_interrupt_event' | 'play_reaction_event'
}

export type PlayKind = keyof PlayTypes

export type PlayAction = {
  [K in PlayKind]: {
    type: PlayTypes[K]
    kind: K
    label: string
    /** the printed cost, prefilled */
    cost: number
  }
}[PlayKind]

export interface CardMenu {
  located: Located
  card: Card | undefined
  /** whose card it is, for the viewer */
  mine: boolean
  abilities: SimpleAction[]
  plays: PlayAction[]
  state: SimpleAction[]
  /** the skills of a character in play can be recorded */
  skill: boolean
  /** a character in play may challenge a character of the opponent */
  duel: boolean
  /** the ways it leaves where it is: returned to hand, sacrificed, discarded */
  removals: SimpleAction[]
  /** pointing at it to show it to the opponent: a card every player sees (not in a hand nor a deck) */
  point: SimpleAction | null
}

const ABILITY_TYPES: Record<
  AbilityTiming,
  'trigger_action_ability' | 'trigger_interrupt_ability' | 'trigger_reaction_ability'
> = {
  action: 'trigger_action_ability',
  interrupt: 'trigger_interrupt_ability',
  reaction: 'trigger_reaction_ability',
}

/** the stacks whose cards some players do not see: nobody can point at them */
const HIDDEN_STACKS = new Set(['hand', 'conflict_draw_deck', 'dynasty_draw_deck'])

const EVENT_TYPES: Record<AbilityTiming, PlayTypes['event']> = {
  action: 'play_action_event',
  interrupt: 'play_interrupt_event',
  reaction: 'play_reaction_event',
}

function cardOf(cards: Cards, code: string | null): Card | undefined {
  return code === null ? undefined : cards[code]
}

/**
 * The timings of the abilities used through a card: its own, and those its attachments grant to it; none for an
 * attachment whose abilities belong to the card it is attached to.
 */
export function abilityTimings(located: Located, cards: Cards): AbilityTiming[] {
  const card = cardOf(cards, located.card.code)
  if (located.stack.type === 'in_play' && located.under !== null && card?.ability_granted_to_attached) {
    return []
  }
  const timings = new Set(card?.ability_timings ?? [])
  if (located.stack.type === 'in_play' && located.under === null) {
    for (const attachment of (located.stack.cards ?? []).slice(1)) {
      const attached = cardOf(cards, attachment.code)
      if (attached?.ability_granted_to_attached) {
        attached.ability_timings?.forEach((timing) => timings.add(timing))
      }
    }
  }

  return (['action', 'interrupt', 'reaction'] as const).filter((timing) => timings.has(timing))
}

/**
 * A unique character in play of the player with the title of the card: the card is its duplicate, discarded to place 1
 * fate on it (step 1.4).
 */
function copyInPlay(located: Located, cards: Cards, card: Card | undefined): string | undefined {
  if (card?.type !== 'character') {
    return undefined
  }

  return located.player.stacks
    .filter((stack) => stack.type === 'in_play')
    .map((stack) => (stack.cards ?? [])[0])
    .find((character) => {
      const printed = character === undefined ? undefined : cardOf(cards, character.code)

      return printed?.uniqueness === true && printed.name === card.name
    })?.id
}

function abilityLabel(timing: AbilityTiming, count: number): string {
  return count === 1 ? 'Activate its ability' : `Activate its ${timing}`
}

export function cardMenu(game: GameView, cards: Cards, cardId: string): CardMenu | undefined {
  const located = locate(game, cardId)
  if (located === undefined) {
    return undefined
  }
  const available = new Set(game.available_actions)
  const { stack, card: view, under } = located
  const card = cardOf(cards, view.code)
  const faceUp = view.orientation !== 'face_down'
  const id = view.id

  const menu: CardMenu = {
    located,
    card,
    mine: located.player.id === game.viewer_id,
    abilities: [],
    plays: [],
    state: [],
    skill: false,
    duel: false,
    removals: [],
    point:
      available.has('point_at_card') && !HIDDEN_STACKS.has(stack.type)
        ? { type: 'point_at_card', label: 'Point at it', payload: { card_id: id } }
        : null,
  }
  // the payload is the card by default, for the messages whose payload is a card only
  const add = <T extends MessageType>(
    list: SimpleAction[],
    type: T,
    label: string,
    ...[payload]: { card_id: string } extends MessagePayloads[T] ? [MessagePayloads[T]?] : [MessagePayloads[T]]
  ) => {
    if (available.has(type)) {
      list.push({ type, label, payload: payload ?? { card_id: id } } as SimpleAction)
    }
  }
  const discard = () => add(menu.removals, 'discard_card', 'Discard')
  const discardDuplicate = () => {
    const copyId = menu.mine ? copyInPlay(located, cards, card) : undefined
    if (copyId !== undefined) {
      add(menu.removals, 'discard_duplicate', 'Discard as a duplicate', { card_id: id, copy_id: copyId })
    }
  }
  const sacrifice = () => add(menu.removals, 'sacrifice_card', 'Sacrifice')
  const bowOrReady = () =>
    view.orientation === 'sideways' ? add(menu.state, 'ready_card', 'Ready') : add(menu.state, 'bow_card', 'Bow')
  const abilities = () => {
    const timings = abilityTimings(located, cards)
    timings.forEach((timing) => add(menu.abilities, ABILITY_TYPES[timing], abilityLabel(timing, timings.length)))
  }
  const cost = card?.cost ?? 0

  switch (stack.type) {
    case 'hand':
      if (card?.type === 'character' && available.has('play_character_from_hand')) {
        menu.plays.push({ type: 'play_character_from_hand', kind: 'from_hand', label: 'Play this character', cost })
      } else if (card?.type === 'attachment' && available.has('play_attachment')) {
        menu.plays.push({ type: 'play_attachment', kind: 'attachment', label: 'Attach it', cost })
      } else if (card?.type === 'event') {
        const timings = card.ability_timings ?? []
        for (const timing of timings) {
          if (available.has(EVENT_TYPES[timing])) {
            const label =
              timings.length === 1 ? 'Play this event' : `Play it as ${timing === 'action' ? 'an' : 'a'} ${timing}`
            menu.plays.push({ type: EVENT_TYPES[timing], kind: 'event', label, cost })
          }
        }
      }
      discardDuplicate()
      discard()
      break

    case 'regular_province':
      if (under === null) {
        // the province itself
        if (faceUp) {
          abilities()
        } else {
          add(menu.state, 'reveal_province', 'Reveal it')
        }
        if ((stack.cards?.length ?? 0) <= 1) {
          add(menu.state, 'fill_province', 'Fill this province', { province_id: id })
        }
      } else if (!faceUp) {
        add(menu.state, 'reveal_dynasty_card', 'Turn it face up')
        discard()
      } else {
        if (card?.type === 'character' && available.has('play_character_from_province')) {
          menu.plays.push({
            type: 'play_character_from_province',
            kind: 'from_province',
            label: 'Play this character',
            cost,
          })
        }
        // a character has abilities once in play; a holding has them in its province
        if (card?.type !== 'character') {
          abilities()
        }
        if (card?.type === 'holding') {
          sacrifice()
        }
        discardDuplicate()
        discard()
      }
      break

    case 'stronghold_province':
      if ((stack.cards ?? [])[0]?.id === id) {
        // the stronghold
        abilities()
        bowOrReady()
      } else if (game.place === 'choose_stronghold') {
        add(menu.state, 'choose_stronghold_province', 'Put it under your stronghold')
      } else if (faceUp) {
        abilities()
      } else {
        add(menu.state, 'reveal_province', 'Reveal it')
      }
      break

    case 'role':
      abilities()
      break

    case 'in_play':
      abilities()
      bowOrReady()
      if (under === null) {
        if (view.personal_honor !== 'honored') {
          add(menu.state, 'honor_character', 'Honor')
        }
        if (view.personal_honor !== 'dishonored') {
          add(menu.state, 'dishonor_character', 'Dishonor')
        }
        add(menu.state, 'place_fate', 'Place 1 fate', { card_id: id, amount: 1 })
        if (view.fate > 0) {
          add(menu.state, 'remove_fate', 'Remove 1 fate', { card_id: id, amount: 1 })
        }
        if (game.conflict !== null) {
          const participating = [...game.conflict.attacker_ids, ...game.conflict.defender_ids].includes(id)
          if (participating) {
            add(menu.state, 'move_home', 'Send home')
          } else {
            add(menu.state, 'move_to_conflict', 'Move to the conflict')
          }
          menu.duel = participating && available.has('initiate_duel')
        }
        menu.skill = available.has('set_character_skill')
      }
      add(menu.removals, 'return_card_to_hand', 'Return to hand')
      sacrifice()
      discard()
      break

    default:
      break
  }

  return menu
}
