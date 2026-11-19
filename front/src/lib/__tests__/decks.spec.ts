import { describe, expect, it } from 'vitest'

import type { Card, DeckRecord, OwnedDeck } from '@/api/client'
import {
  clanCounts,
  dayLabel,
  deckContents,
  deckRows,
  endingRows,
  jigokuExport,
  opponentRows,
  recordTiles,
  winRate,
} from '@/lib/decks'

const now = new Date(2026, 10, 19, 18, 0)

const card = (code: string, name: string, side: string, type: string, elements?: string[]): Card => ({
  code,
  name,
  side,
  type,
  clan: 'crane',
  elements: elements ?? null,
  image_url: `https://cards.example/${code}.webp`,
})

const cards: Record<string, Card> = Object.fromEntries(
  [
    card('shizuka', 'Shizuka Toshi', 'stronghold', 'stronghold'),
    card('keeper', 'Keeper of Water', 'role', 'role'),
    card('night-raid', 'Night Raid', 'province', 'province', ['fire']),
    card('fury', 'Elemental Fury', 'province', 'province', ['air', 'earth']),
    card('whisperer', 'Doji Whisperer', 'dynasty', 'character'),
    card('artisan', 'Asahina Artisan', 'dynasty', 'character'),
    card('academy', 'Artisan Academy', 'dynasty', 'holding'),
    card('rally', 'Rally to the Cause', 'dynasty', 'event'),
    card('rival', 'Political Rival', 'conflict', 'character'),
    card('banzai', 'Banzai!', 'conflict', 'event'),
  ].map((entry) => [entry.code, entry]),
)

const emptyRecord: DeckRecord = {
  played: 0,
  won: 0,
  lost: 0,
  in_progress: 0,
  ranked_won: 0,
  ranked_lost: 0,
  last_game: null,
  endings: [],
  opponents: [],
}

const record: DeckRecord = {
  played: 5,
  won: 3,
  lost: 1,
  in_progress: 1,
  ranked_won: 2,
  ranked_lost: 1,
  last_game: { game_id: 'g', ended_at: new Date(2026, 10, 19, 9, 0).toISOString(), won: true, opponent_clan: 'lion' },
  endings: [
    { victory_condition: 'honor_win', won: 2, lost: 0 },
    { victory_condition: 'concession', won: 1, lost: 1 },
  ],
  opponents: [
    { clan: 'lion', won: 2, lost: 1 },
    { clan: 'neutral', won: 1, lost: 0 },
  ],
}

describe('dayLabel', () => {
  it('tells the day as a player says it', () => {
    expect(dayLabel(new Date(2026, 10, 19, 9).toISOString(), now)).toBe('Today')
    expect(dayLabel(new Date(2026, 10, 18, 9).toISOString(), now)).toBe('Yesterday')
    expect(dayLabel(new Date(2026, 10, 3, 9).toISOString(), now)).toBe('3 Nov')
    expect(dayLabel(new Date(2025, 11, 31, 9).toISOString(), now)).toBe('31 Dec 2025')
    expect(dayLabel(null, now)).toBe('Never')
  })
})

describe('winRate', () => {
  it('rounds the share of the games won, none before the first game', () => {
    expect(winRate(2, 3)).toBe('67%')
    expect(winRate(0, 0)).toBe('–')
  })
})

describe('deckRows', () => {
  it('names each deck, its stronghold and its record in short', () => {
    const decks: OwnedDeck[] = [
      {
        deck: { id: 'd1', name: 'Duelists', owner_id: 'me', cards: {}, stronghold: 'shizuka', clan: 'crane' },
        record,
      },
      { deck: { id: 'd2', name: null, owner_id: 'me', cards: {}, stronghold: null, clan: null }, record: emptyRecord },
    ]

    const rows = deckRows(decks, cards, now)

    expect(rows.map((row) => [row.name, row.stronghold, row.played, row.won, row.rate, row.lastPlayed])).toEqual([
      ['Duelists', 'Shizuka Toshi', 5, '3', '60%', 'Today'],
      ['Untitled deck', '', 0, '–', '–', 'Never'],
    ])
    expect(rows[0]?.clan?.id).toBe('crane')
  })
})

describe('recordTiles', () => {
  it('tells the games played, won, lost, ranked and the last one', () => {
    expect(recordTiles(record, now)).toEqual([
      { label: 'Played', value: '5', detail: '1 in progress' },
      { label: 'Won', value: '3', detail: '60% of the games' },
      { label: 'Lost', value: '1', detail: '20% of the games' },
      { label: 'Ranked', value: '2–1', detail: 'queue and tournaments' },
      { label: 'Last played', value: 'Today', detail: 'won against Lion' },
    ])
  })

  it('tells nothing more of a deck never played', () => {
    expect(recordTiles(emptyRecord, now).map((tile) => [tile.value, tile.detail])).toEqual([
      ['0', ''],
      ['0', ''],
      ['0', ''],
      ['0–0', 'queue and tournaments'],
      ['Never', ''],
    ])
  })
})

describe('endingRows and opponentRows', () => {
  it('name the victory conditions and the clans', () => {
    expect(endingRows(record)).toEqual([
      { label: '25 honor reached', won: 2, lost: 0 },
      { label: 'Concession', won: 1, lost: 1 },
    ])
    // the neutral clan is not a clan of the site
    expect(opponentRows(record).map((row) => [row.clan.name, row.won, row.lost])).toEqual([['Lion', 2, 1]])
  })
})

describe('deckContents', () => {
  it('lists the cards by their titles: stronghold, role, provinces, then each deck by type', () => {
    const contents = deckContents(
      {
        whisperer: 3,
        artisan: 2,
        academy: 1,
        rally: 1,
        rival: 2,
        banzai: 3,
        shizuka: 1,
        keeper: 1,
        'night-raid': 1,
        fury: 1,
      },
      cards,
    )

    expect(contents.stronghold?.name).toBe('Shizuka Toshi')
    expect(contents.role?.name).toBe('Keeper of Water')
    expect(contents.provinces.map((province) => [province.name, province.elements])).toEqual([
      ['Elemental Fury', ['air', 'earth']],
      ['Night Raid', ['fire']],
    ])
    expect(
      contents.sides.map((side) => [
        side.title,
        side.count,
        side.groups.map((group) => [
          group.title,
          group.count,
          group.cards.map((entry) => `${entry.quantity} ${entry.name}`),
        ]),
      ]),
    ).toEqual([
      [
        'Dynasty deck',
        7,
        [
          ['Characters', 5, ['2 Asahina Artisan', '3 Doji Whisperer']],
          ['Holdings', 1, ['1 Artisan Academy']],
          ['Events', 1, ['1 Rally to the Cause']],
        ],
      ],
      [
        'Conflict deck',
        5,
        [
          ['Characters', 2, ['2 Political Rival']],
          ['Events', 3, ['3 Banzai!']],
        ],
      ],
    ])
  })
})

describe('jigokuExport', () => {
  it('writes a line per card, count, title and pack, in the order of the page, nothing else', () => {
    const packed = Object.fromEntries(
      Object.entries(cards).map(([code, entry]) => [
        code,
        { ...entry, pack: code === 'banzai' ? 'Core Set' : 'Clan War' },
      ]),
    )

    expect(jigokuExport({ banzai: 3, whisperer: 2, shizuka: 1, 'night-raid': 1, keeper: 1 }, packed)).toBe(
      [
        '1 Shizuka Toshi (Clan War)',
        '1 Keeper of Water (Clan War)',
        '1 Night Raid (Clan War)',
        '2 Doji Whisperer (Clan War)',
        '3 Banzai! (Core Set)',
      ].join('\n'),
    )
  })

  it('leaves out the pack of a card it does not know', () => {
    expect(jigokuExport({ banzai: 3 }, cards)).toBe('3 Banzai!')
  })
})

describe('clanCounts', () => {
  it('counts the cards of each clan, the most first, the neutral cards last', () => {
    const clans = Object.fromEntries(
      Object.entries({
        shizuka: 'crane',
        whisperer: 'crane',
        banzai: 'neutral',
        rival: 'neutral',
        fury: 'lion',
        keeper: 'neutral',
      }).map(([code, clan]) => [code, { ...cards[code]!, clan }]),
    )

    expect(
      clanCounts({ shizuka: 1, whisperer: 3, banzai: 3, rival: 2, fury: 1, keeper: 1, unknown: 3 }, clans),
    ).toEqual([
      { clan: 'crane', name: 'Crane', count: 4 },
      { clan: 'lion', name: 'Lion', count: 1 },
      { clan: 'neutral', name: 'Neutral', count: 6 },
    ])
  })
})
