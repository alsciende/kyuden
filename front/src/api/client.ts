import type { ClanId } from '@/data/clans'

/**
 * The HTTP API of the engine, on the same origin (nginx sends /api to Symfony). Errors are problem details
 * (RFC 9457), thrown as ApiProblem.
 */

export interface Violation {
  property_path: string
  message: string
}

export class ApiProblem extends Error {
  constructor(
    public readonly status: number,
    public readonly type: string,
    public readonly detail: string,
    public readonly violations: Violation[] = [],
  ) {
    super(detail)
    this.name = 'ApiProblem'
  }
}

export type AbilityTiming = 'action' | 'interrupt' | 'reaction'

/**
 * A card as printed. Skills are strings, as printed (`-` when the card has none).
 */
export interface Card {
  code: string
  name: string
  side?: string | null
  type: string
  clan: string | null
  cost?: number | null
  fate?: number | null
  honor?: number | null
  glory?: number | null
  military?: string | null
  military_bonus?: string | null
  political?: string | null
  political_bonus?: string | null
  strength?: number | null
  strength_bonus?: string | null
  text?: string | null
  ability_timings?: AbilityTiming[]
  ability_granted_to_attached?: boolean
  image_url: string | null
}

export interface Guest {
  user_id: string
  name: string
  token: string
}

/**
 * An account and a new session token: the guest who created it (same user), a new user, or a user signing in.
 */
/** how the board draws the cards: a frame with their name and values, or their image */
export type CardDisplay = 'drawn' | 'images'

/**
 * The preferences of a user.
 */
export interface UserSettings {
  card_display: CardDisplay
}

export interface Account {
  user_id: string
  name: string
  username: string
  preferred_clan: ClanId | null
  settings: UserSettings
  token: string
}

/**
 * The authenticated user: a guest has no username nor preferred clan.
 */
export interface Self {
  name: string
  username: string | null
  preferred_clan: ClanId | null
  settings: UserSettings
}

/**
 * A game of the authenticated user, the last joined first.
 */
export interface UserGame {
  id: string
  player_id: string
  version: number
  ended: boolean
  /** the user ids, in player order */
  players: string[]
}

export interface BotGame {
  game_id: string
  player_id: string
  opponent: {
    user_id: string
    name: string
    deck_id: string
  }
}

/**
 * A deck the user can play: their own, or a deck without owner (the starter decks).
 */
export interface Deck {
  id: string
  name: string | null
  /** null for the decks every user can use */
  owner_id: string | null
  /** quantity by card code */
  cards: Record<string, number>
}

/**
 * The subscription of the user to the Mercure hub: a subscriber token for their topic and the games they play.
 */
export interface Subscription {
  hub: string
  token: string
  /** the topic of the user, where they learn they have joined a game */
  topic: string
  expires_at: string
}

interface RequestOptions {
  method?: string
  body?: unknown
  token?: string | null
}

export async function request<T>(path: string, { method = 'GET', body, token }: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(path, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  if (!response.ok) {
    throw await toProblem(response)
  }

  return (response.status === 204 ? undefined : await response.json()) as T
}

export async function toProblem(response: Response): Promise<ApiProblem> {
  try {
    const problem = (await response.json()) as Partial<{ type: string; detail: string; violations: Violation[] }>

    return new ApiProblem(
      response.status,
      problem.type ?? 'unknown',
      problem.detail ?? response.statusText,
      problem.violations ?? [],
    )
  } catch {
    return new ApiProblem(response.status, 'unknown', response.statusText)
  }
}

const cards = new Map<string, Promise<Card>>()

/**
 * A card as printed, read once per page (the API is cacheable).
 */
export function fetchCard(code: string): Promise<Card> {
  let card = cards.get(code)
  if (card === undefined) {
    card = request<Card>(`/api/cards/${encodeURIComponent(code)}`)
    card.catch(() => cards.delete(code))
    cards.set(code, card)
  }

  return card
}

export function createGuest(name: string): Promise<Guest> {
  return request<Guest>('/api/guests', { method: 'POST', body: { name } })
}

export function createBotGame(token: string, deckId: string): Promise<BotGame> {
  return request<BotGame>('/api/bot-games', { method: 'POST', body: { deck_id: deckId }, token })
}

/**
 * Creates an account with its preferred clan, which gets a copy of the starter deck of the clan: with the token of a
 * guest, the guest becomes the account; without, a new user with the name at court given.
 */
export function createAccount(
  credentials: { username: string; password: string; clan: ClanId; name?: string },
  token: string | null,
): Promise<Account> {
  return request<Account>('/api/accounts', { method: 'POST', body: credentials, token })
}

export function createSession(username: string, password: string): Promise<Account> {
  return request<Account>('/api/sessions', { method: 'POST', body: { username, password } })
}

export function deleteSession(token: string): Promise<void> {
  return request<void>('/api/sessions/current', { method: 'DELETE', token })
}

export function fetchSelf(token: string): Promise<Self> {
  return request<Self>('/api/self', { token })
}

export function updateSelf(
  token: string,
  changes: { name?: string; preferred_clan?: ClanId; settings?: Partial<UserSettings> },
): Promise<Self> {
  return request<Self>('/api/self', { method: 'PATCH', body: changes, token })
}

export function fetchSelfGames(token: string): Promise<UserGame[]> {
  return request<UserGame[]>('/api/self/games', { token })
}

export function fetchSubscription(token: string): Promise<Subscription> {
  return request<Subscription>('/api/self/subscription', { token })
}

export function fetchDecks(token: string): Promise<Deck[]> {
  return request<Deck[]>('/api/decks', { token })
}

/**
 * The deck the user waits with in the queue; a 404 (`not_queued`) once matched or left.
 */
export function fetchQueue(token: string): Promise<{ deck_id: string }> {
  return request<{ deck_id: string }>('/api/queue', { token })
}

/**
 * Joins the queue: the user is matched at once when another player waits, and told on their topic once the game has started (`game_started`).
 */
export function joinQueue(token: string, deckId: string): Promise<void> {
  return request<void>('/api/queue', { method: 'POST', body: { deck_id: deckId }, token })
}

export function leaveQueue(token: string): Promise<void> {
  return request<void>('/api/queue', { method: 'DELETE', token })
}
