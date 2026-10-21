import { ref } from 'vue'

import {
  ApiProblem,
  createAccount,
  createBotGame,
  createGuest,
  createSession,
  deleteSession,
  fetchSelf,
  renameSelf,
  type Account,
  type BotGame,
} from '@/api/client'
import { CLANS, type Clan } from '@/data/clans'
import { drawCourtName } from '@/lib/names'

/**
 * The account of the visitor, kept by the browser so that the visitor finds it again on their next visit: a guest
 * account created with their name at court (no username), or an account they signed in to.
 */
export interface Session {
  userId: string
  name: string
  /** null for a guest */
  username: string | null
  token: string
}

const KEY = 'kyuden.session'

export function loadSession(): Session | null {
  try {
    const value = localStorage.getItem(KEY)
    if (value === null) {
      return null
    }
    const stored = JSON.parse(value) as Omit<Session, 'username'> & { username?: string | null }

    // the guests kept before the accounts have no username
    return { ...stored, username: stored.username ?? null }
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
  const created = { userId: guest.user_id, name: guest.name, username: null, token: guest.token }
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
  const created = { userId: account.user_id, name: account.name, username: account.username, token: account.token }
  saveSession(created)

  return created
}

/**
 * Creates the account of the visitor: a guest keeps their user (their name at court and their games); anyone else
 * gets a new user, with a name at court drawn at random, which they can change from their account.
 */
export async function signUp(username: string, password: string, random: () => number = Math.random): Promise<Session> {
  const current = loadSession()
  if (current !== null && current.username === null) {
    try {
      return accountSession(await createAccount({ username, password }, current.token))
    } catch (error) {
      if (!(error instanceof ApiProblem) || error.status !== 401) {
        throw error
      }
      // the guest is no longer valid: a new user with the same name
      return accountSession(await createAccount({ username, password, name: current.name }, null))
    }
  }

  const clan = CLANS[Math.floor(random() * CLANS.length)]!

  return accountSession(await createAccount({ username, password, name: drawCourtName(clan, random) }, null))
}

export async function signIn(username: string, password: string): Promise<Session> {
  return accountSession(await createSession(username, password))
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
    const refreshed = { ...current, name: self.name, username: self.username }
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

export async function rename(name: string): Promise<Session> {
  const current = loadSession()
  if (current === null) {
    throw new Error('No session to rename.')
  }
  const self = await renameSelf(current.token, name)
  const renamed = { ...current, name: self.name, username: self.username }
  saveSession(renamed)

  return renamed
}
