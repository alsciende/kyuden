import { request, toProblem } from '@/api/client'

/**
 * A game as its player sees it (`GET /api/games/{id}`), its log, and the messages a player sends to it. The view
 * follows the visibility rules of the engine: hidden stacks have no cards, hidden cards no code.
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

export interface CardView {
  id: string
  /** null when the viewer does not see the face of the card */
  code: string | null
  /** public, even when the code is not: the back of the card */
  side: Side
  orientation: Orientation
  fate: number
  personal_honor: PersonalHonor
  /** the current skill recorded by the players (every modifier applied), null when none */
  military_skill: number | null
  political_skill: number | null
}

export interface StackView {
  id: string
  type: StackType
  count: number
  /** from the bottom to the top; null when the viewer only sees the number of cards */
  cards: CardView[] | null
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
  fate_collected: boolean
  conflict_opportunities: number
  declared_conflict_types: ConflictType[]
  glory_count: number | null
  stacks: StackView[]
}

export interface RingView {
  fate: number
  claimed_by_id: string | null
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
  viewer_id: string
  player_ids: string[]
  first_player_id: string | null
  active_player_ids: string[]
  players: PlayerView[]
  rings: Record<Element, RingView>
  conflict: ConflictView | null
  duel: DuelView | null
  imperial_favor_holder_id: string | null
  imperial_favor_side: ConflictType | null
  glory_count_winner_id: string | null
  winner_id: string | null
  victory_condition: string | null
  available_actions: string[]
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

export function fetchGame(token: string, gameId: string): Promise<GameView> {
  return request<GameView>(`/api/games/${encodeURIComponent(gameId)}`, { token })
}

export function fetchLog(token: string, gameId: string, since: number): Promise<GameLog> {
  return request<GameLog>(`/api/games/${encodeURIComponent(gameId)}/log?since=${since}`, { token })
}

/**
 * Sends a message of the player, rejected (412) if the game has changed since the version the player has seen.
 */
export async function sendMessage(
  token: string,
  gameId: string,
  type: string,
  payload: Record<string, unknown>,
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
