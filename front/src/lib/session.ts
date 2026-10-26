import { ref } from 'vue'

import {
  ApiProblem,
  createAccount,
  createBotGame,
  createGuest,
  createOAuthSession,
  createSession,
  deleteSelf,
  deleteSession,
  fetchSelf,
  fetchSelfGames,
  updateSelf,
  type Account,
  type BotGame,
  type CardDisplay,
  type OAuthProvider,
  type Self,
  type Theme,
} from '@/api/client'
import { fetchGame } from '@/api/game'
import { CLANS, findClan, type Clan, type ClanId } from '@/data/clans'
import { drawCourtName } from '@/lib/names'
import { newcomer } from '@/lib/newcomer'

/**
 * The account of the visitor, kept by the browser so that the visitor finds it again on their next visit: a guest
 * account created with their name at court (no username), or an account they signed in to.
 */
export interface Session {
  userId: string
  name: string
  /** null for a guest */
  username: string | null
  /** null for a guest */
  preferredClan: ClanId | null
  /** how the board draws the cards: always drawn for a guest, who has no account to change it */
  cardDisplay: CardDisplay
  /** light or dark: as the system says for a guest */
  theme: Theme
  token: string
}

const KEY = 'kyuden.session'

export function loadSession(): Session | null {
  try {
    const value = localStorage.getItem(KEY)
    if (value === null) {
      return null
    }
    const stored = JSON.parse(value) as Omit<Session, 'username' | 'preferredClan' | 'cardDisplay' | 'theme'> & {
      username?: string | null
      preferredClan?: ClanId | null
      cardDisplay?: CardDisplay
      theme?: Theme
    }

    // the guests kept before the accounts have no username, the sessions kept before the clans no preferred clan,
    // those kept before the settings no card display nor theme
    return {
      ...stored,
      username: stored.username ?? null,
      preferredClan: stored.preferredClan ?? null,
      cardDisplay: stored.cardDisplay ?? 'images',
      theme: stored.theme ?? 'auto',
    }
  } catch {
    return null
  }
}

/**
 * The session of the visitor, for the pages that show it (the header, the account).
 */
export const session = ref<Session | null>(loadSession())

function saveSession(current: Session | null): void {
  session.value = current
  try {
    if (current === null) {
      localStorage.removeItem(KEY)
    } else {
      localStorage.setItem(KEY, JSON.stringify(current))
    }
  } catch {
    // the browser keeps nothing (private window): the session lasts as long as the page
  }
}

export function isGuest(current: Session | null): boolean {
  return current !== null && current.username === null
}

async function guestSession(name: string): Promise<Session> {
  const current = loadSession()
  // an account plays under its own name; a guest cannot be renamed: another name is another guest
  if (current !== null && (current.username !== null || current.name === name)) {
    return current
  }

  const guest = await createGuest(name)
  const created: Session = {
    userId: guest.user_id,
    name: guest.name,
    username: null,
    preferredClan: null,
    cardDisplay: 'images',
    theme: 'auto',
    token: guest.token,
  }
  saveSession(created)

  return created
}

/**
 * Starts the introductory game of a visitor: their guest account (created with their name at court if needed), then a
 * game against the house bot with the starter deck of their clan.
 */
export async function startIntroductoryGame(name: string, clan: Clan): Promise<BotGame> {
  const current = await guestSession(name)
  try {
    return await createBotGame(current.token, clan.deckId)
  } catch (error) {
    if (!(error instanceof ApiProblem) || error.status !== 401) {
      throw error
    }
    // the token is no longer valid: a new guest
    saveSession(null)

    return createBotGame((await guestSession(name)).token, clan.deckId)
  }
}

function accountSession(account: Account): Session {
  const created = {
    userId: account.user_id,
    name: account.name,
    username: account.username,
    preferredClan: account.preferred_clan,
    cardDisplay: account.settings.card_display,
    theme: account.settings.theme,
    token: account.token,
  }
  saveSession(created)

  return created
}

/**
 * The clan a visitor creating their account probably prefers: for a guest, the clan of their last game, else the clan
 * chosen on their way to it while the tab remembers it; for anyone else, or when nothing tells, a clan drawn at random.
 */
export async function suggestedClan(random: () => number = Math.random): Promise<Clan> {
  const current = loadSession()
  if (current !== null && current.username === null) {
    try {
      const games = await fetchSelfGames(current.token)
      if (games.length > 0) {
        const game = await fetchGame(current.token, games[0]!.id)
        const clan = findClan(game.players.find((player) => player.user_id === current.userId)?.clan)
        if (clan !== undefined) {
          return clan
        }
      }
    } catch {
      // nothing tells the clan of the last game
    }
    const chosen = findClan(newcomer.clanId)
    if (chosen !== undefined) {
      return chosen
    }
  }

  return CLANS[Math.floor(random() * CLANS.length)]!
}

/**
 * Creates the account of the visitor with their preferred clan, which gives them a copy of the starter deck of the
 * clan: a guest keeps their user (their name at court and their games); anyone else gets a new user, with a name at
 * court drawn from the families of the clan, which they can change from their account.
 */
export async function signUp(
  username: string,
  password: string,
  clan: Clan,
  random: () => number = Math.random,
): Promise<Session> {
  const current = loadSession()
  if (current !== null && current.username === null) {
    try {
      return accountSession(await createAccount({ username, password, clan: clan.id }, current.token))
    } catch (error) {
      if (!(error instanceof ApiProblem) || error.status !== 401) {
        throw error
      }
      // the guest is no longer valid: a new user with the same name
      return accountSession(await createAccount({ username, password, clan: clan.id, name: current.name }, null))
    }
  }

  return accountSession(
    await createAccount({ username, password, clan: clan.id, name: drawCourtName(clan, random) }, null),
  )
}

export async function signIn(username: string, password: string): Promise<Session> {
  return accountSession(await createSession(username, password))
}

/**
 * Signs in with the identity a provider sent back, to the account linked to it.
 */
export async function signInWithProvider(provider: OAuthProvider, code: string, state: string): Promise<Session> {
  return accountSession(await createOAuthSession(provider, code, state))
}

/**
 * Signs out: the token is revoked, and the browser forgets the account.
 */
export async function signOut(): Promise<void> {
  const current = loadSession()
  saveSession(null)
  if (current === null) {
    return
  }
  try {
    await deleteSession(current.token)
  } catch {
    // a token already revoked, or a network failure: the browser has forgotten it anyway
  }
}

/**
 * Deletes the account (its games stay, under a name that is no longer theirs), then the browser forgets it. A failure
 * keeps the account and the session.
 */
export async function deleteAccount(): Promise<void> {
  const current = loadSession()
  if (current === null) {
    return
  }
  await deleteSelf(current.token)
  saveSession(null)
}

/**
 * Reads the account again from the API (it may have been renamed from another browser). A token refused (401) signs
 * out.
 */
export async function refreshSession(): Promise<Session | null> {
  const current = loadSession()
  if (current === null) {
    return null
  }
  try {
    const self = await fetchSelf(current.token)
    const refreshed = withSelf(current, self)
    saveSession(refreshed)

    return refreshed
  } catch (error) {
    if (error instanceof ApiProblem && error.status === 401) {
      saveSession(null)

      return null
    }
    throw error
  }
}

function withSelf(current: Session, self: Self): Session {
  return {
    ...current,
    name: self.name,
    username: self.username,
    preferredClan: self.preferred_clan,
    cardDisplay: self.settings.card_display,
    theme: self.settings.theme,
  }
}

async function updateAccount(changes: Parameters<typeof updateSelf>[1]): Promise<Session> {
  const current = loadSession()
  if (current === null) {
    throw new Error('No session to change.')
  }
  const changed = withSelf(current, await updateSelf(current.token, changes))
  saveSession(changed)

  return changed
}

export function rename(name: string): Promise<Session> {
  return updateAccount({ name })
}

export function preferClan(clan: ClanId): Promise<Session> {
  return updateAccount({ preferred_clan: clan })
}

export function chooseCardDisplay(cardDisplay: CardDisplay): Promise<Session> {
  return updateAccount({ settings: { card_display: cardDisplay } })
}

export function chooseTheme(theme: Theme): Promise<Session> {
  return updateAccount({ settings: { theme } })
}
