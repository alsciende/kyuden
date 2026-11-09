import { request } from '@/api/client'
import type { GameLog } from '@/api/game'

/** an action of the player who follows a replay: its version in the game, whose log tells what it did */
export interface ReplayAction {
  version: number
  type: string
}

/**
 * Where the replay of a game that has ended stands, for the player who follows it: its copy is read like any game, as
 * its player (`player_id`).
 */
export interface ReplayView {
  /** the game replayed */
  game_id: string
  /** the copy the replay rebuilds */
  replay_game_id: string
  /** the player of the user in the copy */
  player_id: string
  /** the number of events replayed */
  version: number
  /** the number of events of the game */
  total: number
  /** the next action of the player, not replayed yet: the replay stopped before it when it is the next event */
  next: ReplayAction | null
  /** every event of the game has been replayed */
  ended: boolean
}

const path = (gameId: string) => `/api/games/${encodeURIComponent(gameId)}/replay`

/** where the replay stands: 404 `replay_not_found` before it starts */
export function fetchReplay(token: string, gameId: string): Promise<ReplayView> {
  return request<ReplayView>(path(gameId), { token })
}

/**
 * Starts the replay again from the beginning, up to the first action of the player, or up to a version: 409
 * `game_not_ended`, `replay_diverged` (with the version the copy stands at).
 */
export function startReplay(token: string, gameId: string, untilVersion?: number): Promise<ReplayView> {
  return request<ReplayView>(path(gameId), {
    method: 'POST',
    token,
    body: untilVersion === undefined ? undefined : { until_version: untilVersion },
  })
}

/** continues the replay, up to the next action of the player or up to a version (not before the replay: 409) */
export function proceedReplay(token: string, gameId: string, untilVersion?: number): Promise<ReplayView> {
  return request<ReplayView>(`${path(gameId)}/proceed`, {
    method: 'POST',
    token,
    body: untilVersion === undefined ? undefined : { until_version: untilVersion },
  })
}

/** the log of the game as the player read it, every message of it, its ids those of the copy */
export function fetchReplayLog(token: string, gameId: string): Promise<GameLog> {
  return request<GameLog>(`${path(gameId)}/log`, { token })
}
