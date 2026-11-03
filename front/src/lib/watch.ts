import type { WatchableGame, WatchableGamePlayer } from '@/api/game'
import { findClan } from '@/data/clans'
import { PHASES, stepOf } from '@/lib/board'

/**
 * The games in progress an account may watch, as the list shows them: who plays, where they are, how many watch, and
 * whether the user plays it (they join it as its player instead).
 */
export interface WatchableGameRow {
  id: string
  players: { name: string; tint: string; bot: boolean }[]
  /** a program plays in it */
  bot: boolean
  friendly: boolean
  /** the user plays it */
  yours: boolean
  /** the phase, then the step */
  phase: string
  step: string
  spectators: number
}

const NEUTRAL_TINT = '#e2d8c4'

function playerOf(player: WatchableGamePlayer): WatchableGameRow['players'][number] {
  return { name: player.name, tint: findClan(player.clan)?.tint ?? NEUTRAL_TINT, bot: player.bot }
}

export function watchableGameRow(game: WatchableGame, userId: string | null): WatchableGameRow {
  const step = stepOf(game.place, true)

  return {
    id: game.id,
    players: game.players.map(playerOf),
    bot: game.players.some((player) => player.bot),
    friendly: game.friendly,
    yours: game.players.some((player) => player.user_id === userId),
    phase: PHASES.find((phase) => phase.id === step.phase)?.name ?? 'Setup',
    step: step.title,
    spectators: game.spectators,
  }
}

export function spectatorsLabel(count: number): string {
  return `${count} spectator${count === 1 ? '' : 's'}`
}
