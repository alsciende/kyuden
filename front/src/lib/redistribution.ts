import type { GameView, PlayerView } from '@/api/game'
import type { MessagePayloads } from '@/api/messages'
import { locate, playerById, provinces, type Cards } from '@/lib/board'
import type { Inspection } from '@/lib/inspection'

/**
 * The redistribution of the cards of the provinces of a player (Governor's Spy): once the viewer has looked at them
 * (`look_at_cards` on the provinces), they choose the province of each card, then put them all back face down
 * (`redistribute_province_cards`): the cards get new ids, and the opponent does not see where each one went. The
 * engine does not check that each province keeps a card: the window says so.
 */

/** a regular province of the player, a column of the window */
export interface RedistributionProvince {
  /** the id of the province card, sent in the message */
  id: string
  name: string
}

/** a card of the provinces looked at */
export interface RedistributedCard {
  id: string
  code: string
  /** the province it is in now */
  fromId: string
  /** whether it was face up: it goes back face down */
  faceUp: boolean
}

/** the province chosen for each card, by the id of the card */
export type Placement = Record<string, string>

/** the regular provinces of the player, by their province card: the stronghold province takes no part */
export function redistributionProvinces(player: PlayerView, cards: Cards): RedistributionProvince[] {
  return provinces(player).flatMap((province, index) =>
    province.stronghold === null && province.province !== null
      ? [
          {
            id: province.province.id,
            name: (province.province.code !== null && cards[province.province.code]?.name) || `Province ${index + 1}`,
          },
        ]
      : [],
  )
}

/** the cards looked at still in a regular province of their owner, with where they are */
export function redistributedCards(game: GameView, inspection: Inspection): RedistributedCard[] {
  return inspection.cards.flatMap((card) => {
    const located = locate(game, card.id)
    if (located === undefined || located.stack.type !== 'regular_province' || located.under === null) {
      return []
    }

    return [{ id: card.id, code: card.code, fromId: located.under.id, faceUp: located.card.orientation === 'face_up' }]
  })
}

/** every card where it is */
export function initialPlacement(cards: RedistributedCard[]): Placement {
  return Object.fromEntries(cards.map((card) => [card.id, card.fromId]))
}

/** the provinces left without a card: each must end up with one (the text of Governor's Spy) */
export function emptyProvinces(provinces: RedistributionProvince[], placement: Placement): RedistributionProvince[] {
  const filled = new Set(Object.values(placement))

  return provinces.filter((province) => !filled.has(province.id))
}

/** the message putting the cards back, in the order of the cards */
export function redistributionPayload(
  ownerId: string,
  cards: RedistributedCard[],
  placement: Placement,
): MessagePayloads['redistribute_province_cards'] {
  return {
    target_player_id: ownerId,
    card_ids: cards.map((card) => card.id),
    province_ids: cards.map((card) => placement[card.id] ?? card.fromId),
  }
}

/** the dynasty cards in the regular provinces of a player, and the provinces holding some: "5 cards in 4 provinces" */
export function provinceCardsCount(player: PlayerView): { cards: number; provinces: number } {
  const regular = provinces(player).filter((province) => province.stronghold === null)
  const counts = regular.map((province) => province.cards.filter((card) => card.side === 'dynasty').length)

  return { cards: counts.reduce((sum, count) => sum + count, 0), provinces: counts.filter((count) => count > 0).length }
}

/** the owner of the cards looked at */
export function ownerOf(game: GameView, inspection: Inspection): PlayerView | undefined {
  return playerById(game, inspection.ownerId)
}
