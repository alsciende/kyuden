import { computed, onBeforeUnmount, reactive, ref, shallowRef } from 'vue'

import { ApiProblem, fetchCard } from '@/api/client'
import { fetchGame, seenFrom, type GameView, type LoggedMessage, type LogParam } from '@/api/game'
import { fetchReplay, fetchReplayLog, proceedReplay, startReplay, type ReplayView } from '@/api/replay'
import { visibleCodes, type Cards } from '@/lib/board'
import { jumpTo } from '@/lib/replay'
import { loadSession } from '@/lib/session'

/** the delay between two steps of the autoplay */
export const AUTOPLAY_DELAY = 2000

function codesOf(param: LogParam): string[] {
  if (param.type === 'list') {
    return param.items.flatMap(codesOf)
  }

  return param.type === 'card' && param.code ? [param.code] : []
}

/**
 * The replay of a game that has ended, followed by one of its players: where it stands, the board of its copy as the
 * player saw it then (read only), and the log of the whole game told about the copy. The player continues it up to
 * their next action, jumps to a message (forward it goes on, back it starts again), starts it again, or lets it play
 * on its own (autoplay). The replay the player followed is resumed where it stood; one deleted meanwhile (not read for
 * an hour) is started again up to there.
 */
export function useReplay(gameId: string) {
  const session = loadSession()
  const replay = shallowRef<ReplayView | null>(null)
  const game = shallowRef<GameView | null>(null)
  const log = ref<LoggedMessage[]>([])
  /** the copy whose log is read: a replay started again has another */
  let logOf: string | null = null
  const cards = reactive<Cards>({})
  /** the version the last step started from: what it replayed is told */
  const stepFrom = ref<number | null>(null)
  /** why the replay cannot be shown */
  const loadError = ref<ApiProblem | null>(null)
  /** why the last step failed */
  const error = ref<string | null>(null)
  /** the game cannot be replayed further: the engine has changed since it was played */
  const diverged = ref(false)
  const busy = ref(false)
  /** the version a jump goes to, while it is replayed */
  const jumping = ref<number | null>(null)
  const autoplay = ref(false)
  let timer: ReturnType<typeof setTimeout> | null = null

  const ended = computed(() => replay.value?.ended === true)

  async function readCopy(view: ReplayView): Promise<void> {
    const data = await fetchGame(session!.token, view.replay_game_id)
    game.value = { ...seenFrom(data, null), replaying: true }
    if (logOf !== view.replay_game_id) {
      log.value = (await fetchReplayLog(session!.token, gameId)).messages
      logOf = view.replay_game_id
    }
    readCards([
      ...visibleCodes(game.value),
      ...log.value.flatMap((message) =>
        message.entries.flatMap((entry) => Object.values(entry.params).flatMap(codesOf)),
      ),
    ])
  }

  function readCards(codes: string[]): void {
    for (const code of new Set(codes)) {
      if (!(code in cards)) {
        fetchCard(code)
          .then((card) => {
            cards[code] = card
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

  /** the replay the player followed, else a new one */
  async function load(): Promise<void> {
    if (session === null) {
      loadError.value = new ApiProblem(401, 'no_session', 'You are not signed in.')

      return
    }
    busy.value = true
    try {
      let view: ReplayView
      try {
        view = await fetchReplay(session.token, gameId)
      } catch (problem) {
        if (!(problem instanceof ApiProblem) || problem.type !== 'replay_not_found') {
          throw problem
        }
        view = await startReplay(session.token, gameId)
      }
      replay.value = view
      await readCopy(view)
      loadError.value = null
    } catch (problem) {
      if (!(problem instanceof ApiProblem)) {
        throw problem
      }
      if (problem.type === 'replay_diverged') {
        diverged.value = true
        await reread()
      } else {
        loadError.value = problem
      }
    } finally {
      busy.value = false
    }
  }

  /** where the replay stands after a failed step */
  async function reread(): Promise<void> {
    try {
      const view = await fetchReplay(session!.token, gameId)
      replay.value = view
      await readCopy(view)
    } catch {
      // the board stays as it was
    }
  }

  /** a step of the replay: the board is read again once it is handled */
  async function step(request: () => Promise<ReplayView>): Promise<void> {
    if (session === null || busy.value || diverged.value) {
      return
    }
    busy.value = true
    error.value = null
    const from = replay.value?.version ?? 0
    try {
      let view: ReplayView
      try {
        view = await request()
      } catch (problem) {
        // not read for an hour, the replay was deleted: it starts again up to where it stood
        if (!(problem instanceof ApiProblem) || problem.type !== 'replay_not_found') {
          throw problem
        }
        view = await startReplay(session.token, gameId, from)
      }
      stepFrom.value = view.version > from ? from : null
      replay.value = view
      await readCopy(view)
    } catch (problem) {
      if (!(problem instanceof ApiProblem)) {
        throw problem
      }
      stopAutoplay()
      if (problem.type === 'replay_diverged') {
        diverged.value = true
        await reread()
      } else {
        error.value = problem.detail
      }
    } finally {
      busy.value = false
      jumping.value = null
    }
    scheduleAutoplay()
  }

  /** up to the next action of the player: theirs first when the replay stopped before it */
  function proceed(): Promise<void> {
    return step(() => proceedReplay(session!.token, gameId))
  }

  /** from the beginning, up to the first action of the player */
  function restart(): Promise<void> {
    stopAutoplay()

    return step(() => startReplay(session!.token, gameId))
  }

  /** to the board as it was once a message was handled */
  function jump(version: number): Promise<void> {
    const current = replay.value
    const plan = current === null ? null : jumpTo(current, version)
    if (plan === null) {
      return Promise.resolve()
    }
    stopAutoplay()
    jumping.value = plan.until

    return step(() =>
      plan.direction === 'forward'
        ? proceedReplay(session!.token, gameId, plan.until)
        : startReplay(session!.token, gameId, plan.until),
    )
  }

  function scheduleAutoplay(): void {
    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
    if (autoplay.value && !ended.value && !diverged.value) {
      timer = setTimeout(() => {
        timer = null
        void proceed()
      }, AUTOPLAY_DELAY)
    }
  }

  function stopAutoplay(): void {
    autoplay.value = false
    scheduleAutoplay()
  }

  function toggleAutoplay(): void {
    autoplay.value = !autoplay.value && !ended.value && !diverged.value
    if (autoplay.value) {
      void proceed()
    } else {
      scheduleAutoplay()
    }
  }

  onBeforeUnmount(() => stopAutoplay())

  return {
    session,
    replay,
    game,
    log,
    cards,
    stepFrom,
    loadError,
    error,
    diverged,
    busy,
    jumping,
    autoplay,
    ended,
    load,
    proceed,
    restart,
    jump,
    toggleAutoplay,
  }
}

export type ReplayContext = ReturnType<typeof useReplay>
