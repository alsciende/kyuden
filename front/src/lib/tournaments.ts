import type { Tournament, TournamentMatch, TournamentStatus, TournamentSummary } from '@/api/client'

/**
 * The tournaments as the web client shows them: their dates in the time zone of the viewer (named on the pages, the
 * API giving them in UTC), the list split by where each stands, the size of the bracket, and the start chosen when
 * creating one (on the half hour in UTC, at least 12 hours ahead, 12 hours from any other tournament); once under way,
 * its rounds, the match of the viewer and how each match ended.
 */

/** how long before its start a tournament locks */
export const LOCK_LEAD_MS = 60 * 60 * 1000
/** how far ahead a tournament begins at the least, and how far apart two tournaments begin */
export const MINIMUM_NOTICE_MS = 12 * 60 * 60 * 1000
const STEP_MS = 30 * 60 * 1000
const DAY_MS = 24 * 60 * 60 * 1000
const MAX_BRACKET = 64

/** the time zone of the browser, e.g. Europe/Paris */
export function viewerTimeZone(): string {
  return Intl.DateTimeFormat().resolvedOptions().timeZone
}

function format(iso: string | Date, timeZone: string, options: Intl.DateTimeFormatOptions): string {
  return new Intl.DateTimeFormat('en-GB', { timeZone, ...options }).format(
    typeof iso === 'string' ? new Date(iso) : iso,
  )
}

/** "Saturday 21 November" */
export function dayLabel(iso: string, timeZone: string): string {
  return format(iso, timeZone, { weekday: 'long', day: 'numeric', month: 'long' })
}

/** "20:00" */
export function timeLabel(iso: string, timeZone: string): string {
  return format(iso, timeZone, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
}

/** "Saturday 21 November, 20:00" */
export function startLabel(iso: string, timeZone: string): string {
  return `${dayLabel(iso, timeZone)}, ${timeLabel(iso, timeZone)}`
}

/** "Saturday 19:00": a time of the schedule, the day of the week being enough so close to it */
export function weekdayTimeLabel(iso: string, timeZone: string): string {
  return `${format(iso, timeZone, { weekday: 'long' })} ${timeLabel(iso, timeZone)}`
}

/** the day of a moment in a time zone, `2026-11-21` (the value of an `<input type="date">`) */
export function localDateOf(iso: string | Date, timeZone: string): string {
  return format(iso, timeZone, { year: 'numeric', month: '2-digit', day: '2-digit' }).split('/').reverse().join('-')
}

/** "today", "tomorrow", "in 3 days": how many days of the calendar of the viewer until then */
export function inDaysLabel(iso: string, now: Date, timeZone: string): string {
  const days = Math.round((Date.parse(localDateOf(iso, timeZone)) - Date.parse(localDateOf(now, timeZone))) / DAY_MS)
  if (days <= 0) {
    return 'today'
  }

  return days === 1 ? 'tomorrow' : `in ${days} days`
}

/** whether the players still register, change their deck or unregister, and its organizer cancels it */
export function isOpen(tournament: { status: TournamentStatus; locks_at: string }, now: Date): boolean {
  return tournament.status === 'open' && now.getTime() < Date.parse(tournament.locks_at)
}

/** the size of the bracket for so many players: the smallest power of 2 holding them, null below 2 players */
export function bracketSize(players: number): number | null {
  if (players < 2) {
    return null
  }
  let size = 2
  while (size < players && size < MAX_BRACKET) {
    size *= 2
  }

  return size
}

export interface TournamentGroups {
  inProgress: TournamentSummary[]
  /** open or locked, not begun yet */
  upcoming: TournamentSummary[]
  cancelled: TournamentSummary[]
}

/** the list of the API, split as /tournaments shows it, each part in the order of the list (the first to begin first) */
export function groupTournaments(tournaments: TournamentSummary[]): TournamentGroups {
  return {
    inProgress: tournaments.filter((tournament) => tournament.status === 'in_progress'),
    upcoming: tournaments.filter((tournament) => tournament.status === 'open' || tournament.status === 'locked'),
    cancelled: tournaments.filter((tournament) => tournament.status === 'cancelled'),
  }
}

/** a start the viewer may choose, in UTC, and its time in their time zone */
export interface StartSlot {
  iso: string
  label: string
}

/** the first start allowed: on the half hour, at least 12 hours from now */
export function earliestStart(now: Date): Date {
  return new Date(Math.ceil((now.getTime() + MINIMUM_NOTICE_MS) / STEP_MS) * STEP_MS)
}

/**
 * The starts of a day of the viewer (`2026-11-21`): every half hour in UTC falling on that day in their time zone
 * (the times follow its offset, the changes of the clock included), those before the first start allowed left out.
 */
export function slotsOfDay(day: string, timeZone: string, now: Date): StartSlot[] {
  const midnight = Date.parse(`${day}T00:00:00Z`)
  if (Number.isNaN(midnight)) {
    return []
  }
  const earliest = earliestStart(now).getTime()
  const slots: StartSlot[] = []
  // the offsets of the time zones stay within 14 hours of UTC
  for (let time = midnight - 14 * 60 * 60 * 1000; time < midnight + DAY_MS + 14 * 60 * 60 * 1000; time += STEP_MS) {
    const iso = new Date(time).toISOString()
    if (time >= earliest && localDateOf(iso, timeZone) === day) {
      slots.push({ iso, label: timeLabel(iso, timeZone) })
    }
  }

  return slots
}

/** another tournament too close to a start, and the starts that would leave 12 hours between them */
export interface StartConflict {
  other: TournamentSummary
  /** the last start allowed before it */
  earlierLimit: string
  /** the first start allowed after it */
  laterLimit: string
}

/** the closest tournament still to be played beginning less than 12 hours before or after the start, if any */
export function conflictOf(startsAt: string, tournaments: TournamentSummary[]): StartConflict | null {
  const start = Date.parse(startsAt)
  const others = tournaments
    .filter((tournament) => tournament.status !== 'cancelled' && tournament.status !== 'ended')
    .filter((tournament) => Math.abs(Date.parse(tournament.starts_at) - start) < MINIMUM_NOTICE_MS)
    .sort((a, b) => Math.abs(Date.parse(a.starts_at) - start) - Math.abs(Date.parse(b.starts_at) - start))
  const other = others[0]
  if (other === undefined) {
    return null
  }
  const otherStart = Date.parse(other.starts_at)

  return {
    other,
    earlierLimit: new Date(otherStart - MINIMUM_NOTICE_MS).toISOString(),
    laterLimit: new Date(otherStart + MINIMUM_NOTICE_MS).toISOString(),
  }
}

/** "Round 4 · final", "Round 3 · semifinals", "Round 1" */
export function roundTitle(number: number, roundCount: number | null): string {
  const fromLast = roundCount === null ? null : roundCount - number
  const name = fromLast === 0 ? 'final' : fromLast === 1 ? 'semifinals' : fromLast === 2 ? 'quarterfinals' : null

  return name === null ? `Round ${number}` : `Round ${number} · ${name}`
}

const VICTORIES: Record<string, string> = {
  honor_win: 'honor',
  honor_loss: 'honor lost',
  stronghold_province_broken: 'stronghold broken',
  concession: 'concession',
  abandonment: 'abandonment',
}

/** how a match ended, as told under its players: "won, concession", "bye", "both eliminated"; null while played */
export function matchResultLabel(match: TournamentMatch): string | null {
  switch (match.outcome) {
    case null:
      return null
    case 'bye':
      return 'bye'
    case 'forfeit':
      return 'won, forfeit'
    case 'no_result':
      return 'both eliminated'
    case 'game':
      return match.victory_condition === null ? 'won' : `won, ${VICTORIES[match.victory_condition] ?? 'game'}`
  }
}

/** the match of the user in the current round, if they play one */
export function currentMatchOf(tournament: Tournament, userId: string | null): TournamentMatch | null {
  if (userId === null) {
    return null
  }
  const round = tournament.rounds.find((candidate) => candidate.number === tournament.current_round)

  return round?.matches.find((match) => match.players.some((player) => player.user_id === userId)) ?? null
}

/** "8:24": the time left until a deadline, 0:00 once over */
export function countdownLabel(deadline: string, now: number): string {
  const seconds = Math.max(0, Math.ceil((Date.parse(deadline) - now) / 1000))

  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
}
