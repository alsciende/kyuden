import type { ClanId } from '@/data/clans'
import type { Banner, MonId, NoboriCollection, PatternId } from '@/data/nobori'

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
  uniqueness?: boolean | null
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

/** whether the site is light or dark: as the system says, day or night */
export type Theme = 'auto' | 'light' | 'dark'

/**
 * The preferences of a user.
 */
export interface UserSettings {
  card_display: CardDisplay
  theme: Theme
  /** the tips of the tutorial shown in the games against the house bot */
  tutorial: boolean
  /** the ids of the tips of the tutorial already seen (`lib/tutorial.ts`) */
  seen_tips: string[]
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
  /** their nobori, seen by the other players */
  banner: Banner
  /** what they may choose for their nobori */
  unlocked: NoboriCollection
}

/**
 * A game of the authenticated user, the last joined first.
 */
export interface UserGame {
  id: string
  player_id: string
  version: number
  ended: boolean
  /** a game of an invitation, not ranked */
  friendly: boolean
  /** the user ids, in player order */
  players: string[]
  /** the players, in player order: their name, the clan of their stronghold, whether a program plays them */
  seats: UserGameSeat[]
  /** when the game ended, null while it goes on */
  ended_at: string | null
  /** the player who won, null while the game goes on or when nobody won */
  winner_id: string | null
  /** how the game ended, null while it goes on */
  victory_condition: string | null
}

/** the trophies of the catalogue, the values of the enum `Trophy` of the API */
export type TrophyId =
  | 'house_bot_game'
  | 'clan_crab'
  | 'clan_crane'
  | 'clan_dragon'
  | 'clan_lion'
  | 'clan_phoenix'
  | 'clan_scorpion'
  | 'clan_unicorn'
  | 'tournament_game'
  | 'tournament_win'

/**
 * A trophy of the catalogue for the authenticated user, in its order: what it gives for the nobori, and once earned,
 * when and in which game.
 */
export interface UserTrophy {
  trophy: TrophyId
  reward_mon: MonId | null
  reward_pattern: PatternId | null
  earned: boolean
  earned_at: string | null
  /** the game it was earned in, null for a trophy no game gives */
  game_id: string | null
}

export interface UserGameSeat {
  user_id: string
  name: string
  clan: string | null
  bot: boolean
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

/** whom an invitation to a friendly game is for */
export type InvitationKind = 'bot' | 'friend'

/**
 * An invitation of the user to a friendly game.
 */
export interface Invitation {
  id: string
  kind: InvitationKind
  /** the code a friend uses (`KX7-QM4-T9P`) */
  code: string | null
  /** the bot of the user, for an invitation of their bot */
  bot: { user_id: string; name: string } | null
  /** the API key of the bot, limited to the invitation: only when it is created */
  token: string | null
  expires_at: string
}

/**
 * An invitation of a friend as anyone with its code sees it.
 */
export interface InvitationStatus {
  host_name: string
  state: 'pending' | 'expired' | 'used'
}

/** how the user joins the queue with an invitation: its id for its host, its code for a friend */
export type QueueInvitation = { invitation_id: string } | { invitation_code: string }

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
  /** the code of its stronghold, null for a deck without one */
  stronghold: string | null
  /** the clan of its stronghold */
  clan: string | null
}

/**
 * The subscription of the user to the Mercure hub. Reading it sets a subscriber token for their topic and the games they
 * play in a cookie the browser sends to the hub (HTTP only: the client never reads it).
 */
export interface Subscription {
  hub: string
  /** the topic of the user, where they learn they have joined a game */
  topic: string
  expires_at: string
}

/** a provider of identities an account is linked to, to sign in with it */
export type OAuthProvider = 'google' | 'discord'

/** why the visitor goes to the provider: to sign in to the account linked there, or to link it to theirs */
export type OAuthPurpose = 'sign_in' | 'link'

/**
 * Where to send the visitor to authorize Kyūden at the provider, and the state the provider sends back.
 */
export interface OAuthAuthorization {
  authorization_url: string
  state: string
}

/**
 * An identity at a provider linked to the account of the user.
 */
export interface LinkedAccount {
  provider: OAuthProvider
  /** RFC 3339 */
  linked_at: string
}

interface RequestOptions {
  method?: string
  body?: unknown
  token?: string | null
}

/** told of each token the API refuses (401) */
let refusedTokenListener: ((token: string) => void) | null = null

/**
 * Tells the listener of each token the API refuses (a 401 to a request sent with it): a token revoked, or forgotten
 * by a database created again. Null stops telling.
 */
export function onRefusedToken(listener: ((token: string) => void) | null): void {
  refusedTokenListener = listener
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
    if (response.status === 401 && token) {
      refusedTokenListener?.(token)
    }
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
  changes: { name?: string; preferred_clan?: ClanId; settings?: Partial<UserSettings>; banner?: Partial<Banner> },
): Promise<Self> {
  return request<Self>('/api/self', { method: 'PATCH', body: changes, token })
}

export function deleteSelf(token: string): Promise<void> {
  return request<void>('/api/self', { method: 'DELETE', token })
}

export function fetchSelfGames(token: string): Promise<UserGame[]> {
  return request<UserGame[]>('/api/self/games', { token })
}

export function fetchTrophies(token: string): Promise<UserTrophy[]> {
  return request<UserTrophy[]>('/api/self/trophies', { token })
}

export function fetchSubscription(token: string): Promise<Subscription> {
  return request<Subscription>('/api/self/subscription', { token })
}

export function fetchDecks(token: string): Promise<Deck[]> {
  return request<Deck[]>('/api/decks', { token })
}

/**
 * Imports a deck of EmeraldDB from the URL of its page, for the user: 422 `invalid_deck` when it cannot be (violations
 * on `url`, `cards[<id>]` or `cards`), 502 `deck_source_unavailable` when EmeraldDB cannot be reached. A deck with the
 * same cards as one of the user's is that deck, restored if they had deleted it.
 */
export function importDeck(token: string, url: string): Promise<Deck> {
  return request<Deck>('/api/decks', { method: 'POST', body: { url }, token })
}

/**
 * Deletes a deck of the user: 404 `deck_not_found` for a starter deck, 409 `deck_in_queue` while they wait with it.
 */
export function deleteDeck(token: string, deckId: string): Promise<void> {
  return request<void>(`/api/decks/${encodeURIComponent(deckId)}`, { method: 'DELETE', token })
}

/**
 * The deck the user waits with in the queue; a 404 (`not_queued`) once matched or left.
 */
export function fetchQueue(token: string): Promise<{ deck_id: string; match_id: number | null }> {
  return request<{ deck_id: string; match_id: number | null }>('/api/queue', { token })
}

/**
 * How many players wait in the open queue for an opponent, the user left out (not those of an invitation).
 */
export function fetchQueueCount(token: string): Promise<{ waiting: number }> {
  return request<{ waiting: number }>('/api/queue/count', { token })
}

/**
 * Joins the queue: the user is matched at once when another player waits, and told on their topic once the game has started (`game_started`).
 */
export function joinQueue(token: string, deckId: string, invitation?: QueueInvitation): Promise<void> {
  return request<void>('/api/queue', { method: 'POST', body: { deck_id: deckId, ...invitation }, token })
}

/**
 * Joins the queue with a match of a tournament, the deck of the registration: matched with the other player of the
 * match only, until 10 minutes after the round has begun (`invalid_match` after that). Replaces any other wait.
 */
export function joinQueueWithMatch(token: string, matchId: number): Promise<void> {
  return request<void>('/api/queue', { method: 'POST', body: { match_id: matchId }, token })
}

export function leaveQueue(token: string): Promise<void> {
  return request<void>('/api/queue', { method: 'DELETE', token })
}

/**
 * Invites the bot of the user or a friend to a friendly game: a previous invitation is cancelled.
 */
export function createInvitation(token: string, kind: InvitationKind): Promise<Invitation> {
  return request<Invitation>('/api/invitations', { method: 'POST', body: { kind }, token })
}

/**
 * The invitation of the user waiting for its guest (without the key of the bot); a 404 (`invitation_not_found`) when
 * there is none.
 */
export function fetchSelfInvitation(token: string): Promise<Invitation> {
  return request<Invitation>('/api/self/invitation', { token })
}

export function cancelInvitation(token: string): Promise<void> {
  return request<void>('/api/self/invitation', { method: 'DELETE', token })
}

/**
 * Who invites with a code, and whether it can be used; a 404 (`invitation_not_found`) once cancelled.
 */
export function fetchInvitationStatus(code: string): Promise<InvitationStatus> {
  return request<InvitationStatus>(`/api/invitations/${encodeURIComponent(code)}`)
}

/**
 * The providers the visitors can link their account to and sign in with (those configured on the server).
 */
export function fetchOAuthProviders(): Promise<string[]> {
  return request<string[]>('/api/oauth/providers')
}

/**
 * Starts a flow with a provider: to link, with the token of the account.
 */
export function createOAuthAuthorization(
  provider: OAuthProvider,
  purpose: OAuthPurpose,
  token: string | null,
): Promise<OAuthAuthorization> {
  return request<OAuthAuthorization>(`/api/oauth/${provider}/authorizations`, {
    method: 'POST',
    body: { purpose },
    token,
  })
}

/**
 * Signs in with the identity the provider sent back: a 401 (`not_linked`) when no account is linked to it.
 */
export function createOAuthSession(provider: OAuthProvider, code: string, state: string): Promise<Account> {
  return request<Account>(`/api/oauth/${provider}/sessions`, { method: 'POST', body: { code, state } })
}

export function fetchLinkedAccounts(token: string): Promise<LinkedAccount[]> {
  return request<LinkedAccount[]>('/api/self/linked-accounts', { token })
}

/**
 * Links the account to the identity the provider sent back: a 409 (`identity_already_linked`) when it is linked to
 * another account.
 */
export function linkAccount(
  token: string,
  provider: OAuthProvider,
  code: string,
  state: string,
): Promise<LinkedAccount> {
  return request<LinkedAccount>(`/api/self/linked-accounts/${provider}`, {
    method: 'PUT',
    body: { code, state },
    token,
  })
}

export function unlinkAccount(token: string, provider: OAuthProvider): Promise<void> {
  return request<void>(`/api/self/linked-accounts/${provider}`, { method: 'DELETE', token })
}

/** where a tournament stands: open until it locks, one hour before it begins */
export type TournamentStatus = 'open' | 'locked' | 'in_progress' | 'ended' | 'cancelled'

export type TournamentFormat = 'single_elimination'

/** a user in a tournament: its organizer, a player registered (never their deck nor their clan) */
export interface TournamentUser {
  user_id: string
  name: string
  banner: Banner
}

/**
 * A tournament in the list of the tournaments and in the registrations of the user. The dates are in UTC.
 */
export interface TournamentSummary {
  id: string
  starts_at: string
  /** one hour before it begins: no registration, change of deck or cancellation after that */
  locks_at: string
  status: TournamentStatus
  format: TournamentFormat
  organizer: TournamentUser
  entrants: number
  max_entrants: number
}

/** how a match of a tournament ended: won in a game, by a bye, by forfeit, or both players eliminated */
export type MatchOutcome = 'game' | 'bye' | 'forfeit' | 'no_result'

/** a match of a round that has begun */
export interface TournamentMatch {
  /** to join the queue with it */
  id: number
  table: number
  /** two players, one for a bye */
  players: TournamentUser[]
  /** its game, to watch it, null before it begins */
  game_id: string | null
  winner_user_id: string | null
  /** null while it is played */
  outcome: MatchOutcome | null
  /** how its game ended (outcome `game`) */
  victory_condition: string | null
  /** the user ids of its players waiting in the queue with it */
  waiting: string[]
}

export interface TournamentRound {
  number: number
  matches: TournamentMatch[]
}

/**
 * A tournament, the same for every reader, with its players in their order of registration and, once locked, its
 * bracket; the rounds that have begun (the pairings of a round are secret until then).
 */
export interface Tournament extends Omit<TournamentSummary, 'entrants'> {
  players: TournamentUser[]
  /** the places of the bracket, once locked */
  size: number | null
  round_count: number | null
  /** from 1; 0 until it begins */
  current_round: number
  /** until when the players of the current round join the queue, else they forfeit */
  join_deadline: string | null
  /** once ended, null without one */
  winner: TournamentUser | null
  rounds: TournamentRound[]
}

/**
 * A registration of the user in a tournament that has not ended, with its deck.
 */
export interface TournamentEntry {
  tournament: TournamentSummary
  deck_id: string
  deck_name: string | null
  registered_at: string
  /** whether they have lost a match of the tournament */
  eliminated: boolean
}

/**
 * The tournaments to be played and the cancelled ones that would not have begun yet, the first to begin first:
 * public.
 */
export function fetchTournaments(): Promise<TournamentSummary[]> {
  return request<TournamentSummary[]>('/api/tournaments')
}

export function fetchTournament(id: string): Promise<Tournament> {
  return request<Tournament>(`/api/tournaments/${encodeURIComponent(id)}`)
}

/**
 * The tournament as an iCalendar file (`.ics`), to add it to a calendar: public, a link rather than a request.
 */
export function tournamentCalendarUrl(id: string): string {
  return `/api/tournaments/${encodeURIComponent(id)}/calendar.ics`
}

/**
 * Creates a tournament beginning at a time (ISO 8601, UTC): a 422 (`invalid_start`) off the half hour or less than 12
 * hours ahead, a 409 when the user organizes one already (`tournament_already_organized`) or when another begins
 * within 12 hours (`tournament_too_close`).
 */
export function createTournament(token: string, startsAt: string): Promise<Tournament> {
  return request<Tournament>('/api/tournaments', { method: 'POST', body: { starts_at: startsAt }, token })
}

/** its organizer cancels it, until it locks */
export function cancelTournament(token: string, id: string): Promise<Tournament> {
  return request<Tournament>(`/api/tournaments/${encodeURIComponent(id)}/cancellation`, { method: 'POST', token })
}

/** the registrations of the user in the tournaments that have not ended, cancelled ones included */
export function fetchTournamentEntries(token: string): Promise<TournamentEntry[]> {
  return request<TournamentEntry[]>('/api/self/tournaments', { token })
}

/**
 * Registers the user with a deck, or changes the deck of their registration: a 409 once it is locked or cancelled
 * (`tournament_closed`) or full (`tournament_full`).
 */
export function registerInTournament(token: string, id: string, deckId: string): Promise<TournamentEntry> {
  return request<TournamentEntry>(`/api/self/tournaments/${encodeURIComponent(id)}`, {
    method: 'PUT',
    body: { deck_id: deckId },
    token,
  })
}

/** until it locks, or at any time once it is cancelled */
export function unregisterFromTournament(token: string, id: string): Promise<void> {
  return request<void>(`/api/self/tournaments/${encodeURIComponent(id)}`, { method: 'DELETE', token })
}
