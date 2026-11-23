import type { AbilityWindowView, GameView } from '@/api/game'
import { locate, playerById, viewer, type Cards } from '@/lib/board'

/**
 * The interrupt window the engine waits for (`ability_windows` of timing `interrupt`, the first of the view): before
 * a triggering condition occurs (a character would leave play…), the players asked interrupt it or pass. It is shown to
 * every player, asked or not: the game waits for it.
 *
 * A player asked about a card out of sight of the opponent (an event in the hand or the deck) has their client pass
 * for them after a while (`timed`), whether they hold the card or not, so that the time they take tells nothing.
 */

/** the seconds before the client passes for the viewer, asked about a card out of sight */
export const PASS_AFTER = 6

/** a card the viewer can interrupt with */
export interface ShownInterruptOffer {
  cardId: string
  name: string
  /** what the card is and, when it matters, where it is: "event · hand" */
  kind: string
  forced: boolean
}

export interface ShownInterruptWindow {
  id: string
  /** what is about to happen: "Hida Kisada would leave play" */
  trigger: string
  /** the stage, as a label: "Would", "Forced", "Interrupt" */
  stage: string
  offers: ShownInterruptOffer[]
  /** the window waits for the viewer: they interrupt or pass */
  mine: boolean
  /** the other players it waits for, by name */
  waitingFor: string[]
  /** the client passes for the viewer after PASS_AFTER seconds */
  timed: boolean
}

function cardName(game: GameView, cards: Cards, cardId: string | undefined): string | null {
  const code = cardId === undefined ? null : (locate(game, cardId)?.card.code ?? null)

  return code === null ? null : (cards[code]?.name ?? null)
}

/** the card a condition announces, by its code: an event in the hand is revealed by the window */
function announcedName(cards: Cards, code: string | undefined): string {
  return (code === undefined ? undefined : cards[code]?.name) ?? 'a card'
}

/** what the window is about to let happen, as the cards word it */
export function imminentText(window: AbilityWindowView, game: GameView, cards: Cards): string {
  const player = playerById(game, window.params.player ?? '')?.name ?? 'A player'
  switch (window.trigger) {
    case 'card_leaving_play':
      return `${cardName(game, cards, window.params.card) ?? 'A card'} would leave play`
    case 'province_breaking':
      return `${cardName(game, cards, window.params.card) ?? 'A province'} would be broken`
    case 'effects_initiating':
      return window.params.kind === 'event'
        ? `${player} is about to play ${announcedName(cards, window.params.code)}`
        : `${player} is about to trigger ${announcedName(cards, window.params.code)}`
    case 'attachment_being_played':
      return `${player} is about to play ${announcedName(cards, window.params.code)} on ${cardName(game, cards, window.params.target) ?? 'a character'}`
    case 'phase_beginning':
      return `The ${window.params.phase ?? ''} phase would begin`
    case 'phase_ending':
      return `The ${window.params.phase ?? ''} phase would end`
    case 'honor_losing':
      return `${player} would lose ${window.params.amount ?? ''} honor`
    case 'conflict_losing':
      return `${player} would lose the conflict`
    case 'glory_count_beginning':
      return 'The glory count would begin'
    case 'conflict_declaring':
      return `${player} is about to declare a conflict against ${cardName(game, cards, window.params.province) ?? 'a province'}`
    case 'ring_effect_resolving':
      return `${player} would resolve the ${window.params.ring ?? ''} ring effect`
    case 'character_honoring':
      return `${cardName(game, cards, window.params.card) ?? 'A character'} would be honored`
    case 'character_dishonoring':
      return `${cardName(game, cards, window.params.card) ?? 'A character'} would be dishonored`
    case 'fate_removing':
      return `${window.params.amount ?? ''} fate would be removed from ${cardName(game, cards, window.params.card) ?? 'a character'}`
    case 'first_player_token_passing':
      return `${player} would pass the first player token`
    case 'ring_returning':
      return `The ${window.params.ring ?? ''} ring would return to the unclaimed pool`
    case 'duel_resolving':
      return 'The duel would resolve'
    case 'cards_drawing':
      return `${player} would draw ${window.params.amount === '1' ? 'a card' : `${window.params.amount ?? ''} cards`}`
    default:
      return 'Something is about to happen'
  }
}

const STAGES: Record<string, string> = { would: 'Would', forced: 'Forced', standard: 'Interrupt' }

/** the interrupt window open, as the viewer sees it; none for a spectator nor in a replay */
export function shownInterruptWindow(game: GameView, cards: Cards): ShownInterruptWindow | null {
  if (game.spectating || game.replaying) {
    return null
  }
  const window = viewer(game).ability_windows.find((candidate) => candidate.timing === 'interrupt')
  if (window === undefined) {
    return null
  }
  const offers = window.card_ids.flatMap((cardId): ShownInterruptOffer[] => {
    const located = locate(game, cardId)
    if (located === undefined) {
      return []
    }
    const card = located.card.code === null ? undefined : cards[located.card.code]
    const type = card?.type ?? 'card'

    return [
      {
        cardId,
        name: card?.name ?? 'Card',
        kind: located.stack.type === 'hand' ? `${type} · hand` : type,
        forced: window.forced_card_ids.includes(cardId),
      },
    ]
  })
  const awaited = window.awaited_player_ids ?? []

  return {
    id: window.id,
    trigger: imminentText(window, game, cards),
    stage: STAGES[window.stage ?? 'standard'] ?? 'Interrupt',
    offers,
    mine: awaited.includes(game.viewer_id ?? ''),
    waitingFor: awaited
      .filter((id) => id !== game.viewer_id)
      .map((id) => playerById(game, id)?.name ?? 'Your opponent'),
    timed: window.timed === true && offers.every((offer) => !offer.forced),
  }
}
