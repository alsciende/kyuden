import { ApiProblem, createBotGame, createGuest, type BotGame } from '@/api/client'
import type { Clan } from '@/data/clans'

/**
 * The account of the visitor: a guest account created with their name at court, kept by the browser so that the
 * visitor finds it again on their next visit.
 */
export interface Session {
  userId: string
  name: string
  token: string
}

const KEY = 'kyuden.session'

export function loadSession(): Session | null {
  try {
    const value = localStorage.getItem(KEY)

    return value === null ? null : (JSON.parse(value) as Session)
  } catch {
    return null
  }
}

function saveSession(session: Session | null): void {
  try {
    if (session === null) {
      localStorage.removeItem(KEY)
    } else {
      localStorage.setItem(KEY, JSON.stringify(session))
    }
  } catch {
    // the browser keeps nothing (private window): the session lasts as long as the page
  }
}

async function guestSession(name: string): Promise<Session> {
  const session = loadSession()
  // a guest cannot be renamed yet: another name is another guest
  if (session !== null && session.name === name) {
    return session
  }

  const guest = await createGuest(name)
  const created = { userId: guest.user_id, name: guest.name, token: guest.token }
  saveSession(created)

  return created
}

/**
 * Starts the introductory game of a visitor: their guest account (created with their name at court if needed), then a
 * game against the house bot with the starter deck of their clan.
 */
export async function startIntroductoryGame(name: string, clan: Clan): Promise<BotGame> {
  const session = await guestSession(name)
  try {
    return await createBotGame(session.token, clan.deckId)
  } catch (error) {
    if (!(error instanceof ApiProblem) || error.status !== 401) {
      throw error
    }
    // the token is no longer valid: a new guest
    saveSession(null)

    return createBotGame((await guestSession(name)).token, clan.deckId)
  }
}
