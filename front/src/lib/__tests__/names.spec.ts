import { describe, expect, it } from 'vitest'

import { CLANS } from '@/data/clans'
import { drawCourtName, GIVEN_NAMES } from '@/lib/names'

describe('drawCourtName', () => {
  it('puts a family of the clan first, then a given name', () => {
    for (const clan of CLANS) {
      const [family, given, ...rest] = drawCourtName(clan).split(' ')

      expect(clan.families).toContain(family)
      expect(GIVEN_NAMES).toContain(given)
      expect(rest).toEqual([])
    }
  })

  it('draws with the random generator it is given', () => {
    const lion = CLANS.find((clan) => clan.id === 'lion')!

    expect(drawCourtName(lion, () => 0)).toBe(`Akodo ${GIVEN_NAMES[0]}`)
    expect(drawCourtName(lion, () => 0.999)).toBe(`Kitsu ${GIVEN_NAMES[GIVEN_NAMES.length - 1]}`)
  })
})
