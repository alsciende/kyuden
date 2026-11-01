import { describe, expect, it } from 'vitest'

import { ApiProblem, type Deck } from '@/api/client'
import { deckChoices, deckClan, importProblem, waitingTime, withImported, withoutDeck } from '@/lib/queue'

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

describe('withImported', () => {
  it('puts a new deck first', () => {
    const result = withImported(deckChoices([crab, myCrab], null), myCrane)

    expect(result.alreadyOwned).toBe(false)
    expect(result.choices.mine.map((choice) => choice.deck.id)).toEqual(['deck-2', 'deck-1'])
  })

  it('keeps a deck the user already has in its place', () => {
    const choices = deckChoices([crab, myCrab, myCrane], null)
    const result = withImported(choices, myCrane)

    expect(result.alreadyOwned).toBe(true)
    expect(result.choices).toBe(choices)
  })
})

describe('withoutDeck', () => {
  it('removes a deck of the user', () => {
    const choices = withoutDeck(deckChoices([crab, myCrab, myCrane], null), 'deck-1')

    expect(choices.mine.map((choice) => choice.deck.id)).toEqual(['deck-2'])
    expect(choices.starters).toHaveLength(1)
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

describe('importProblem', () => {
  const invalid = (...violations: [string, string][]) =>
    new ApiProblem(
      422,
      'invalid_deck',
      'The deck cannot be imported.',
      violations.map(([path, message]) => ({ property_path: path, message })),
    )

  it('tells the cards unknown to the engine, which has no card of Emerald Legacy', () => {
    const problem = importProblem(
      invalid(['cards[ashen-flame-plateau]', 'Unknown card.'], ['cards[earth-s-examination]', 'Unknown card.']),
    )

    expect(problem.summary).toBe(
      'This deck cannot be imported: 2 of its cards are not in Kyūden, which has the cards of Fantasy Flight Games only, not those of Emerald Legacy yet.',
    )
    expect(problem.details).toEqual(['ashen-flame-plateau, earth-s-examination'])
    expect(importProblem(invalid(['cards[ashen-flame-plateau]', 'Unknown card.'])).summary).toContain(
      '1 of its cards is not in Kyūden',
    )
  })

  it('lists the deckbuilding rules broken', () => {
    const problem = importProblem(invalid(['cards', 'The deck must have exactly 1 stronghold.']))

    expect(problem.summary).toContain('it breaks the deckbuilding rules')
    expect(problem.details).toEqual(['The deck must have exactly 1 stronghold.'])
  })

  it('tells why the URL names no deck', () => {
    expect(importProblem(invalid(['url', 'Not the URL of a deck of EmeraldDB.']))).toEqual({
      summary: 'Not the URL of a deck of EmeraldDB.',
      details: [],
    })
  })

  it('asks to try again when EmeraldDB cannot be reached, and gives any other problem as is', () => {
    expect(importProblem(new ApiProblem(502, 'deck_source_unavailable', 'Bad gateway')).summary).toBe(
      'EmeraldDB cannot be reached, try again later.',
    )
    expect(importProblem(new ApiProblem(401, 'unauthorized', 'Invalid token.')).summary).toBe('Invalid token.')
  })
})
