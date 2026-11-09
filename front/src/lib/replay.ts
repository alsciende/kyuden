import type { LogEntry, LoggedMessage } from '@/api/game'
import type { ReplayAction, ReplayView } from '@/api/replay'

/**
 * The replay of a game that has ended, as its panel shows it: where it stands among the messages of the game, the
 * rounds and the actions of the player on its timeline, what the player does next, and the log of the whole game, the
 * messages to come dimmed. The log is the one of the game as the player read it, its ids those of the copy
 * (`fetchReplayLog`), so that it is told about the board of the copy.
 */

/** the entries of the log beginning each phase; a round begins with the dynasty phase */
const PHASE_BEGINS: Record<string, string> = {
  'The dynasty phase begins': 'Dynasty phase',
  'The draw phase begins': 'Draw phase',
  'The conflict phase begins': 'Conflict phase',
  'The fate phase begins': 'Fate phase',
}
const ROUND_BEGINS = 'The dynasty phase begins'

/** the messages a player sends that the replay does not stop before: the platform sends them for the player */
const PLATFORM_MESSAGES = new Set(['join_game', 'claim_abandonment'])

export type ReplayLogRow =
  | { kind: 'heading'; key: string; text: string; future: boolean }
  | {
      kind: 'message'
      key: string
      version: number
      entries: LogEntry[]
      /** replayed, the action of the player the replay stopped before, or to come */
      state: 'past' | 'next' | 'future'
      /** an action of the player */
      mine: boolean
    }

/** the replay stopped before an action of the player: the next message is theirs */
export function stoppedBefore(replay: ReplayView): ReplayAction | null {
  return replay.next !== null && replay.next.version === replay.version + 1 ? replay.next : null
}

function isActionOf(message: LoggedMessage, playerId: string): boolean {
  return message.player_id === playerId && !PLATFORM_MESSAGES.has(message.type)
}

/**
 * The log of the game for the panel: a heading at the beginning of each phase ("Round 2 · Draw phase"), then a row
 * per message that wrote something.
 */
export function replayLogRows(log: LoggedMessage[], replay: ReplayView): ReplayLogRow[] {
  const next = stoppedBefore(replay)
  const rows: ReplayLogRow[] = []
  let round = 0
  for (const message of log) {
    for (const entry of message.entries) {
      const phase = PHASE_BEGINS[entry.template]
      if (phase !== undefined) {
        round += entry.template === ROUND_BEGINS ? 1 : 0
        rows.push({
          kind: 'heading',
          key: `heading-${message.version}-${phase}`,
          text: `Round ${Math.max(round, 1)} · ${phase}`,
          future: message.version > replay.version,
        })
      }
    }
    if (message.entries.length === 0) {
      continue
    }
    rows.push({
      kind: 'message',
      key: `message-${message.version}`,
      version: message.version,
      entries: message.entries,
      state: message.version <= replay.version ? 'past' : message.version === next?.version ? 'next' : 'future',
      mine: isActionOf(message, replay.player_id),
    })
  }

  return rows
}

/** a mark on the timeline, where it stands among the messages of the game (0 to 1) */
export interface TimelineMark {
  at: number
  label: string
}

function at(version: number, total: number): number {
  return total === 0 ? 0 : Math.min(Math.max((version - 1) / total, 0), 1)
}

/** where each round begins */
export function roundMarks(log: LoggedMessage[], total: number): TimelineMark[] {
  return log
    .filter((message) => message.entries.some((entry) => entry.template === ROUND_BEGINS))
    .map((message, index) => ({ at: at(message.version, total), label: `R${index + 1}` }))
}

/** where each action of the player stands */
export function actionMarks(log: LoggedMessage[], replay: ReplayView): TimelineMark[] {
  return log
    .filter((message) => isActionOf(message, replay.player_id))
    .map((message) => ({ at: at(message.version, replay.total), label: `#${message.version}` }))
}

/** how far the replay has gone (0 to 1) */
export function progress(replay: ReplayView): number {
  return replay.total === 0 ? 1 : replay.version / replay.total
}

/** the round the replay stands in, 0 before the first one */
export function currentRound(log: LoggedMessage[], version: number): number {
  return log.filter(
    (message) => message.version <= version && message.entries.some((entry) => entry.template === ROUND_BEGINS),
  ).length
}

/** the entries telling the action of the player the replay stopped before */
export function nextEntries(log: LoggedMessage[], replay: ReplayView): LogEntry[] {
  const next = stoppedBefore(replay)

  return next === null ? [] : (log.find((message) => message.version === next.version)?.entries ?? [])
}

/** the entries of the messages a step has replayed, the version it started from excluded */
export function entriesSince(log: LoggedMessage[], from: number, to: number): LogEntry[] {
  return log.filter((message) => message.version > from && message.version <= to).flatMap((message) => message.entries)
}

/**
 * How the replay goes to a message (the board as it was once the message was handled): forward, it goes on up to it;
 * back, it starts again up to it; nothing to do when it stands there.
 */
export function jumpTo(replay: ReplayView, version: number): { direction: 'forward' | 'back'; until: number } | null {
  const target = Math.min(Math.max(version, 0), replay.total)
  if (target === replay.version) {
    return null
  }

  return { direction: target > replay.version ? 'forward' : 'back', until: target }
}
