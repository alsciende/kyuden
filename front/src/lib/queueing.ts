import { ref, watch } from 'vue'

import { ApiProblem, fetchDecks, fetchQueue, fetchSelfGames, leaveQueue } from '@/api/client'
import { timeLeft } from '@/lib/invitation'
import { deckChoice, waitingTime } from '@/lib/queue'

/**
 * The wait of the user in the matchmaking queue, shared by every page: once they have joined it, they go where they
 * like (an account watches a game meanwhile), and the game opens wherever they are once the queue has matched them
 * (`QueueFollower`, mounted by the application). Kept by the tab, so that reloading a page keeps the wait as it was;
 * the queue (`GET /api/queue`) tells whether the user still waits.
 */
export interface Waiting {
  userId: string
  deckId: string
  /** the name of the deck, null when it could not be read */
  deckName: string | null
  /** a starter deck, named by its clan */
  starter: boolean
  /** when the wait began, in milliseconds */
  since: number
  /** whom the user waits for in a friendly game */
  friendly: Friendly | null
}

export type Friendly =
  /** the host of an invitation of their bot or of a friend, with when it expires */
  | { kind: 'bot'; botName: string | null; expiresAt: string }
  | { kind: 'friend'; expiresAt: string }
  /** the friend of the host, invited with a code */
  | { kind: 'host'; hostName: string }

/** how long "Opponent found" shows before the game opens */
export const FOUND_DELAY = 1000

/** how often the queue is read again, should the hub not tell that the game has started */
export const CHECK_DELAY = 15_000

const KEY = 'kyuden.queue'

function load(): Waiting | null {
  try {
    const value = sessionStorage.getItem(KEY)

    return value === null ? null : (JSON.parse(value) as Waiting)
  } catch {
    return null
  }
}

/** the wait of the user, null when they are not in the queue */
export const waiting = ref<Waiting | null>(load())

/** the game the queue has found, shown as found until it opens */
export const found = ref<string | null>(null)

/** the time the wait is told at, every second while the user waits */
export const now = ref(Date.now())

watch(waiting, (current) => {
  try {
    if (current === null) {
      sessionStorage.removeItem(KEY)
    } else {
      sessionStorage.setItem(KEY, JSON.stringify(current))
    }
  } catch {
    // nothing is kept: the queue tells again
  }
})

/**
 * The user has joined the queue.
 */
export function startWaiting(
  userId: string,
  deck: { id: string; name: string; starter: boolean },
  friendly: Friendly | null,
): void {
  waiting.value = { userId, deckId: deck.id, deckName: deck.name, starter: deck.starter, since: Date.now(), friendly }
}

/**
 * The queue has matched the user: the game shows as found, then opens.
 */
export function gameFound(gameId: string): void {
  waiting.value = null
  found.value = gameId
}

/**
 * Reads whether the user waits in the queue: still waiting (a wait unknown to the tab is read from the queue, with the
 * name of the deck), or no longer, once matched (their last game in progress is found) or once they have left it
 * elsewhere.
 */
export async function checkQueue(token: string, userId: string): Promise<void> {
  if (waiting.value !== null && waiting.value.userId !== userId) {
    waiting.value = null
  }
  // the wait changed meanwhile (joined, left, found): the read that follows the change tells
  const before = waiting.value
  let deckId: string
  try {
    deckId = (await fetchQueue(token)).deck_id
  } catch (problem) {
    if (!(problem instanceof ApiProblem) || problem.status !== 404) {
      throw problem
    }
    if (waiting.value !== before || before === null) {
      return
    }
    const game = (await fetchSelfGames(token)).find((candidate) => !candidate.ended)
    if (waiting.value !== before) {
      return
    }
    if (game !== undefined) {
      gameFound(game.id)
    } else {
      waiting.value = null
    }

    return
  }
  if (waiting.value !== before || before?.deckId === deckId) {
    return
  }
  const deck = (await fetchDecks(token)).find((candidate) => candidate.id === deckId)
  if (waiting.value !== before) {
    return
  }
  const choice = deck === undefined ? null : deckChoice(deck)
  waiting.value = {
    userId,
    deckId,
    deckName: choice?.name ?? null,
    starter: deck?.owner_id === null,
    since: before?.since ?? Date.now(),
    friendly: before?.friendly ?? null,
  }
}

/**
 * Leaves the queue; matched meanwhile, the game is found.
 */
export async function leave(token: string, userId: string): Promise<void> {
  try {
    await leaveQueue(token)
    waiting.value = null
  } catch (problem) {
    if (!(problem instanceof ApiProblem) || problem.status !== 404) {
      throw problem
    }
    await checkQueue(token, userId)
  }
}

/**
 * The texts of the wait, the same wherever it shows.
 */
export interface WaitingTexts {
  /** "A friendly game · In the queue · 1:42" */
  eyebrow: string
  /** "Waiting for an opponent" */
  title: string
  /** the deck, "Lion starter deck" (null when unknown) */
  deck: string | null
  /** when the game opens */
  opens: string
  /** when the invitation of the host expires, null otherwise */
  expiry: string | null
  /** the label of the button that leaves the queue */
  leave: string
}

export function waitingTexts(current: Waiting, now: number): WaitingTexts {
  const friendly = current.friendly
  const title =
    friendly === null
      ? 'Waiting for an opponent'
      : friendly.kind === 'bot'
        ? 'Waiting for your bot'
        : friendly.kind === 'friend'
          ? 'Waiting for your friend'
          : `Waiting for ${friendly.hostName}`
  const opens =
    friendly === null
      ? 'The game opens as soon as another player joins.'
      : friendly.kind === 'bot'
        ? 'The game opens as soon as your bot enters the queue with its key.'
        : friendly.kind === 'friend'
          ? 'The game opens as soon as your friend enters the queue with the link.'
          : `The game opens as soon as ${friendly.hostName} enters the queue.`
  const hosting = friendly !== null && friendly.kind !== 'host'

  return {
    eyebrow: `${friendly !== null ? 'A friendly game · ' : ''}In the queue · ${waitingTime((now - current.since) / 1000)}`,
    title,
    deck: current.deckName === null ? null : `${current.deckName}${current.starter ? ' starter deck' : ''}`,
    opens,
    expiry: hosting ? `The invitation expires in ${timeLeft(friendly.expiresAt, now)} if nobody comes.` : null,
    leave: hosting ? 'Leave the queue and cancel the invitation' : 'Leave the queue',
  }
}
