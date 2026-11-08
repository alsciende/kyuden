import { computed, inject, type ComputedRef, type InjectionKey } from 'vue'

import type { AbilityTiming, Card } from '@/api/client'
import type { Element, GameView, PlayerView, ResolutionView } from '@/api/game'
import { locate, playerById, type Cards } from '@/lib/board'
import { emphasized, parseCardText, type TextSegment } from '@/lib/cardText'
import { ELEMENT_NAMES, RING_EFFECTS } from '@/lib/icons'

/**
 * The events played and the abilities triggered that a player resolves (`resolutions` of their view), shown to remind
 * them of the effect they apply before they end it, like the effect of the ring of a conflict. The engine does not
 * know which ability of the card is used: the paragraphs of the timing stand out. Characters and attachments played
 * are not resolutions.
 */

export interface ShownResolution {
  cardId: string | null
  card: Card
  timing: AbilityTiming
  /** an event played, else an ability triggered */
  event: boolean
  /** e.g. "Action · ability" */
  label: string
  /** where the card holding the ability is, null for an event */
  where: string | null
  paragraphs: { segments: TextSegment[]; emphasized: boolean }[]
}

export interface Resolving {
  /** the one EndResolution ends */
  current: ShownResolution
  /** the names of the others, in the order they will end */
  then: string[]
}

const TIMING_NAMES: Record<AbilityTiming, string> = {
  action: 'Action',
  interrupt: 'Interrupt',
  reaction: 'Reaction',
}

/**
 * The resolutions in the order EndResolution ends them: the interrupts and reactions first, the last one initiated
 * first, then the action, with its events and abilities (the last one first).
 */
export function endingOrder(resolutions: ResolutionView[]): ResolutionView[] {
  const reversed = [...resolutions].reverse()

  return [
    ...reversed.filter((resolution) => resolution.timing !== 'action'),
    ...reversed.filter((resolution) => resolution.timing === 'action'),
  ]
}

function whereIs(game: GameView, cardId: string | null): string | null {
  const located = cardId === null ? undefined : locate(game, cardId)
  if (located === undefined) {
    return null
  }
  const conflict = game.conflict
  if (conflict?.attacker_ids.includes(cardId!)) {
    return 'Attacking in the conflict'
  }
  if (conflict?.defender_ids.includes(cardId!)) {
    return 'Defending in the conflict'
  }
  switch (located.stack.type) {
    case 'in_play':
      return located.under === null ? 'In play' : 'Attached'
    case 'regular_province':
      return located.under === null ? 'Province' : 'In a province'
    case 'stronghold_province':
      return located.card.side === 'stronghold' ? 'Stronghold' : 'Stronghold province'
    case 'role':
      return 'Role'
    case 'removed_from_game':
      return 'Removed from the game'
    case 'underneath':
      return 'Under a card'
    default:
      return null
  }
}

function show(game: GameView, cards: Cards, resolution: ResolutionView): ShownResolution | null {
  const card = resolution.code === null ? undefined : cards[resolution.code]
  if (card === undefined) {
    return null
  }
  const event = card.type === 'event'
  const paragraphs = parseCardText(card.text)
  const strong = emphasized(paragraphs, resolution.timing)

  return {
    cardId: resolution.card_id,
    card,
    timing: resolution.timing,
    event,
    label: `${TIMING_NAMES[resolution.timing]} · ${event ? 'event' : 'ability'}`,
    where: event ? null : whereIs(game, resolution.card_id),
    paragraphs: paragraphs.map((paragraph, index) => ({ segments: paragraph.segments, emphasized: strong[index]! })),
  }
}

/**
 * What the player resolves, or null when they resolve nothing the viewer can name (a character played, a card no
 * longer in sight).
 */
export function resolvingOf(game: GameView, player: PlayerView, cards: Cards): Resolving | null {
  const [first, ...others] = endingOrder(player.resolutions)
  const current = first === undefined ? null : show(game, cards, first)
  if (current === null) {
    return null
  }

  return {
    current,
    then: others.map((resolution) => (resolution.code === null ? undefined : cards[resolution.code]?.name) ?? 'a card'),
  }
}

/** the effect of the contested ring, resolved by the attacker who won the conflict */
export interface RingResolving {
  player: PlayerView
  element: Element
  /** e.g. "Air ring" */
  name: string
  text: string
}

/**
 * The effect of the contested ring the attacking player resolves, shown where the effects of the cards are: the game
 * only stays in `resolve_ring_effects` when the attacker won the conflict, until they pass.
 */
export function ringResolving(game: GameView): RingResolving | null {
  const conflict = game.conflict
  if (
    game.place !== 'resolve_ring_effects' ||
    conflict === null ||
    !game.active_player_ids.includes(conflict.attacking_player_id)
  ) {
    return null
  }
  const player = playerById(game, conflict.attacking_player_id)
  if (player === undefined) {
    return null
  }

  return {
    player,
    element: conflict.ring,
    name: `${ELEMENT_NAMES[conflict.ring]} ring`,
    text: RING_EFFECTS[conflict.ring],
  }
}

/**
 * What the other players resolve, as the viewer sees it: the opponent of a player, every player for a spectator (who
 * has no panel of actions).
 */
export function othersResolving(game: GameView, cards: Cards): { player: PlayerView; resolving: Resolving }[] {
  return game.players
    .filter((player) => game.spectating || player.id !== game.viewer_id)
    .flatMap((player) => {
      const resolving = resolvingOf(game, player, cards)

      return resolving === null ? [] : [{ player, resolving }]
    })
}

/**
 * The cards whose events or abilities the players resolve now (the one EndResolution ends for each), marked on the
 * board.
 */
export function resolvingCardIds(game: GameView, cards: Cards): Set<string> {
  const ids = game.players.map((player) => resolvingOf(game, player, cards)?.current.cardId ?? null)

  return new Set(ids.filter((id): id is string => id !== null))
}

/** provided by the page of the game to the cards of the board */
export const RESOLVING_CARDS: InjectionKey<ComputedRef<Set<string>>> = Symbol('resolving-cards')

export function useResolvingCards(): ComputedRef<Set<string>> {
  return inject(RESOLVING_CARDS, () => computed(() => new Set<string>()), true)
}
