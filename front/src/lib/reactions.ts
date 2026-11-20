import { ref, watch, type Ref } from 'vue'

import type { Element, GameView, ReactionWindowView } from '@/api/game'
import { locate, viewer, type Cards } from '@/lib/board'
import { ELEMENT_NAMES } from '@/lib/icons'

/**
 * The reactions the engine offers the viewer: once a triggering condition has occurred (a province revealed…), a
 * reaction window lists the cards of the player that can react to it (`reaction_windows` of their view). The window
 * blocks nothing: the player activates a card (the engine then withdraws it), declines it, or ignores it until they
 * pass.
 *
 * Declining stays in the client, so that the opponent does not learn that the player had a card to react with: the
 * cards declined are kept by the tab, for each game, and a reload does not offer them again; another device does.
 */

/** a card offered in a reaction window */
export interface ShownOffer {
  windowId: string
  cardId: string
  name: string
  /** what the card is and, when it matters, where it is: "event · hand" */
  kind: string
}

/** a reaction window, with the cards still offered */
export interface ShownWindow {
  id: string
  /** the triggering condition: "After Night Raid is revealed" */
  trigger: string
  offers: ShownOffer[]
}

export function declineKey(windowId: string, cardId: string): string {
  return `${windowId}:${cardId}`
}

function cardName(game: GameView, cards: Cards, cardId: string | undefined): string | null {
  const code = cardId === undefined ? null : (locate(game, cardId)?.card.code ?? null)

  return code === null ? null : (cards[code]?.name ?? null)
}

/** the player a triggering condition names, as its subject: "you win", "your opponent wins" */
function subject(game: GameView, playerId: string | undefined, plural: string, singular: string): string {
  return playerId === game.viewer_id ? `you ${plural}` : `your opponent ${singular}`
}

/** the triggering condition of a window, as the cards word it */
export function triggerText(window: ReactionWindowView, game: GameView, cards: Cards): string {
  switch (window.trigger) {
    case 'province_revealed': {
      const name = cardName(game, cards, window.params.province)

      return name === null ? 'After a province is revealed' : `After ${name} is revealed`
    }
    case 'conflict_declared':
    case 'conflict_declaration_completed':
      return `After ${subject(game, window.params.attacker, 'declare', 'declares')} a conflict`
    case 'province_broken': {
      const name = cardName(game, cards, window.params.province)

      return `After ${subject(game, window.params.breaker, 'break', 'breaks')} ${name ?? 'a province'}`
    }
    case 'honor_dials_revealed':
      return window.params.bid === 'duel'
        ? 'After the honor dials of the duel are revealed'
        : 'After the honor dials are revealed'
    case 'conflict_passed':
      return `After ${subject(game, window.params.player, 'pass', 'passes')} on declaring a conflict`
    case 'ring_claimed': {
      const ring = ELEMENT_NAMES[window.params.ring as Element] ?? 'a'

      return `After ${subject(game, window.params.player, 'claim', 'claims')} the ${ring} ring`
    }
    case 'defenders_declared':
      return `After ${subject(game, window.params.player, 'declare', 'declares')} defenders`
    case 'duel_won':
      return `After ${subject(game, window.params.player, 'win', 'wins')} the duel`
    case 'duel_ended':
      return 'After the duel resolves'
    case 'card_played':
      return `After ${subject(game, window.params.player, 'play', 'plays')} ${cardName(game, cards, window.params.card) ?? 'a card'}`
    case 'card_entered_play':
      return `After ${cardName(game, cards, window.params.card) ?? 'a card'} enters play`
    case 'phase_began':
      return `After the ${window.params.phase ?? 'a'} phase begins`
    case 'conflict_won':
      return `After ${subject(game, window.params.winner, 'win', 'wins')} the conflict`
    default:
      return 'Reactions'
  }
}

/** the reaction windows of the viewer with the cards they have not declined, the oldest first; none for a spectator nor
 * in a replay, where nobody acts */
export function shownWindows(game: GameView, cards: Cards, declined: Set<string>): ShownWindow[] {
  if (game.spectating || game.replaying) {
    return []
  }

  return viewer(game).reaction_windows.flatMap((window) => {
    const offers = window.card_ids.flatMap((cardId): ShownOffer[] => {
      const located = locate(game, cardId)
      if (declined.has(declineKey(window.id, cardId)) || located === undefined) {
        return []
      }
      const card = located.card.code === null ? undefined : cards[located.card.code]
      const type = card?.type ?? 'card'

      return [
        {
          windowId: window.id,
          cardId,
          name: card?.name ?? 'Card',
          kind: located.stack.type === 'hand' ? `${type} · hand` : type,
        },
      ]
    })

    return offers.length === 0 ? [] : [{ id: window.id, trigger: triggerText(window, game, cards), offers }]
  })
}

/** the first offer of a card, with the triggering condition of its window */
export function offerOf(windows: ShownWindow[], cardId: string): (ShownOffer & { trigger: string }) | null {
  for (const window of windows) {
    const offer = window.offers.find((candidate) => candidate.cardId === cardId)
    if (offer !== undefined) {
      return { ...offer, trigger: window.trigger }
    }
  }

  return null
}

const KEY = 'kyuden.declined-reactions'

function load(gameId: string): Set<string> {
  try {
    const value = sessionStorage.getItem(`${KEY}.${gameId}`)

    return new Set(value === null ? [] : (JSON.parse(value) as string[]))
  } catch {
    return new Set()
  }
}

/**
 * The cards the viewer has declined in the reaction windows of a game, kept by the tab; those of the windows closed
 * since are forgotten.
 */
export function useDeclinedReactions(game: Ref<GameView | null>): {
  declined: Ref<Set<string>>
  decline: (offer: Pick<ShownOffer, 'windowId' | 'cardId'>) => void
} {
  const declined = ref(new Set<string>())
  let gameId: string | null = null

  const save = () => {
    try {
      if (gameId !== null) {
        sessionStorage.setItem(`${KEY}.${gameId}`, JSON.stringify([...declined.value]))
      }
    } catch {
      // nothing is kept: a reload offers them again
    }
  }

  watch(
    game,
    (current) => {
      if (current === null || current.spectating || current.replaying) {
        return
      }
      if (current.id !== gameId) {
        gameId = current.id
        declined.value = load(gameId)
      }
      const open = new Set(viewer(current).reaction_windows.map((window) => window.id))
      const kept = [...declined.value].filter((key) => open.has(key.split(':')[0]!))
      if (kept.length !== declined.value.size) {
        declined.value = new Set(kept)
        save()
      }
    },
    { immediate: true },
  )

  return {
    declined,
    decline: ({ windowId, cardId }) => {
      declined.value = new Set([...declined.value, declineKey(windowId, cardId)])
      save()
    },
  }
}
