import type { Deck } from '@/api/client'
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

export function deckChoices(decks: Deck[], preferredClan: ClanId | null): DeckChoices {
  const choices = decks.map((deck): DeckChoice => {
    const clan = deckClan(deck)
    // the starter decks are named by their id: the clan names them
    const name = deck.owner_id === null && clan ? clan.name : (deck.name ?? 'Untitled deck')

    return { deck, clan, name }
  })
  const mine = choices.filter((choice) => choice.deck.owner_id !== null)
  const starters = choices.filter((choice) => choice.deck.owner_id === null)
  const ofClan = (choice: DeckChoice) => choice.clan?.id === preferredClan
  // their own deck of their clan first, then the starter deck of their clan
  const preselected = mine.find(ofClan) ?? starters.find(ofClan) ?? mine[0] ?? null

  return { mine, starters, preselected: preselected?.deck.id ?? null }
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
