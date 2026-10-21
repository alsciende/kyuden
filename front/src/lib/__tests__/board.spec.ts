import { describe, expect, it } from 'vitest'

import {
  charactersInPlay,
  conflictsLeft,
  gloryCount,
  locate,
  provinceStrength,
  provinces,
  rings,
  sideSkill,
  skillOf,
  stepOf,
  strongholdFate,
  visibleCodes,
} from '@/lib/board'

import { card, game, player, printed, stack } from './gameFixture'

const cards = {
  samurai: printed('samurai', { military: '2', political: '1', glory: 1 }),
  courtier: printed('courtier', { military: '-', political: '3', glory: 2 }),
  katana: printed('katana', { type: 'attachment', military_bonus: '+2' }),
  stronghold: printed('stronghold', { type: 'stronghold', fate: 7, strength_bonus: '+2' }),
  garden: printed('garden', { type: 'province', strength: 4 }),
  raid: printed('raid', { type: 'province', strength: 3 }),
}

describe('provinces', () => {
  it('reads the province at the bottom of each stack, the stronghold province last', () => {
    const raid = card('raid')
    const samurai = card('samurai')
    const garden = card('garden', { orientation: 'upside_down' })
    const stronghold = card('stronghold')
    const me = player('me', [
      stack('stronghold_province', [stronghold, garden]),
      stack('regular_province', [raid, samurai]),
    ])

    const [regular, strongholdProvince] = provinces(me)

    expect(regular).toMatchObject({ province: raid, cards: [samurai], stronghold: null, broken: false })
    expect(strongholdProvince).toMatchObject({ province: garden, cards: [], stronghold, broken: true })
  })
})

describe('characters', () => {
  it('reads each character in play with its attachments', () => {
    const samurai = card('samurai')
    const katana = card('katana')
    const me = player('me', [stack('in_play', [samurai, katana]), stack('in_play', [])])

    expect(charactersInPlay(me)).toEqual([expect.objectContaining({ card: samurai, attachments: [katana] })])
  })

  it('locates a card with the card under it', () => {
    const samurai = card('samurai')
    const katana = card('katana')
    const view = game(player('me', [stack('in_play', [samurai, katana])]), player('opp'))

    expect(locate(view, katana.id)).toMatchObject({ card: katana, under: samurai })
    expect(locate(view, samurai.id)).toMatchObject({ card: samurai, under: null })
    expect(locate(view, 'unknown')).toBeUndefined()
  })
})

describe('skills', () => {
  it('adds the bonuses of the attachments and the glory of an honored character', () => {
    const me = player('me', [stack('in_play', [card('samurai', { personal_honor: 'honored' }), card('katana')])])

    expect(skillOf(charactersInPlay(me)[0]!, cards, 'military')).toBe(2 + 2 + 1)
  })

  it('subtracts the glory of a dishonored character, down to 0', () => {
    const me = player('me', [stack('in_play', [card('courtier', { personal_honor: 'dishonored' })])])

    expect(skillOf(charactersInPlay(me)[0]!, cards, 'political')).toBe(1)
    expect(skillOf(charactersInPlay(me)[0]!, cards, 'military')).toBe(0)
  })

  it('takes the skill recorded by the players', () => {
    const me = player('me', [stack('in_play', [card('samurai', { military_skill: 7 }), card('katana')])])

    expect(skillOf(charactersInPlay(me)[0]!, cards, 'military')).toBe(7)
  })

  it('sums the ready characters of a side', () => {
    const samurai = card('samurai')
    const courtier = card('courtier')
    const bowed = card('samurai', { orientation: 'sideways' })
    const view = game(
      player('me', [stack('in_play', [samurai]), stack('in_play', [courtier]), stack('in_play', [bowed])]),
      player('opp'),
    )

    expect(sideSkill(view, cards, [samurai.id, courtier.id, bowed.id], 'political')).toBe(4)
  })
})

describe('the values prefilled for the players', () => {
  it('reads the fate of the stronghold and the strength of the provinces', () => {
    const garden = card('garden')
    const raid = card('raid')
    const me = player('me', [
      stack('stronghold_province', [card('stronghold'), garden]),
      stack('regular_province', [raid]),
    ])
    const view = game(me, player('opp'))

    expect(strongholdFate(me, cards)).toBe(7)
    expect(provinceStrength(view, cards, garden.id)).toBe(6)
    expect(provinceStrength(view, cards, raid.id)).toBe(3)
  })

  it('counts the glory of the ready characters and the claimed rings', () => {
    const me = player('me', [
      stack('in_play', [card('courtier')]),
      stack('in_play', [card('samurai', { orientation: 'sideways' })]),
    ])
    const view = game(me, player('opp'), {
      rings: {
        air: { fate: 0, claimed_by_id: 'me' },
        earth: { fate: 0, claimed_by_id: 'opp' },
        fire: { fate: 0, claimed_by_id: null },
        void: { fate: 0, claimed_by_id: 'me' },
        water: { fate: 0, claimed_by_id: null },
      },
    })

    expect(gloryCount(view, me, cards)).toBe(2 + 2)
  })
})

describe('the state of the game', () => {
  it('tells the rings unclaimed, contested and claimed', () => {
    const view = game(player('me'), player('opp'), {
      rings: {
        air: { fate: 2, claimed_by_id: null },
        earth: { fate: 0, claimed_by_id: 'opp' },
        fire: { fate: 0, claimed_by_id: null },
        void: { fate: 0, claimed_by_id: 'me' },
        water: { fate: 1, claimed_by_id: null },
      },
      conflict: {
        attacking_player_id: 'me',
        defending_player_id: 'opp',
        ring: 'fire',
        type: 'military',
        province_id: 'p',
        attacked_province_ids: ['p'],
        attacker_ids: [],
        defender_ids: [],
        evaded_ids: [],
        attacker_skill: null,
        defender_skill: null,
        province_strengths: {},
        winner_id: null,
        broken_province_ids: [],
      },
    })

    expect(rings(view).map((ring) => [ring.element, ring.status, ring.fate])).toEqual([
      ['air', 'unclaimed', 2],
      ['earth', 'theirs', 0],
      ['fire', 'contested', 0],
      ['water', 'unclaimed', 1],
      ['void', 'yours', 0],
    ])
  })

  it('tells the conflicts a player may still declare', () => {
    expect(conflictsLeft(player('me', [], { declared_conflict_types: ['political'] }))).toEqual(['military'])
    expect(conflictsLeft(player('me', [], { conflict_opportunities: 0 }))).toEqual([])
  })

  it('names the step of the round of a place', () => {
    expect(stepOf('declare_conflict')).toMatchObject({ phase: 'conflict', title: 'Declare a conflict' })
    expect(stepOf('fate_phase_begins')).toMatchObject({ phase: 'fate', title: 'Fate phase begins' })
  })

  it('lists the codes the viewer sees', () => {
    const view = game(
      player('me', [stack('hand', [card('samurai'), card('courtier')])]),
      player('opp', [stack('hand', null), stack('regular_province', [card(null), card('samurai')])]),
    )

    expect(visibleCodes(view).sort()).toEqual(['courtier', 'samurai'])
  })
})
