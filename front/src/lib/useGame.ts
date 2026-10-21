import { onBeforeUnmount, reactive, ref, shallowRef } from 'vue'

import { ApiProblem, fetchCard } from '@/api/client'
import { fetchGame, fetchLog, sendMessage, type GameView, type LoggedMessage } from '@/api/game'
import { visibleCodes, type Cards } from '@/lib/board'
import { loadSession } from '@/lib/session'

/**
 * A game followed by its player: its view and its log, read again whenever the Mercure hub announces a change, the
 * cards it shows as printed, and the messages the player sends.
 */
export function useGame(gameId: string) {
  const session = loadSession()
  const game = shallowRef<GameView | null>(null)
  const log = ref<LoggedMessage[]>([])
  const cards = reactive<Cards>({})
  /** why the game cannot be shown */
  const loadError = ref<ApiProblem | null>(null)
  /** why the last message was rejected */
  const error = ref<string | null>(null)
  const sending = ref(false)

  let source: EventSource | null = null
  let refreshing: Promise<void> | null = null
  let refreshAgain = false

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
      game.value = loaded.game
      loadError.value = null
      readCards(visibleCodes(loaded.game))
      subscribe(loaded.hub)
      await readLog()
    } catch (problem) {
      if (problem instanceof ApiProblem) {
        loadError.value = problem
      } else {
        throw problem
      }
    }
  }

  async function readLog(): Promise<void> {
    const since = log.value.at(-1)?.version ?? 0
    const read = await fetchLog(session!.token, gameId, since)
    log.value = [...log.value, ...read.messages.filter((message) => message.version > since)]
    readCards(
      read.messages.flatMap((message) =>
        message.entries.flatMap((entry) =>
          Object.values(entry.params).flatMap((param) => (param.type === 'card' && param.code ? [param.code] : [])),
        ),
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

  function subscribe(hub: string | null): void {
    if (hub === null || source !== null || typeof EventSource === 'undefined') {
      return
    }
    const url = new URL(hub, window.location.href)
    url.searchParams.append('topic', `/games/${gameId}`)
    source = new EventSource(url)
    source.onmessage = () => void refresh()
  }

  /**
   * Sends a message of the player for the version of the game they see. Returns whether it was handled.
   */
  async function send(type: string, payload: Record<string, unknown> = {}): Promise<boolean> {
    if (session === null || game.value === null) {
      return false
    }
    sending.value = true
    error.value = null
    try {
      await sendMessage(session.token, gameId, type, payload, game.value.version)
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

  onBeforeUnmount(() => {
    source?.close()
    source = null
  })

  return { session, game, log, cards, loadError, error, sending, send, refresh }
}

export type GameContext = ReturnType<typeof useGame>
