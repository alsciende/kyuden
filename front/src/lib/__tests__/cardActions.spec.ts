import { describe, expect, it } from 'vitest'

import { cardMenu } from '@/lib/cardActions'

import { card, game, player, printed, stack } from './gameFixture'

const EFFECTS = [
  'bow_card',
  'ready_card',
  'honor_character',
  'dishonor_character',
  'place_fate',
  'remove_fate',
  'move_fate',
  'move_home',
  'move_to_conflict',
  'set_character_skill',
  'discard_card',
  'sacrifice_card',
  'return_card_to_hand',
  'reveal_dynasty_card',
  'fill_province',
  'discard_duplicate',
]

const cards = {
  kaezin: printed('kaezin', { cost: 3, ability_timings: ['action'] }),
  ancestral: printed('ancestral', {
    type: 'attachment',
    ability_timings: ['reaction'],
    ability_granted_to_attached: true,
  }),
  banzai: printed('banzai', { type: 'event', cost: 0, ability_timings: ['action'] }),
  scout: printed('scout', { cost: 1 }),
  toturi: printed('toturi', { name: 'Akodo Toturi', uniqueness: true }),
  'toturi-2': printed('toturi-2', { name: 'Akodo Toturi', uniqueness: true }),
  raid: printed('raid', { type: 'province', strength: 3 }),
}

describe('cardMenu', () => {
  it('offers the abilities of a character and those its attachments grant it', () => {
    const kaezin = card('kaezin', { fate: 1 })
    const view = game(player('me', [stack('in_play', [kaezin, card('ancestral')])]), player('opp'), {
      available_actions: [...EFFECTS, 'trigger_action_ability', 'trigger_reaction_ability'],
    })

    const menu = cardMenu(view, cards, kaezin.id)!

    expect(menu.abilities.map((action) => [action.type, action.label])).toEqual([
      ['trigger_action_ability', 'Activate its action'],
      ['trigger_reaction_ability', 'Activate its reaction'],
    ])
    expect(menu.state.map((action) => action.type)).toEqual([
      'bow_card',
      'honor_character',
      'dishonor_character',
      'place_fate',
      'remove_fate',
      'move_fate',
    ])
    expect(menu.state.at(-1)?.payload).toEqual({
      source: 'card',
      source_id: kaezin.id,
      target: 'player',
      target_id: 'me',
      amount: 1,
    })
    expect(menu.skill).toBe(true)
    expect(menu.removals.map((action) => [action.type, action.payload])).toEqual([
      ['return_card_to_hand', { card_id: kaezin.id }],
      ['sacrifice_card', { card_id: kaezin.id }],
      ['discard_card', { card_id: kaezin.id }],
    ])
  })

  it('offers the manipulations on the cards of the opponent too, but not their state they already have', () => {
    const kaezin = card('kaezin', { orientation: 'sideways', personal_honor: 'honored' })
    const view = game(player('me'), player('opp', [stack('in_play', [kaezin])]), { available_actions: EFFECTS })

    const menu = cardMenu(view, cards, kaezin.id)!

    expect(menu.mine).toBe(false)
    expect(menu.state.map((action) => action.type)).toEqual(['ready_card', 'dishonor_character', 'place_fate'])
  })

  it('sends a participating character home and moves the others to the conflict', () => {
    const attacker = card('kaezin')
    const home = card('scout')
    const view = game(player('me', [stack('in_play', [attacker]), stack('in_play', [home])]), player('opp'), {
      available_actions: [...EFFECTS, 'initiate_duel'],
      conflict: {
        attacking_player_id: 'me',
        defending_player_id: 'opp',
        ring: 'air',
        type: 'military',
        province_id: 'p',
        attacked_province_ids: ['p'],
        attacker_ids: [attacker.id],
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

    expect(cardMenu(view, cards, attacker.id)!.state.map((action) => action.type)).toContain('move_home')
    expect(cardMenu(view, cards, attacker.id)!.duel).toBe(true)
    expect(cardMenu(view, cards, home.id)!.state.map((action) => action.type)).toContain('move_to_conflict')
  })

  it('plays an event from hand with the message of its timing', () => {
    const banzai = card('banzai')
    const view = game(player('me', [stack('hand', [banzai])]), player('opp'), {
      available_actions: [...EFFECTS, 'play_action_event'],
    })

    const menu = cardMenu(view, cards, banzai.id)!

    expect(menu.plays).toEqual([{ type: 'play_action_event', kind: 'event', label: 'Play this event', cost: 0 }])
  })

  it('reveals a face-down dynasty card and plays a face-up character from a province', () => {
    const hidden = card('scout', { orientation: 'face_down' })
    const shown = card('scout')
    const view = game(
      player('me', [
        stack('regular_province', [card('raid'), hidden]),
        stack('regular_province', [card('raid'), shown]),
      ]),
      player('opp'),
      { available_actions: [...EFFECTS, 'play_character_from_province'] },
    )

    expect(cardMenu(view, cards, hidden.id)!.state.map((action) => action.type)).toEqual(['reveal_dynasty_card'])
    expect(cardMenu(view, cards, shown.id)!.plays).toEqual([
      { type: 'play_character_from_province', kind: 'from_province', label: 'Play this character', cost: 1 },
    ])
  })

  it('discards a duplicate of a unique character in play, from hand or a province, to place fate on it', () => {
    const inPlay = card('toturi')
    const inHand = card('toturi-2')
    const inProvince = card('toturi')
    const scout = card('scout')
    const view = game(
      player('me', [
        stack('in_play', [inPlay]),
        stack('in_play', [card('scout')]),
        stack('hand', [inHand, scout]),
        stack('regular_province', [card('raid'), inProvince]),
      ]),
      player('opp'),
      { available_actions: EFFECTS },
    )
    const duplicates = (cardId: string) =>
      cardMenu(view, cards, cardId)!
        .removals.filter((action) => action.type === 'discard_duplicate')
        .map((action) => action.payload)

    expect(duplicates(inHand.id)).toEqual([{ card_id: inHand.id, copy_id: inPlay.id }])
    expect(duplicates(inProvince.id)).toEqual([{ card_id: inProvince.id, copy_id: inPlay.id }])
    // not unique
    expect(duplicates(scout.id)).toEqual([])
  })

  it('sacrifices a holding in a province, but not a character', () => {
    const academy = card('academy')
    const shown = card('scout')
    const view = game(
      player('me', [
        stack('regular_province', [card('raid'), academy]),
        stack('regular_province', [card('raid'), shown]),
      ]),
      player('opp'),
      { available_actions: EFFECTS },
    )
    const holdings = { ...cards, academy: printed('academy', { type: 'holding' }) }

    expect(cardMenu(view, holdings, academy.id)!.removals.map((action) => action.type)).toEqual([
      'sacrifice_card',
      'discard_card',
    ])
    expect(cardMenu(view, holdings, shown.id)!.removals.map((action) => action.type)).toEqual(['discard_card'])
  })

  it('fills an empty province', () => {
    const raid = card('raid')
    const view = game(player('me', [stack('regular_province', [raid])]), player('opp'), { available_actions: EFFECTS })

    expect(cardMenu(view, cards, raid.id)!.state).toEqual([
      { type: 'fill_province', label: 'Fill this province', payload: { province_id: raid.id } },
    ])
  })

  it('reveals a face-down province, of either player, the stronghold province too', () => {
    const mine = card('raid', { orientation: 'face_down' })
    const theirs = card(null, { side: 'province', orientation: 'face_down' })
    const shown = card('raid')
    const view = game(
      player('me', [stack('stronghold_province', [card('stronghold'), mine]), stack('regular_province', [shown])]),
      player('opp', [stack('regular_province', [theirs])]),
      { available_actions: ['reveal_province'] },
    )

    expect(cardMenu(view, cards, mine.id)!.state.map((action) => action.type)).toEqual(['reveal_province'])
    expect(cardMenu(view, cards, theirs.id)!.state).toEqual([
      { type: 'reveal_province', label: 'Reveal it', payload: { card_id: theirs.id } },
    ])
    expect(cardMenu(view, cards, shown.id)!.state).toEqual([])
  })

  it('puts a province under the stronghold during setup', () => {
    const raid = card('raid', { orientation: 'face_down' })
    const view = game(player('me', [stack('stronghold_province', [card('stronghold'), raid])]), player('opp'), {
      place: 'choose_stronghold',
      available_actions: ['choose_stronghold_province'],
    })

    expect(cardMenu(view, cards, raid.id)!.state.map((action) => action.type)).toEqual(['choose_stronghold_province'])
  })

  it('points at a card every player sees, face down too, but not at a card in hand', () => {
    const kaezin = card('kaezin')
    const facedown = card(null, { orientation: 'face_down' })
    const banzai = card('banzai')
    const view = game(
      player('me', [stack('in_play', [kaezin]), stack('hand', [banzai])]),
      player('opp', [stack('regular_province', [card(null, { orientation: 'face_down' }), facedown])]),
      { available_actions: [...EFFECTS, 'point_at_card'] },
    )

    expect(cardMenu(view, cards, kaezin.id)!.point).toEqual({
      type: 'point_at_card',
      label: 'Point at it',
      payload: { card_id: kaezin.id },
    })
    expect(cardMenu(view, cards, facedown.id)!.point?.payload).toEqual({ card_id: facedown.id })
    expect(cardMenu(view, cards, banzai.id)!.point).toBeNull()
  })

  it('looks at a card the viewer does not know, and reveals a card of their hand', () => {
    const theirs = card(null, { side: 'province', orientation: 'face_down' })
    const known = card('raid', { orientation: 'face_down' })
    const banzai = card('banzai')
    const view = game(
      player('me', [stack('regular_province', [known]), stack('hand', [banzai])]),
      player('opp', [stack('regular_province', [theirs])]),
      { available_actions: ['look_at_card', 'reveal_card'] },
    )

    expect(cardMenu(view, cards, theirs.id)!.state).toEqual([
      { type: 'look_at_card', label: 'Look at it', payload: { card_id: theirs.id } },
    ])
    expect(cardMenu(view, cards, known.id)!.state).toEqual([])
    expect(cardMenu(view, cards, banzai.id)!.state).toEqual([
      { type: 'reveal_card', label: 'Reveal it', payload: { card_id: banzai.id } },
    ])
  })

  it('puts a card into its deck, and takes a card of the conflict discard pile back into hand', () => {
    const kaezin = card('kaezin')
    const banzai = card('banzai')
    const view = game(
      player('me', [stack('in_play', [kaezin]), stack('conflict_discard_pile', [banzai])]),
      player('opp'),
      {
        available_actions: ['put_card_on_deck', 'shuffle_into_deck', 'add_card_to_hand'],
      },
    )

    expect(cardMenu(view, cards, kaezin.id)!.removals.map((action) => [action.label, action.payload])).toEqual([
      ['Put on top of its deck', { card_id: kaezin.id, position: 'top' }],
      ['Put at the bottom of its deck', { card_id: kaezin.id, position: 'bottom' }],
      ['Shuffle into its deck', { card_id: kaezin.id }],
    ])
    expect(cardMenu(view, cards, banzai.id)!.removals.map((action) => action.label)).toEqual([
      'Take it back into your hand',
      'Put on top of its deck',
      'Put at the bottom of its deck',
      'Shuffle into its deck',
    ])
  })

  it('puts a character into play, at home or into the conflict of its owner', () => {
    const scout = card('scout')
    const kaezin = card('kaezin')
    const raid = card('raid')
    const view = game(
      player('me', [stack('regular_province', [raid, scout]), stack('dynasty_discard_pile', [kaezin])]),
      player('opp'),
      { available_actions: ['put_character_into_play'] },
    )
    const conflict = {
      ...view,
      conflict: {
        attacking_player_id: 'opp',
        defending_player_id: 'me',
        ring: 'air' as const,
        type: 'military' as const,
        province_id: raid.id,
        attacked_province_ids: [raid.id],
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
    }

    expect(cardMenu(view, cards, scout.id)!.intoPlay).toEqual([
      { type: 'put_character_into_play', label: 'Put into play', payload: { card_id: scout.id, into_conflict: false } },
    ])
    expect(cardMenu(conflict, cards, kaezin.id)!.intoPlay.map((action) => action.label)).toEqual([
      'Put into play',
      'Put into play in the conflict',
    ])
    expect(cardMenu(view, cards, raid.id)!.intoPlay).toEqual([])
  })

  it('puts a holding of the dynasty discard pile into a province of its owner', () => {
    const academy = card('academy')
    const raid = card('raid')
    const hidden = card(null, { orientation: 'face_down', side: 'province' })
    const view = game(
      player('me', [
        stack('regular_province', [raid]),
        stack('regular_province', [hidden]),
        stack('dynasty_discard_pile', [academy]),
      ]),
      player('opp'),
      { available_actions: ['put_holding_into_province'] },
    )

    expect(
      cardMenu(view, { ...cards, academy: printed('academy', { type: 'holding' }) }, academy.id)!.intoProvince,
    ).toEqual([
      { id: raid.id, name: 'raid' },
      { id: hidden.id, name: 'Province 2' },
    ])
  })

  it('does not point at a card when the action is not available', () => {
    const kaezin = card('kaezin')
    const view = game(player('me', [stack('in_play', [kaezin])]), player('opp'), { available_actions: EFFECTS })

    expect(cardMenu(view, cards, kaezin.id)!.point).toBeNull()
  })
})
