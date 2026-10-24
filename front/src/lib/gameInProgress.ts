import { fetchSelfGames } from '@/api/client'
import { fetchGame } from '@/api/game'

export interface GameInProgress {
  id: string
  /** null when the game has no other player yet */
  opponent: string | null
}

/**
 * The last game the user joined that has not ended, with the name at court of their opponent, so that a player who
 * comes back to the site can rejoin it; null when every game of theirs has ended.
 */
export async function findGameInProgress(token: string, userId: string): Promise<GameInProgress | null> {
  const game = (await fetchSelfGames(token)).find((candidate) => !candidate.ended)
  if (game === undefined) {
    return null
  }
  const view = await fetchGame(token, game.id)

  return { id: game.id, opponent: view.players.find((player) => player.user_id !== userId)?.name ?? null }
}
