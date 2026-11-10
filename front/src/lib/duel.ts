import type { DuelView, GameView, PlayerView } from '@/api/game'
import {
  charactersInPlay,
  nameOf,
  playerById,
  printed,
  skillOf,
  stepOf,
  type Cards,
  type Character,
  type Step,
} from '@/lib/board'

/**
 * The duel being resolved (D. Duel Timing), as the board shows it: the side of the opponent above the side of the
 * viewer, each with its honor bid, its dueling statistic and its total, the steps of the duel and what the players do
 * in the current one. The engine adds the honor bid to the statistic each player declares (D.7).
 */

export type DuelStep = 'bid' | 'statistic' | 'result'

export const DUEL_STEPS: { id: DuelStep | 'reveal'; label: string }[] = [
  { id: 'bid', label: 'Bid' },
  { id: 'reveal', label: 'Reveal' },
  { id: 'statistic', label: 'Statistic' },
  { id: 'result', label: 'Result' },
]

export function duelStepOf(duel: DuelView): DuelStep {
  if (!duel.revealed) {
    return 'bid'
  }

  return duel.resolved ? 'result' : 'statistic'
}

/** "Military skill", "Political skill", "Glory" */
export function statisticName(duel: DuelView): string {
  return duel.type === 'glory' ? 'Glory' : `${duel.type === 'military' ? 'Military' : 'Political'} skill`
}

/** "Military duel" */
export function duelTitle(duel: DuelView): string {
  return `${duel.type.charAt(0).toUpperCase()}${duel.type.slice(1)} duel`
}

/** the dueling statistic of characters, computed from the cards, without the honor bid: the engine adds it */
export function duelStatistic(characters: Character[], cards: Cards, duel: DuelView): number {
  return characters.reduce(
    (total, character) =>
      total +
      (duel.type === 'glory'
        ? printed(character.card.code ? cards[character.card.code]?.glory : null)
        : skillOf(character, cards, duel.type)),
    0,
  )
}

/** the cards of a player involved in the duel, by their side */
function duelCardIds(duel: DuelView, playerId: string): string[] {
  if (playerId === duel.challenger_id) {
    return duel.challenger_card_ids
  }

  return playerId === duel.challenged_id ? duel.challenged_card_ids : []
}

/** the characters of a player involved in the duel: those of their side, in play */
export function duelCharacters(game: GameView, playerId: string): Character[] {
  const duel = game.duel
  const player = playerById(game, playerId)
  if (duel === null || player === undefined) {
    return []
  }
  const ids = duelCardIds(duel, playerId)

  return charactersInPlay(player).filter((character) => ids.includes(character.card.id))
}

/** every card involved in the duel, of both sides */
export function duelCardIdsOf(game: GameView): string[] {
  const duel = game.duel

  return duel === null ? [] : [...duel.challenger_card_ids, ...duel.challenged_card_ids]
}

export interface DuelBid {
  /** the dial: its value once revealed, or for its owner while choosing; null while unknown */
  value: number | null
  /** chosen and not revealed yet */
  secret: boolean
  caption: string
}

export interface DuelSide {
  player: PlayerView
  role: 'challenger' | 'challenged'
  characters: Character[]
  bid: DuelBid
  /** declared; null until then */
  statistic: number | null
  statisticCaption: string
  /** the statistic and the honor bid, once both are known */
  total: number | null
  /** once the result is known: null on a tie */
  outcome: 'wins' | 'loses' | null
}

export interface DuelBoard {
  duel: DuelView
  step: DuelStep
  title: string
  statistic: string
  /** who challenges, and where */
  context: string
  /** the opponent above, the viewer below */
  sides: [DuelSide, DuelSide]
  band: { title: string; text: string; won: boolean }
}

/** the verb for a player: "You win", "Opp. wins" */
function verb(game: GameView, player: PlayerView, base: string): string {
  return !game.spectating && player.id === game.viewer_id ? base : `${base}s`
}

function bidOf(game: GameView, duel: DuelView, player: PlayerView, other: PlayerView): DuelBid {
  if (!duel.revealed) {
    if (player.pending_honor_bid !== null) {
      return { value: player.pending_honor_bid, secret: true, caption: 'Not revealed yet' }
    }

    return { value: null, secret: true, caption: player.honor_bid_selected ? 'Bid placed' : 'Choosing…' }
  }
  const bid = player.honor_bid
  if (!duel.resolved || bid === null || other.honor_bid === null) {
    return { value: bid, secret: false, caption: 'Revealed' }
  }
  // D.5: the higher bidder gives the difference to the other once the statistics are declared
  const difference = bid - other.honor_bid
  const caption =
    difference === 0 ? 'No transfer' : difference > 0 ? `Gave ${difference} honor` : `Received ${-difference} honor`

  return { value: bid, secret: false, caption }
}

function sideOf(game: GameView, duel: DuelView, player: PlayerView, other: PlayerView): DuelSide {
  const challenger = player.id === duel.challenger_id
  const statistic = challenger ? duel.challenger_statistic : duel.challenged_statistic
  const bid = bidOf(game, duel, player, other)
  const mine = !game.spectating && player.id === game.viewer_id
  let statisticCaption = ''
  if (statistic !== null) {
    statisticCaption = 'Declared'
  } else if (duel.revealed) {
    statisticCaption = mine && game.available_actions.includes('declare_duel_statistic') ? 'Your turn' : 'Declaring…'
  }

  return {
    player,
    role: challenger ? 'challenger' : 'challenged',
    characters: duelCharacters(game, player.id),
    bid,
    statistic,
    statisticCaption,
    total: statistic !== null && duel.revealed && player.honor_bid !== null ? statistic + player.honor_bid : null,
    outcome: !duel.resolved || duel.winner_id === null ? null : duel.winner_id === player.id ? 'wins' : 'loses',
  }
}

function names(side: DuelSide, cards: Cards): string {
  return side.characters
    .map((character) => (character.card.code ? (cards[character.card.code]?.name ?? 'A character') : 'A character'))
    .join(', ')
}

/**
 * The duel as the board shows it, from the side of the viewer (`viewer_id`: the player at the bottom); null without a
 * duel, or when a player of the duel is not in the view.
 */
export function duelBoard(game: GameView, cards: Cards): DuelBoard | null {
  const duel = game.duel
  const bottom = playerById(game, game.viewer_id)
  const top = game.players.find((player) => player.id !== game.viewer_id)
  if (duel === null || bottom === undefined || top === undefined) {
    return null
  }
  const step = duelStepOf(duel)
  const statistic = statisticName(duel)
  const sides: [DuelSide, DuelSide] = [sideOf(game, duel, top, bottom), sideOf(game, duel, bottom, top)]
  const challenger = playerById(game, duel.challenger_id)
  const where = game.conflict === null ? 'outside a conflict' : 'during the conflict'

  let band = {
    title: 'Honor bid',
    text: 'Each duelist secretly chooses 1 to 5. The dials are revealed once both have bid.',
    won: false,
  }
  if (step === 'statistic') {
    band = {
      title: `Dials revealed: ${sides.map((side) => side.bid.value ?? '?').join(' and ')}`,
      text: `Each player declares the ${statistic.toLowerCase()} of their side. Then the higher bidder gives the difference to the other.`,
      won: false,
    }
  } else if (step === 'result') {
    const totals = sides.map((side) => side.total ?? '?').join(' to ')
    const winner = sides.find((side) => side.outcome === 'wins')
    const loser = sides.find((side) => side.outcome === 'loses')
    band =
      winner === undefined || loser === undefined
        ? {
            title: `A tie, ${totals}`,
            text: 'Neither character wins nor loses. Apply what the ability says, then end the duel.',
            won: false,
          }
        : {
            title: `${nameOf(game, winner.player)} ${verb(game, winner.player, 'win')} the duel, ${winner.total ?? '?'} to ${loser.total ?? '?'}`,
            text: `${names(winner, cards)} ${winner.characters.length > 1 ? 'win' : 'wins'}, ${names(loser, cards)} ${loser.characters.length > 1 ? 'lose' : 'loses'}. Apply what the ability says, then end the duel.`,
            won: true,
          }
  }

  return {
    duel,
    step,
    title: duelTitle(duel),
    statistic,
    context:
      challenger === undefined
        ? where
        : `${nameOf(game, challenger)} ${verb(game, challenger, 'challenge')} · ${where}`,
    sides,
    band,
  }
}

const DUEL_HELP: Record<DuelStep, { title: string; help: string; spectator: string }> = {
  bid: {
    title: 'Duel · honor bid',
    help: 'Choose your honor bid. Both dials are revealed once both duelists have bid.',
    spectator: 'Each duelist chooses their honor bid. Both dials are revealed once both have bid.',
  },
  statistic: {
    title: 'Duel · statistic',
    help: 'Declare the dueling statistic of your side: the engine adds your honor bid to it.',
    spectator: 'Each duelist declares the dueling statistic of their side; the honor bid is added to it.',
  },
  result: {
    title: 'Duel · result',
    help: 'Apply the results with the effects of the board, then end the duel: the action window goes on.',
    spectator: 'The duelists apply the results, then end the duel: the action window goes on.',
  },
}

/** the step shown in the bar on the right: the step of the duel being resolved, else that of the game place */
export function stepWithDuel(game: GameView): Step {
  const step = stepOf(game.place, game.spectating)
  if (game.duel === null) {
    return step
  }
  const help = DUEL_HELP[duelStepOf(game.duel)]
  const involved = [game.duel.challenger_id, game.duel.challenged_id].includes(game.viewer_id)

  return {
    phase: step.phase,
    title: help.title,
    help: game.spectating || !involved ? help.spectator : help.help,
  }
}

/**
 * A card of a hand that can be played during a duel (D.9: no action window, only interrupts and reactions): an event
 * with an interrupt or a reaction. A card the viewer does not know is not dimmed.
 */
export function playableDuringDuel(code: string | null, cards: Cards): boolean {
  const card = code === null ? undefined : cards[code]
  if (card === undefined) {
    return true
  }

  return (
    card.type === 'event' &&
    (card.ability_timings ?? []).some((timing) => timing === 'interrupt' || timing === 'reaction')
  )
}
