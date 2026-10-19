import { describe, expect, it } from 'vitest'

import { CLANS, findClanByDeck } from '@/data/clans'

describe('the clans', () => {
  it('are the seven Great Clans, each with its starter deck', () => {
    expect(CLANS.map((clan) => clan.id)).toEqual(['crab', 'crane', 'dragon', 'lion', 'phoenix', 'scorpion', 'unicorn'])
    for (const clan of CLANS) {
      expect(clan.deckId).toBe(`${clan.id}-starter-deck`)
      expect(findClanByDeck(clan.deckId)).toBe(clan)
    }
  })
})
