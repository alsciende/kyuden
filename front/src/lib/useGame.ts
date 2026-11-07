import { computed, onBeforeUnmount, reactive, ref, shallowRef } from 'vue'

import { ApiProblem, fetchCard } from '@/api/client'
import {
  claimAbandonment,
  fetchAbandonment,
  fetchGame,
  fetchLog,
  fetchSpectators,
  leaveGame,
  seenFrom,
  sendMessage,
  type GameView,
  type GameViewData,
  type LoggedMessage,
  type LogParam,
  type PayloadArgs,
  type SpectatorView,
} from '@/api/game'
import type { MessagePayloads, MessageType } from '@/api/messages'
import { isAsked, noPrompt, waitLonger, type AbandonmentPrompt } from '@/lib/abandonment'
import { gameEnded, visibleCodes, type Cards } from '@/lib/board'
import { follow } from '@/lib/hub'
import { idleWatch } from '@/lib/idleWatch'
import { loadSession } from '@/lib/session'

/** the codes of the cards a param of the log names, those of a list included (the cards looked at) */
function codesOf(param: LogParam): string[] {
  if (param.type === 'list') {
    return param.items.flatMap(codesOf)
  }

  return param.type === 'card' && param.code ? [param.code] : []
}

/**
 * A game followed by its player: its view and its log, read again whenever the Mercure hub announces a change (or once
 * it has not changed for a while: a notification may be lost, and reading a game of the house bot makes it play a turn
 * it has not played), the cards it shows as printed, and the messages the player sends.
 *
 * While the game stays the same, the player reads whether their opponent has abandoned it: they are then asked whether
 * to end it, which they win, or to wait longer (see lib/abandonment).
 *
 * An account that does not play the game watches it: the engine sends it what is public only, and it sends nothing. It
 * watches from the side of a player (the first in player order), and switches sides. The spectators of the game are
 * read again whenever the hub announces they have changed (`spectators_updated`).
 */
export function useGame(gameId: string) {
  const session = loadSession()
  /** the view as the engine sent it */
  const data = shallowRef<GameViewData | null>(null)
  /** the player a spectator watches from, null for the first in player order */
  const seat = ref<string | null>(null)
  const game = shallowRef<GameView | null>(null)
  const spectators = ref<SpectatorView[]>([])
  const log = ref<LoggedMessage[]>([])
  const cards = reactive<Cards>({})
  /** why the game cannot be shown */
  const loadError = ref<ApiProblem | null>(null)
  /** why the last message was rejected */
  const error = ref<string | null>(null)
  const sending = ref(false)
  const abandonment = ref<AbandonmentPrompt>(noPrompt())
  /** when the abandonment was last read: the question depends on the time */
  const readAt = ref(Date.now())
  const abandoned = computed(() => isAsked(abandonment.value, readAt.value))

  let unfollow: (() => void) | null = null
  let refreshing: Promise<void> | null = null
  let refreshAgain = false
  const idle = idleWatch(() => void refresh())

  /** reads the game again; a change announced meanwhile reads it once more afterwards */
  async function refresh(): Promise<void> {
    if (refreshing !== null) {
      refreshAgain = true

      return refreshing
    }
    refreshing = (async () => {
      do {
        refreshAgain = false
        await load()
      } while (refreshAgain)
    })()
    try {
      await refreshing
    } finally {
      refreshing = null
    }
  }

  async function load(): Promise<void> {
    if (session === null) {
      loadError.value = new ApiProblem(401, 'no_session', 'You are not signed in.')

      return
    }
    try {
      const loaded = await fetchGame(session.token, gameId)
      const seen = seenFrom(loaded, seat.value)
      const changed = loaded.version !== game.value?.version
      if (gameEnded(seen)) {
        idle.stop()
      } else if (changed) {
        idle.touch()
      }
      if (gameEnded(seen) || seen.spectating || changed) {
        abandonment.value = noPrompt()
      } else {
        void readAbandonment()
      }
      data.value = loaded
      game.value = seen
      loadError.value = null
      readCards(visibleCodes(game.value))
      if (unfollow === null) {
        unfollow = follow(session.token, [`/games/${gameId}`], (update) =>
          update.event === 'spectators_updated' ? void readSpectators() : void refresh(),
        )
        void readSpectators()
      }
      await readLog()
    } catch (problem) {
      if (problem instanceof ApiProblem) {
        loadError.value = problem
      } else {
        throw problem
      }
    }
  }

  /** whether the opponent has abandoned the game, which has not changed for a while */
  async function readAbandonment(): Promise<void> {
    try {
      const view = await fetchAbandonment(session!.token, gameId)
      abandonment.value = { ...abandonment.value, view }
      readAt.value = Date.now()
    } catch {
      // read again while the game stays the same
    }
  }

  /** the player waits longer for their opponent */
  function waitForOpponent(): void {
    abandonment.value = waitLonger(abandonment.value, Date.now())
  }

  /** the player ends the game their opponent has abandoned */
  async function endAbandonedGame(): Promise<void> {
    if (session === null || game.value === null || game.value.spectating) {
      return
    }
    sending.value = true
    error.value = null
    try {
      await claimAbandonment(session.token, gameId)
    } catch (problem) {
      if (!(problem instanceof ApiProblem)) {
        throw problem
      }
      // the opponent has acted or read the game meanwhile
      error.value = [409, 412].includes(problem.status) ? 'Your opponent is back: the game goes on.' : problem.detail
    } finally {
      abandonment.value = noPrompt()
      sending.value = false
    }
    await refresh()
  }

  async function readSpectators(): Promise<void> {
    if (session === null) {
      return
    }
    try {
      spectators.value = await fetchSpectators(session.token, gameId)
    } catch {
      // the spectators are read again on the next change
    }
  }

  /** a spectator watches from the side of the other player */
  function switchSides(): void {
    if (data.value === null || game.value === null || !game.value.spectating) {
      return
    }
    seat.value = game.value.players.find((player) => player.id !== game.value!.viewer_id)?.id ?? null
    game.value = seenFrom(data.value, seat.value)
  }

  /** a spectator stops watching the game */
  async function leave(): Promise<void> {
    if (session === null || !game.value?.spectating) {
      return
    }
    try {
      await leaveGame(session.token, gameId)
    } catch {
      // they will no longer read it: the engine forgets them soon
    }
  }

  async function readLog(): Promise<void> {
    const since = log.value.at(-1)?.version ?? 0
    const read = await fetchLog(session!.token, gameId, since)
    log.value = [...log.value, ...read.messages.filter((message) => message.version > since)]
    readCards(
      read.messages.flatMap((message) =>
        message.entries.flatMap((entry) => Object.values(entry.params).flatMap(codesOf)),
      ),
    )
  }

  function readCards(codes: string[]): void {
    for (const code of codes) {
      if (!(code in cards)) {
        fetchCard(code)
          .then((card) => {
            cards[code] = card
            // the image is shown when the card is hovered: loaded beforehand
            if (card.image_url && typeof Image !== 'undefined') {
              new Image().src = card.image_url
            }
          })
          .catch(() => {
            // the card is shown without its printed values
          })
      }
    }
  }

  /**
   * Sends a message of the player for the version of the game they see. Returns whether it was handled.
   */
  async function send<T extends MessageType>(type: T, ...[payload]: PayloadArgs<T>): Promise<boolean> {
    if (session === null || game.value === null || game.value.spectating) {
      return false
    }
    sending.value = true
    error.value = null
    try {
      // a message without payload sends an empty object
      await sendMessage(session.token, gameId, type, payload ?? ({} as MessagePayloads[T]), game.value.version)
      await refresh()

      return true
    } catch (problem) {
      if (!(problem instanceof ApiProblem)) {
        throw problem
      }
      error.value =
        problem.status === 412
          ? 'The game changed meanwhile: look at it again before acting.'
          : [problem.detail, ...problem.violations.map((violation) => violation.message)].join(' ')
      await refresh()

      return false
    } finally {
      sending.value = false
    }
  }

  // a spectator who leaves the page no longer watches the game
  onBeforeUnmount(() => {
    idle.stop()
    unfollow?.()
    unfollow = null
    void leave()
  })

  return {
    session,
    game,
    log,
    cards,
    spectators,
    loadError,
    error,
    sending,
    send,
    refresh,
    switchSides,
    abandoned,
    waitForOpponent,
    endAbandonedGame,
  }
}

export type GameContext = ReturnType<typeof useGame>
