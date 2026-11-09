import { request, toProblem, type AbilityTiming } from '@/api/client'
import type { MessagePayloads, MessageType } from '@/api/messages'

/**
 * A game as its player sees it (`GET /api/games/{id}`), or a spectator, its log, its spectators, and the messages a
 * player sends to it. The view follows the visibility rules of the engine: hidden stacks have no cards, hidden cards
 * no code; a spectator only sees what is public.
 */

export type Element = 'air' | 'earth' | 'fire' | 'void' | 'water'
export type ConflictType = 'military' | 'political'
export type Orientation = 'face_down' | 'face_up' | 'sideways' | 'upside_down'
export type PersonalHonor = 'ordinary' | 'honored' | 'dishonored'
/** the side of a card, which tells its back */
export type Side = 'conflict' | 'dynasty' | 'province' | 'role' | 'stronghold'
export type StackType =
  | 'conflict_draw_deck'
  | 'conflict_discard_pile'
  | 'dynasty_draw_deck'
  | 'dynasty_discard_pile'
  | 'stronghold_province'
  | 'role'
  | 'hand'
  | 'regular_province'
  | 'in_play'
  /** a pile of each player, created by the first card removed from the game */
  | 'removed_from_game'
  /** the cards placed under a card (`host_id`), out of play */
  | 'underneath'

export interface CardView {
  id: string
  /** null when the viewer does not see the face of the card */
  code: string | null
  /** public, even when the code is not: the back of the card */
  side: Side
  orientation: Orientation
  fate: number
  /** honor tokens on a province or a holding: counters, not status tokens */
  honor_tokens: number
  personal_honor: PersonalHonor
  /** the current skill recorded by the players (every modifier applied), null when none */
  military_skill: number | null
  political_skill: number | null
  /** its owner, unless another player has taken control of it in play; an attachment may be controlled by another
   * player than the one of the stack it is in */
  controller_id: string
}

export interface StackView {
  id: string
  type: StackType
  count: number
  /** from the bottom to the top; null when the viewer only sees the number of cards */
  cards: CardView[] | null
  /** the card the cards of an `underneath` stack lie under; null for any other stack */
  host_id: string | null
}

/**
 * An event played or an ability triggered that a player resolves: the card as the viewer sees it now (no id when they
 * do not see its stack, no code when they do not see its face, neither once it no longer exists) and the timing it was
 * played or triggered with.
 */
export interface ResolutionView {
  card_id: string | null
  code: string | null
  timing: AbilityTiming
}

export interface PlayerView {
  id: string
  user_id: string
  name: string
  clan: string | null
  honor: number | null
  fate: number
  honor_bid: number | null
  pending_honor_bid: number | null
  honor_bid_selected: boolean
  stronghold_province_id: string | null
  passed: boolean
  acted: boolean
  resolving_interrupts_and_reactions: number
  /** in the order they were initiated */
  resolutions: ResolutionView[]
  fate_collected: boolean
  conflict_opportunities: number
  declared_conflict_types: ConflictType[]
  /** the additional conflicts included */
  declarable_conflict_types: ConflictType[]
  glory_count: number | null
  stacks: StackView[]
}

export interface RingView {
  fate: number
  claimed_by_id: string | null
  /** the type of the conflict the ring was claimed in; null while it is unclaimed or contested */
  claimed_conflict_type: ConflictType | null
}

export interface ConflictView {
  attacking_player_id: string
  defending_player_id: string
  ring: Element
  type: ConflictType
  province_id: string
  attacked_province_ids: string[]
  attacker_ids: string[]
  defender_ids: string[]
  evaded_ids: string[]
  attacker_skill: number | null
  defender_skill: number | null
  province_strengths: Record<string, number>
  declared_strength_province_ids: string[]
  winner_id: string | null
  broken_province_ids: string[]
}

export interface DuelView {
  challenger_id: string
  challenged_id: string
  type: ConflictType | 'glory'
  challenger_card_ids: string[]
  challenged_card_ids: string[]
  revealed: boolean
  challenger_statistic: number | null
  challenged_statistic: number | null
  resolved: boolean
  winner_id: string | null
  loser_id: string | null
}

export interface GameView {
  id: string
  version: number
  place: string | null
  /** the player at the bottom of the board: the viewer, or the player whose side a spectator watches from */
  viewer_id: string
  /** set by the client (seenFrom): the viewer watches the game without playing it, and can send nothing */
  spectating?: boolean
  /** set by the client: the viewer replays a game they played (a copy rebuilt from its events), and can send nothing */
  replaying?: boolean
  player_ids: string[]
  first_player_id: string | null
  active_player_ids: string[]
  players: PlayerView[]
  /** empty until the game starts */
  rings: Partial<Record<Element, RingView>>
  conflict: ConflictView | null
  duel: DuelView | null
  imperial_favor_holder_id: string | null
  imperial_favor_side: ConflictType | null
  glory_count_winner_id: string | null
  winner_id: string | null
  victory_condition: string | null
  available_actions: string[]
  /** every special action: offered apart, and only on demand when none of the viewer's cards needs it */
  special_actions: SpecialActionView[]
}

/** a special action and the cards of the viewer whose text needs it (in play under their control, or their own cards
 * out of play they know) */
export interface SpecialActionView {
  type: string
  card_ids: string[]
}

/** the view as the API sends it: no viewer for a spectator */
export type GameViewData = Omit<GameView, 'viewer_id' | 'spectating' | 'replaying'> & { viewer_id: string | null }

/**
 * The view as the client shows it: a spectator watches from the side of a player (`seat`, the first player in player
 * order by default), at the bottom of the board as the viewer would be.
 */
export function seenFrom(data: GameViewData, seat: string | null): GameView {
  if (data.viewer_id !== null) {
    return { ...data, viewer_id: data.viewer_id, spectating: false }
  }
  const ids = data.players.map((player) => player.id)
  const viewerId = seat !== null && ids.includes(seat) ? seat : (data.player_ids[0] ?? ids[0] ?? '')

  return { ...data, viewer_id: viewerId, spectating: true }
}

/** an account watching a game */
export interface SpectatorView {
  user_id: string
  name: string
}

/** a player of a game in progress, in the list of the games to watch */
export interface WatchableGamePlayer {
  user_id: string
  name: string
  clan: string | null
  bot: boolean
}

/** a game in progress, which an account may watch (`GET /api/games`) */
export interface WatchableGame {
  id: string
  version: number
  place: string | null
  friendly: boolean
  /** in player order of arrival */
  players: WatchableGamePlayer[]
  spectators: number
}

export type LogParam =
  | { type: 'player'; id: string }
  | { type: 'card'; id: string | null; code: string | null }
  | { type: 'stack'; stack_type: StackType; player_id: string; id: string | null }
  | { type: 'ring'; element: Element }
  | { type: 'value'; value?: string | number | boolean | null; hidden?: boolean }
  | { type: 'list'; items: LogParam[] }

export interface LogEntry {
  template: string
  /** an empty list when the entry has no params */
  params: Record<string, LogParam> | []
}

export interface LoggedMessage {
  version: number
  type: string
  player_id: string | null
  entries: LogEntry[]
}

export interface GameLog {
  game_id: string
  version: number
  messages: LoggedMessage[]
}

export function fetchGame(token: string, gameId: string): Promise<GameViewData> {
  return request<GameViewData>(`/api/games/${encodeURIComponent(gameId)}`, { token })
}

export function fetchGamesInProgress(token: string): Promise<WatchableGame[]> {
  return request<WatchableGame[]>('/api/games', { token })
}

export function fetchSpectators(token: string, gameId: string): Promise<SpectatorView[]> {
  return request<SpectatorView[]>(`/api/games/${encodeURIComponent(gameId)}/spectators`, { token })
}

/** the spectator stops watching the game */
export function leaveGame(token: string, gameId: string): Promise<void> {
  return request<void>(`/api/games/${encodeURIComponent(gameId)}/spectators/self`, { method: 'DELETE', token })
}

export function fetchLog(token: string, gameId: string, since: number): Promise<GameLog> {
  return request<GameLog>(`/api/games/${encodeURIComponent(gameId)}/log?since=${since}`, { token })
}

/** whether the player may end a game their opponent no longer plays (`GET /api/games/{id}/abandonment`) */
export interface AbandonmentView {
  /** the players the game waits for, in player order */
  awaited_player_ids: string[]
  /** from when the player may end the game if it does not change; null when it does not wait for their opponent */
  claimable_at: string | null
  /** the player may end the game now: their opponent has abandoned it */
  claimable: boolean
}

export function fetchAbandonment(token: string, gameId: string): Promise<AbandonmentView> {
  return request<AbandonmentView>(`/api/games/${encodeURIComponent(gameId)}/abandonment`, { token })
}

/** the player ends the game their opponent has abandoned, and wins: refused (409) while the opponent may still play */
export function claimAbandonment(token: string, gameId: string): Promise<void> {
  return request<void>(`/api/games/${encodeURIComponent(gameId)}/abandonment`, { method: 'POST', token })
}

/**
 * Sends a message of the player, rejected (412) if the game has changed since the version the player has seen.
 */
export async function sendMessage<T extends MessageType>(
  token: string,
  gameId: string,
  type: T,
  payload: MessagePayloads[T],
  version: number,
): Promise<void> {
  const response = await fetch(`/api/games/${encodeURIComponent(gameId)}/messages`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
      'If-Match': `"${version}"`,
    },
    body: JSON.stringify({ type, payload }),
  })
  if (!response.ok) {
    throw await toProblem(response)
  }
}

/** the payload of a message, which can be left out when the message has none */
export type PayloadArgs<T extends MessageType> =
  MessagePayloads[T] extends Record<string, never> ? [payload?: MessagePayloads[T]] : [payload: MessagePayloads[T]]

/** sends a message of the player; resolves to whether it was handled */
export type Send = <T extends MessageType>(type: T, ...payload: PayloadArgs<T>) => Promise<boolean>
