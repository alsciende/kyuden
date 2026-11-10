import { describe, expect, it } from 'vitest'

import {
  attackableProvinces,
  cardsUnder,
  setAsideNote,
  nameOf,
  charactersInPlay,
  conflictResolved,
  conflictsLeft,
  gloryCount,
  locate,
  provinceStrength,
  provinceToReveal,
  provinceCards,
  provinces,
  rings,
  shownSkill,
  sideSkill,
  skillOf,
  stepOf,
  strongholdFate,
  strongholdTooEarly,
  switchableRings,
  resultTitle,
  victoryText,
  visibleCodes,
} from '@/lib/board'

import { card, game, player, printed, stack, underneath } from './gameFixture'

const cards = {
  samurai: printed('samurai', { military: '2', political: '1', glory: 1 }),
  courtier: printed('courtier', { military: '-', political: '3', glory: 2 }),
  katana: printed('katana', { type: 'attachment', military_bonus: '+2' }),
  stronghold: printed('stronghold', { type: 'stronghold', fate: 7, strength_bonus: '+2' }),
  garden: printed('garden', { type: 'province', strength: 4 }),
  raid: printed('raid', { type: 'province', strength: 3 }),
}

describe('attackable provinces', () => {
  it('lists the unbroken provinces, the stronghold province too, which is attacked early by an effect only', () => {
    const raid = card('raid')
    const broken = card('raid', { orientation: 'upside_down' })
    const garden = card('garden')
    const opp = player('opp', [
      stack('regular_province', [raid]),
      stack('regular_province', [broken]),
      stack('stronghold_province', [card('stronghold'), garden]),
    ])

    expect(attackableProvinces(opp)).toEqual([raid.id, garden.id])
    expect(strongholdTooEarly(opp, garden.id)).toBe(true)
    expect(strongholdTooEarly(opp, raid.id)).toBe(false)
  })

  it('allows the stronghold province once 3 other provinces are broken', () => {
    const garden = card('garden')
    const opp = player('opp', [
      ...[1, 2, 3].map(() => stack('regular_province', [card('raid', { orientation: 'upside_down' })])),
      stack('stronghold_province', [card('stronghold'), garden]),
    ])

    expect(strongholdTooEarly(opp, garden.id)).toBe(false)
  })
})

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

  it('reads the cards put on the stronghold province, but not the provinces still to choose during setup', () => {
    const stronghold = card('stronghold')
    const garden = card('garden')
    const scout = card('samurai', { side: 'dynasty' })
    const setup = player('me', [stack('stronghold_province', [stronghold, garden, card('raid', { side: 'province' })])])
    const later = player('me', [stack('stronghold_province', [stronghold, garden, scout])])

    expect(provinces(setup)[0]).toMatchObject({ province: garden, cards: [] })
    expect(provinces(later)[0]).toMatchObject({ province: garden, cards: [scout], stronghold })
  })

  it('lists what lies on a province, the top first, the stronghold last', () => {
    const samurai = card('samurai')
    const marketplace = card('raid', { side: 'dynasty' })
    const stronghold = card('stronghold')
    const scout = card('samurai', { side: 'dynasty' })
    const me = player('me', [
      stack('regular_province', [card('raid'), marketplace, samurai]),
      stack('stronghold_province', [stronghold, card('garden'), scout]),
    ])

    const [regular, strongholdProvince] = provinces(me)

    expect(provinceCards(regular!)).toEqual([samurai, marketplace])
    expect(provinceCards(strongholdProvince!)).toEqual([scout, stronghold])
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

describe('the cards set aside', () => {
  it('reads the cards under each card, the last placed first, with how they lie', () => {
    const kaezin = card('kaezin')
    const first = card('a', { orientation: 'face_down' })
    const last = card('b')
    const hidden = card(null, { orientation: 'face_down' })
    const scout = card('scout')
    const view = game(
      player('me', [stack('in_play', [kaezin]), underneath(kaezin, [first, last])]),
      player('opp', [stack('in_play', [scout]), underneath(scout, [hidden])]),
    )

    const under = cardsUnder(view)

    expect(under.get(kaezin.id)).toEqual([
      { card: last, note: 'Face up' },
      { card: first, note: 'Face down, only you know it' },
    ])
    expect(under.get(scout.id)).toEqual([{ card: hidden, note: 'Face down' }])
    expect(locate(view, first.id)?.host).toBe(kaezin)
    expect(locate(view, kaezin.id)?.host).toBeNull()
  })

  it("tells whose card a card removed from the game is, when it is not the player's of the pile", () => {
    const stolen = card('banzai', { orientation: 'face_down', controller_id: 'opp' })
    const view = game(player('me', [stack('removed_from_game', [stolen])]), player('opp'))

    expect(setAsideNote(view, stolen, 'me')).toBe("Face down, only you know it · Opp.'s card")
    expect(setAsideNote(view, card('scout', { controller_id: 'me' }), 'opp')).toBe('Your card')
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

  it('shows the skill recorded, else the one computed, and no skill for a dash', () => {
    const me = player('me', [
      stack('in_play', [card('samurai', { political_skill: 6 }), card('katana')]),
      stack('in_play', [card('courtier')]),
    ])
    const [samurai, courtier] = charactersInPlay(me)

    expect(shownSkill(samurai!, cards, 'military')).toEqual({ value: 4, recorded: false })
    expect(shownSkill(samurai!, cards, 'political')).toEqual({ value: 6, recorded: true })
    expect(shownSkill(courtier!, cards, 'military')).toEqual({ value: null, recorded: false })
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

  it('adds the Imperial Favor of the type of the conflict when its bearer has a participating character', () => {
    const bowed = card('samurai', { orientation: 'sideways' })
    const courtier = card('courtier')
    const view = game(player('me', [stack('in_play', [bowed])]), player('opp', [stack('in_play', [courtier])]), {
      imperial_favor_holder_id: 'me',
      imperial_favor_side: 'military',
    })

    expect(sideSkill(view, cards, [bowed.id], 'military')).toBe(1)
    expect(sideSkill(view, cards, [bowed.id], 'political')).toBe(0)
    expect(sideSkill(view, cards, [courtier.id], 'military')).toBe(0)
    expect(sideSkill(view, cards, [], 'military')).toBe(0)
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
        air: { fate: 0, claimed_by_id: 'me', claimed_conflict_type: 'military' },
        earth: { fate: 0, claimed_by_id: 'opp', claimed_conflict_type: 'political' },
        fire: { fate: 0, claimed_by_id: null, claimed_conflict_type: null },
        void: { fate: 0, claimed_by_id: 'me', claimed_conflict_type: 'military' },
        water: { fate: 0, claimed_by_id: null, claimed_conflict_type: null },
      },
    })

    expect(gloryCount(view, me, cards)).toBe(2 + 2)
  })
})

describe('the state of the game', () => {
  it('tells the rings unclaimed, contested and claimed', () => {
    const view = game(player('me'), player('opp'), {
      rings: {
        air: { fate: 2, claimed_by_id: null, claimed_conflict_type: null },
        earth: { fate: 0, claimed_by_id: 'opp', claimed_conflict_type: 'political' },
        fire: { fate: 0, claimed_by_id: null, claimed_conflict_type: null },
        void: { fate: 0, claimed_by_id: 'me', claimed_conflict_type: 'military' },
        water: { fate: 1, claimed_by_id: null, claimed_conflict_type: null },
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
        declared_strength_province_ids: [],
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
    expect(switchableRings(view).map((ring) => ring.element)).toEqual(['air', 'water', 'void'])
    expect(
      switchableRings({ ...view, conflict: { ...view.conflict!, attacking_player_id: 'opp' } }).map(
        (ring) => ring.element,
      ),
    ).toEqual(['air', 'earth', 'water'])
    expect(switchableRings({ ...view, conflict: null })).toEqual([])
  })

  it('tells the defender which attacked province to reveal', () => {
    const facedown = card('garden', { side: 'province', orientation: 'face_down' })
    const view = game(player('me', [stack('regular_province', [facedown])]), player('opp'), {
      place: 'reveal_attacked_province',
      available_actions: ['reveal_province'],
      conflict: {
        attacking_player_id: 'opp',
        defending_player_id: 'me',
        ring: 'fire',
        type: 'military',
        province_id: facedown.id,
        attacked_province_ids: [facedown.id],
        attacker_ids: [],
        defender_ids: [],
        evaded_ids: [],
        attacker_skill: null,
        defender_skill: null,
        province_strengths: {},
        declared_strength_province_ids: [],
        winner_id: null,
        broken_province_ids: [],
      },
    })

    expect(provinceToReveal(view)).toBe(facedown.id)
    // the attacker waits for the defender
    expect(provinceToReveal({ ...view, conflict: { ...view.conflict!, defending_player_id: 'opp' } })).toBeNull()
    // a later step: the conflict moved to a facedown province, which any player reveals
    const moved = { ...view, place: 'conflict_action_window' }
    expect(provinceToReveal({ ...moved, conflict: { ...view.conflict!, defending_player_id: 'opp' } })).toBe(
      facedown.id,
    )
    // nothing once it is face up, nor for a spectator
    const revealed = { ...facedown, orientation: 'face_up' as const }
    expect(
      provinceToReveal({ ...moved, players: [player('me', [stack('regular_province', [revealed])]), player('opp')] }),
    ).toBeNull()
    expect(provinceToReveal({ ...moved, available_actions: [] })).toBeNull()
  })

  it('has no rings before the game starts', () => {
    const view = game(player('me'), player('opp'), { place: 'join_game', rings: {} })

    expect(rings(view)).toEqual([])
    expect(gloryCount(view, view.players[0]!, {})).toBe(0)
  })

  it('tells the conflicts a player may still declare', () => {
    expect(conflictsLeft(player('me', [], { declarable_conflict_types: ['military'] }))).toEqual(['military'])
    expect(conflictsLeft(player('me', [], { declarable_conflict_types: [] }))).toEqual([])
  })

  it('tells when the result of the conflict is determined', () => {
    const conflict = {
      attacking_player_id: 'me',
      defending_player_id: 'opp',
      ring: 'fire' as const,
      type: 'military' as const,
      province_id: 'p',
      attacked_province_ids: ['p'],
      attacker_ids: [],
      defender_ids: [],
      evaded_ids: [],
      attacker_skill: 3,
      defender_skill: 1,
      province_strengths: {},
      declared_strength_province_ids: [],
      winner_id: 'me',
      broken_province_ids: [],
    }

    expect(conflictResolved(game(player('me'), player('opp'), { place: 'conflict_action_window', conflict }))).toBe(
      false,
    )
    expect(conflictResolved(game(player('me'), player('opp'), { place: 'resolve_ring_effects', conflict }))).toBe(true)
    expect(conflictResolved(game(player('me'), player('opp'), { place: 'resolve_ring_effects' }))).toBe(false)
  })

  it('tells how the game was won, from the point of view of the viewer', () => {
    const ended = (winner_id: string, victory_condition: string) =>
      victoryText(game(player('me'), player('opp'), { place: 'game_ended', winner_id, victory_condition }))

    expect(ended('me', 'honor_win')).toBe('You reached 25 honor.')
    expect(ended('opp', 'honor_loss')).toBe('You have no honor left.')
    expect(ended('me', 'stronghold_province_broken')).toBe("You broke the opponent's stronghold province.")
    expect(ended('opp', 'concession')).toBe('You conceded.')
    expect(ended('me', 'abandonment')).toBe('The opponent stopped playing: you ended the game.')
  })

  it('tells that nobody wins a game every player has left', () => {
    const ended = (winner_id: string | null) =>
      game(player('me'), player('opp'), { place: 'game_ended', winner_id, victory_condition: 'abandonment' })

    expect(resultTitle(ended('me'))).toBe('Victory')
    expect(resultTitle(ended('opp'))).toBe('Defeat')
    expect(resultTitle(ended(null))).toBe('No winner')
    expect(victoryText(ended(null))).toBe('Nobody played for an hour: the game ended without a winner.')
  })

  it('names the step of the round of a place', () => {
    expect(stepOf('declare_conflict')).toMatchObject({ phase: 'conflict', title: 'Declare a conflict' })
    expect(stepOf('fate_phase_begins')).toMatchObject({ phase: 'fate', title: 'Fate phase begins' })
  })

  it('tells a spectator what the players do in the step, without a hint', () => {
    expect(stepOf('play_cards_from_provinces', true)).toEqual({
      phase: 'dynasty',
      title: 'Play cards from provinces',
      help: 'Each player in turn pays the cost of a character to bring it into play, or passes. The first player to pass gains 1 fate.',
    })
    expect(stepOf('draw_phase_action_window', true).hint).toBeUndefined()
  })

  it('names the players to the viewer, by their names to a spectator', () => {
    const me = player('me', [], { name: 'Doji Akiko' })
    const opp = player('opp', [], { name: 'Matsu Kenji' })

    expect([nameOf(game(me, opp), me), nameOf(game(me, opp), opp), nameOf(game(me, opp), opp, false)]).toEqual([
      'You',
      'Opp.',
      'Opponent',
    ])
    expect(nameOf(game(me, opp, { spectating: true }), me)).toBe('Doji Akiko')
  })

  it('lists the codes the viewer sees', () => {
    const view = game(
      player('me', [stack('hand', [card('samurai'), card('courtier')])]),
      player('opp', [stack('hand', null), stack('regular_province', [card(null), card('samurai')])]),
    )

    expect(visibleCodes(view).sort()).toEqual(['courtier', 'samurai'])
  })
})
