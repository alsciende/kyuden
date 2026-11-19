import type { Card, DeckRecord, OwnedDeck } from '@/api/client'
import { findClan, type Clan } from '@/data/clans'

/**
 * The decks of the user and the page of one of them: their record, and their cards by their titles.
 */

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const sameDay = (a: Date, b: Date) =>
  a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()

/** "Today", "Yesterday", "6 Nov", or with the year when it is not the current one; "Never" without a date */
export function dayLabel(iso: string | null | undefined, now: Date = new Date()): string {
  if (iso === null || iso === undefined) {
    return 'Never'
  }
  const date = new Date(iso)
  if (sameDay(date, now)) {
    return 'Today'
  }
  if (sameDay(date, new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1))) {
    return 'Yesterday'
  }
  const day = `${date.getDate()} ${MONTHS[date.getMonth()]}`

  return date.getFullYear() === now.getFullYear() ? day : `${day} ${date.getFullYear()}`
}

/** the share of the games won, "–" before the first game ended */
export function winRate(won: number, played: number): string {
  return played === 0 ? '–' : `${Math.round((won * 100) / played)}%`
}

/** the name of a deck of the user */
export function deckName(name: string | null): string {
  return name ?? 'Untitled deck'
}

/**
 * A deck of the list: its name, its clan and stronghold, and its record in short.
 */
export interface DeckRow {
  id: string
  name: string
  clan: Clan | undefined
  stronghold: string
  played: number
  won: string
  rate: string
  lastPlayed: string
}

export function deckRows(decks: OwnedDeck[], cards: Record<string, Card>, now: Date = new Date()): DeckRow[] {
  return decks.map(({ deck, record }) => ({
    id: deck.id,
    name: deckName(deck.name),
    clan: findClan(deck.clan),
    stronghold: deck.stronghold === null ? '' : (cards[deck.stronghold]?.name ?? ''),
    played: record.played,
    won: record.played === 0 ? '–' : String(record.won),
    rate: winRate(record.won, record.played),
    lastPlayed: dayLabel(record.last_game?.ended_at, now),
  }))
}

/**
 * The tiles of the record of a deck: played, won, lost, ranked, last played.
 */
export interface RecordTile {
  label: string
  value: string
  detail: string
}

export function recordTiles(record: DeckRecord, now: Date = new Date()): RecordTile[] {
  const share = (count: number) => (record.played === 0 ? '' : `${winRate(count, record.played)} of the games`)
  const last = record.last_game
  const opponent = findClan(last?.opponent_clan)
  let lastDetail = ''
  if (last !== null) {
    const result = last.won === null ? 'no winner' : last.won ? 'won' : 'lost'
    lastDetail = opponent ? `${result} against ${opponent.name}` : result
  }

  return [
    {
      label: 'Played',
      value: String(record.played),
      detail: record.in_progress === 0 ? '' : `${record.in_progress} in progress`,
    },
    { label: 'Won', value: String(record.won), detail: share(record.won) },
    { label: 'Lost', value: String(record.lost), detail: share(record.lost) },
    { label: 'Ranked', value: `${record.ranked_won}–${record.ranked_lost}`, detail: 'queue and tournaments' },
    { label: 'Last played', value: dayLabel(last?.ended_at, now), detail: lastDetail },
  ]
}

const ENDINGS: Record<string, string> = {
  honor_win: '25 honor reached',
  honor_loss: 'No honor left',
  stronghold_province_broken: 'Stronghold province broken',
  concession: 'Concession',
  abandonment: 'Abandonment',
}

/** how the games of a deck ended, won and lost */
export function endingRows(record: DeckRecord): { label: string; won: number; lost: number }[] {
  return record.endings.map((ending) => ({
    label: ENDINGS[ending.victory_condition] ?? ending.victory_condition,
    won: ending.won,
    lost: ending.lost,
  }))
}

/** the games of a deck won and lost against each clan faced */
export function opponentRows(record: DeckRecord): { clan: Clan; won: number; lost: number }[] {
  return record.opponents.flatMap((opponent) => {
    const clan = findClan(opponent.clan)

    return clan ? [{ clan, won: opponent.won, lost: opponent.lost }] : []
  })
}

/**
 * A card of a deck, by its title.
 */
export interface DeckCard {
  code: string
  name: string
  quantity: number
  /** the elements of a province */
  elements: string[]
}

export interface DeckCardGroup {
  title: string
  count: number
  cards: DeckCard[]
}

export interface DeckSide {
  title: string
  count: number
  groups: DeckCardGroup[]
}

/**
 * The cards of a deck as its page lists them: the stronghold and the role, the provinces, then the dynasty deck and
 * the conflict deck, by type, each in the order of the titles.
 */
export interface DeckContents {
  stronghold: DeckCard | null
  role: DeckCard | null
  provinces: DeckCard[]
  sides: DeckSide[]
}

const DYNASTY_TYPES: [string, string][] = [
  ['character', 'Characters'],
  ['holding', 'Holdings'],
  ['event', 'Events'],
]
const CONFLICT_TYPES: [string, string][] = [
  ['character', 'Characters'],
  ['attachment', 'Attachments'],
  ['event', 'Events'],
]

const count = (cards: DeckCard[]) => cards.reduce((total, card) => total + card.quantity, 0)

export function deckContents(quantities: Record<string, number>, cards: Record<string, Card>): DeckContents {
  const list: (DeckCard & { card: Card | undefined })[] = Object.entries(quantities)
    .map(([code, quantity]) => {
      const card = cards[code]

      return { code, name: card?.name ?? code, quantity, elements: card?.elements ?? [], card }
    })
    .sort((a, b) => a.name.localeCompare(b.name))
  const strip = (entry: DeckCard & { card: Card | undefined }): DeckCard => ({
    code: entry.code,
    name: entry.name,
    quantity: entry.quantity,
    elements: entry.elements,
  })
  const ofType = (type: string) => list.filter((entry) => entry.card?.type === type).map(strip)
  const side = (title: string, sideName: string, types: [string, string][]): DeckSide => {
    const groups = types
      .map(([type, groupTitle]) => {
        const groupCards = list.filter((entry) => entry.card?.side === sideName && entry.card.type === type).map(strip)

        return { title: groupTitle, count: count(groupCards), cards: groupCards }
      })
      .filter((group) => group.cards.length > 0)

    return { title, count: groups.reduce((total, group) => total + group.count, 0), groups }
  }

  return {
    stronghold: ofType('stronghold')[0] ?? null,
    role: ofType('role')[0] ?? null,
    provinces: ofType('province'),
    sides: [side('Dynasty deck', 'dynasty', DYNASTY_TYPES), side('Conflict deck', 'conflict', CONFLICT_TYPES)],
  }
}

/**
 * A deck in the format of Jigoku, which EmeraldDB imports among others: a line `<count> <title> (<pack>)` per card,
 * nothing else (no name, no heading), in the order of the page of the deck.
 */
export function jigokuExport(quantities: Record<string, number>, cards: Record<string, Card>): string {
  const contents = deckContents(quantities, cards)
  const ordered = [
    contents.stronghold,
    contents.role,
    ...contents.provinces,
    ...contents.sides.flatMap((side) => side.groups.flatMap((group) => group.cards)),
  ].filter((card) => card !== null)

  return ordered
    .map((card) => {
      const pack = cards[card.code]?.pack

      return pack ? `${card.quantity} ${card.name} (${pack})` : `${card.quantity} ${card.name}`
    })
    .join('\n')
}

/**
 * How many cards of a deck each clan has, the most first (then by name), the neutral cards last.
 */
export function clanCounts(
  quantities: Record<string, number>,
  cards: Record<string, Card>,
): { clan: string; name: string; count: number }[] {
  const counts = new Map<string, number>()
  for (const [code, quantity] of Object.entries(quantities)) {
    const clan = cards[code]?.clan
    if (clan) {
      counts.set(clan, (counts.get(clan) ?? 0) + quantity)
    }
  }
  const rows = [...counts].map(([clan, count]) => ({ clan, name: findClan(clan)?.name ?? 'Neutral', count }))
  const neutral = (row: { clan: string }) => Number(row.clan === 'neutral')

  return rows.sort((a, b) => neutral(a) - neutral(b) || b.count - a.count || a.name.localeCompare(b.name))
}
