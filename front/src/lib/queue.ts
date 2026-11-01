import type { ApiProblem, Deck } from '@/api/client'
import { CLANS, type Clan, type ClanId } from '@/data/clans'

/**
 * The decks offered in the queue: the user's own, then the starter decks, each with its clan (the clan of its
 * stronghold), and the deck chosen at first.
 */
export interface DeckChoices {
  mine: DeckChoice[]
  starters: DeckChoice[]
  preselected: string | null
}

export interface DeckChoice {
  deck: Deck
  clan: Clan | undefined
  /** the name shown: the name of the deck, or the clan of a starter deck */
  name: string
}

/**
 * The clan of a deck, read from its stronghold.
 */
export function deckClan(deck: Deck): Clan | undefined {
  return CLANS.find((clan) => (deck.cards[clan.strongholdCode] ?? 0) > 0)
}

export function deckChoice(deck: Deck): DeckChoice {
  const clan = deckClan(deck)
  // the starter decks are named by their id: the clan names them
  const name = deck.owner_id === null && clan ? clan.name : (deck.name ?? 'Untitled deck')

  return { deck, clan, name }
}

export function deckChoices(decks: Deck[], preferredClan: ClanId | null): DeckChoices {
  const choices = decks.map(deckChoice)
  const mine = choices.filter((choice) => choice.deck.owner_id !== null)
  const starters = choices.filter((choice) => choice.deck.owner_id === null)
  const ofClan = (choice: DeckChoice) => choice.clan?.id === preferredClan
  // their own deck of their clan first, then the starter deck of their clan
  const preselected = mine.find(ofClan) ?? starters.find(ofClan) ?? mine[0] ?? null

  return { mine, starters, preselected: preselected?.deck.id ?? null }
}

/**
 * The choices once a deck is imported: a deck the user already had stays in its place (the API answers the deck with
 * the same cards), any other comes first.
 */
export function withImported(choices: DeckChoices, deck: Deck): { choices: DeckChoices; alreadyOwned: boolean } {
  if (choices.mine.some((choice) => choice.deck.id === deck.id)) {
    return { choices, alreadyOwned: true }
  }

  return { choices: { ...choices, mine: [deckChoice(deck), ...choices.mine] }, alreadyOwned: false }
}

/**
 * The choices once a deck of the user is deleted.
 */
export function withoutDeck(choices: DeckChoices, deckId: string): DeckChoices {
  return { ...choices, mine: choices.mine.filter((choice) => choice.deck.id !== deckId) }
}

/**
 * Why a deck of EmeraldDB cannot be imported, from the problem of the API: what went wrong, and what it concerns (the
 * cards unknown to the engine, the deckbuilding rules broken), if anything.
 */
export interface ImportProblem {
  summary: string
  details: string[]
}

export function importProblem(problem: ApiProblem): ImportProblem {
  if (problem.type === 'deck_source_unavailable') {
    return { summary: 'EmeraldDB cannot be reached, try again later.', details: [] }
  }
  const url = problem.violations.find((violation) => violation.property_path === 'url')
  if (url) {
    return { summary: url.message, details: [] }
  }
  const unknown = problem.violations
    .map((violation) => /^cards\[(.+)\]$/.exec(violation.property_path)?.[1])
    .filter((id) => id !== undefined)
  if (unknown.length > 0) {
    const cards = unknown.length === 1 ? '1 of its cards is' : `${unknown.length} of its cards are`

    return {
      summary: `This deck cannot be imported: ${cards} not in Kyūden, which has the cards of Fantasy Flight Games only, not those of Emerald Legacy yet.`,
      details: [unknown.join(', ')],
    }
  }
  const rules = problem.violations.filter((violation) => violation.property_path === 'cards')
  if (rules.length > 0) {
    return {
      summary: 'This deck cannot be imported: it breaks the deckbuilding rules of the Stronghold format.',
      details: rules.map((violation) => violation.message),
    }
  }

  return { summary: problem.detail, details: [] }
}

/**
 * How long the user has waited, as `m:ss` (`h:mm:ss` beyond an hour).
 */
export function waitingTime(seconds: number): string {
  const whole = Math.max(0, Math.floor(seconds))
  const s = String(whole % 60).padStart(2, '0')
  const minutes = Math.floor(whole / 60)
  if (minutes < 60) {
    return `${minutes}:${s}`
  }

  return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}:${s}`
}
