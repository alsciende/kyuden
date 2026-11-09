import type { UserGame } from '@/api/client'
import { findClan } from '@/data/clans'
import { resultTitleOf, victoryTextOf } from '@/lib/board'

/**
 * The games a user has played, as their history shows them: the user and their opponent on the tints of their clans
 * (the user first), the result from their point of view and how the game ended, and when.
 */
export interface PlayedGameRow {
  id: string
  players: { name: string; tint: string; bot: boolean }[]
  bot: boolean
  friendly: boolean
  /** Victory, Defeat or No winner */
  result: string
  won: boolean
  /** how the game ended */
  how: string
  /** when it ended */
  ended: string
}

const NEUTRAL_TINT = '#e2d8c4'
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const sameDay = (a: Date, b: Date) =>
  a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()

/** "Today, 17:12", "Yesterday, 21:05", "6 Nov, 19:30", or with the year when it is not the current one */
export function endedLabel(iso: string | null, now: Date = new Date()): string {
  if (iso === null) {
    return ''
  }
  const date = new Date(iso)
  const time = `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
  const yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1)
  if (sameDay(date, now)) {
    return `Today, ${time}`
  }
  if (sameDay(date, yesterday)) {
    return `Yesterday, ${time}`
  }
  const day = `${date.getDate()} ${MONTHS[date.getMonth()]}`

  return date.getFullYear() === now.getFullYear() ? `${day}, ${time}` : `${day} ${date.getFullYear()}, ${time}`
}

/** the games that have ended, the last ended first */
export function playedGameRows(games: UserGame[], userId: string, now: Date = new Date()): PlayedGameRow[] {
  return games
    .filter((game) => game.ended)
    .sort((a, b) => (b.ended_at ?? '').localeCompare(a.ended_at ?? ''))
    .map((game) => {
      const seats = [...game.seats].sort((a, b) => Number(b.user_id === userId) - Number(a.user_id === userId))

      return {
        id: game.id,
        players: seats.map((seat) => ({
          name: seat.name,
          tint: findClan(seat.clan)?.tint ?? NEUTRAL_TINT,
          bot: seat.bot,
        })),
        bot: game.seats.some((seat) => seat.bot && seat.user_id !== userId),
        friendly: game.friendly,
        result: resultTitleOf(game.winner_id, game.player_id),
        won: game.winner_id === game.player_id,
        how: victoryTextOf(game.winner_id, game.player_id, game.victory_condition),
        ended: endedLabel(game.ended_at, now),
      }
    })
}
