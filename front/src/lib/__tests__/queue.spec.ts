import { describe, expect, it } from 'vitest'

import type { Deck } from '@/api/client'
import { deckChoices, deckClan, waitingTime } from '@/lib/queue'

const starter = (clan: string, stronghold: string): Deck => ({
  id: `${clan}-starter-deck`,
  name: `${clan}-starter-deck`,
  owner_id: null,
  cards: { [stronghold]: 1, '01-fine-katana': 3 },
})
const crab = starter('crab', '01-shiro-nishiyama')
const crane = starter('crane', '01-shizuka-toshi')
const myCrab: Deck = { ...crab, id: 'deck-1', name: 'Crab starter deck', owner_id: 'john' }
const myCrane: Deck = { ...crane, id: 'deck-2', name: null, owner_id: 'john' }

describe('deckClan', () => {
  it('reads the clan of a deck from its stronghold', () => {
    expect(deckClan(crane)?.id).toBe('crane')
    expect(deckClan({ ...crane, cards: { '01-fine-katana': 3 } })).toBeUndefined()
  })
})

describe('deckChoices', () => {
  it('puts the decks of the user first, and names the starter decks by their clan', () => {
    const choices = deckChoices([crab, myCrab, crane], null)

    expect(choices.mine.map((choice) => choice.name)).toEqual(['Crab starter deck'])
    expect(choices.starters.map((choice) => choice.name)).toEqual(['Crab', 'Crane'])
  })

  it('chooses their own deck of their preferred clan, else its starter deck, else their first deck', () => {
    expect(deckChoices([crab, crane, myCrab, myCrane], 'crane').preselected).toBe('deck-2')
    expect(deckChoices([crab, crane, myCrab], 'crane').preselected).toBe('crane-starter-deck')
    expect(deckChoices([crab, myCrab], 'crane').preselected).toBe('deck-1')
    expect(deckChoices([crab], null).preselected).toBeNull()
  })

  it('names a deck without a name', () => {
    expect(deckChoices([myCrane], null).mine[0]!.name).toBe('Untitled deck')
  })
})

describe('waitingTime', () => {
  it('writes minutes and seconds, and hours beyond an hour', () => {
    expect(waitingTime(0)).toBe('0:00')
    expect(waitingTime(42.7)).toBe('0:42')
    expect(waitingTime(605)).toBe('10:05')
    expect(waitingTime(3725)).toBe('1:02:05')
  })
})
