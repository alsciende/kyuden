import { describe, expect, it } from 'vitest'

import { cardMenu, hostChoices } from '@/lib/cardActions'

import { card, game, player, printed, stack, underneath } from './gameFixture'

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
  academy: printed('academy', { type: 'holding' }),
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

  it('plays a character of a province with play_character outside the dynasty phase', () => {
    // Prepared Ambush, Wind's Path: during a conflict, into it
    const shown = card('scout', { side: 'dynasty' })
    const view = game(player('me', [stack('regular_province', [card('raid'), shown])]), player('opp'), {
      available_actions: [...EFFECTS, 'play_character'],
    })

    expect(cardMenu(view, cards, shown.id)!.plays).toEqual([
      { type: 'play_character', kind: 'character', label: 'Play this character', cost: 1 },
    ])
  })

  it('plays a card of a discard pile, of the opponent too', () => {
    // Kunshu, Guidance of the Ancestors, Gateway to Meido
    const banzai = card('banzai')
    const katana = card('katana')
    const scout = card('scout', { side: 'dynasty' })
    const academy = card('academy', { side: 'dynasty' })
    const view = game(
      player('me', [stack('dynasty_discard_pile', [scout, academy])]),
      player('opp', [stack('conflict_discard_pile', [banzai, katana])]),
      { available_actions: [...EFFECTS, 'play_action_event', 'play_attachment', 'play_character'] },
    )
    const withKatana = { ...cards, katana: printed('katana', { type: 'attachment', cost: 0 }) }

    expect(cardMenu(view, withKatana, banzai.id)!.plays.map((action) => action.type)).toEqual(['play_action_event'])
    expect(cardMenu(view, withKatana, katana.id)!.plays.map((action) => action.type)).toEqual(['play_attachment'])
    expect(cardMenu(view, withKatana, scout.id)!.plays.map((action) => action.type)).toEqual(['play_character'])
    // a holding is not played
    expect(cardMenu(view, withKatana, academy.id)!.plays).toEqual([])
  })

  it('does not play a card of a discard pile without the opportunity to act', () => {
    const banzai = card('banzai')
    const view = game(player('me', [stack('conflict_discard_pile', [banzai])]), player('opp'), {
      available_actions: EFFECTS,
    })

    expect(cardMenu(view, cards, banzai.id)!.plays).toEqual([])
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

  it('fills a province face down or face up, whatever lies on it already', () => {
    const raid = card('raid')
    const rallied = card('raid')
    const view = game(
      player('me', [stack('regular_province', [raid]), stack('regular_province', [rallied, card('scout')])]),
      player('opp'),
      { available_actions: EFFECTS },
    )
    const fills = [
      { type: 'fill_province', label: 'Fill this province', payload: { province_id: raid.id, face_up: false } },
      { type: 'fill_province', label: 'Fill this province faceup', payload: { province_id: raid.id, face_up: true } },
    ]

    expect(cardMenu(view, cards, raid.id)!.state).toEqual(fills)
    expect(cardMenu(view, cards, rallied.id)!.state.map((action) => action.type)).toEqual([
      'fill_province',
      'fill_province',
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

    expect(cardMenu(view, cards, scout.id)!.more).toEqual([
      { type: 'put_character_into_play', label: 'Put into play', payload: { card_id: scout.id, into_conflict: false } },
    ])
    expect(cardMenu(conflict, cards, kaezin.id)!.more.map((action) => action.label)).toEqual([
      'Put into play',
      'Put into play in the conflict',
    ])
    expect(cardMenu(view, cards, raid.id)!.more).toEqual([])
  })

  it('puts a character of the opponent into play under the control of the viewer', () => {
    const kaezin = card('kaezin')
    const raid = card('raid')
    const view = game(
      player('me', [stack('regular_province', [raid])]),
      player('opp', [stack('conflict_discard_pile', [kaezin])]),
      {
        available_actions: ['put_character_into_play'],
      },
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

    expect(cardMenu(view, cards, kaezin.id)!.more.map((action) => action.label)).toEqual([
      'Put into play',
      'Put into play under your control',
    ])
    expect(cardMenu(conflict, cards, kaezin.id)!.more.at(-1)).toEqual({
      type: 'put_character_into_play',
      label: 'Put into play in the conflict under your control',
      payload: { card_id: kaezin.id, into_conflict: true, controller_id: 'me' },
    })
  })

  it('takes control of a card in play controlled by the opponent', () => {
    const kaezin = card('kaezin')
    const ancestral = card('ancestral', { controller_id: 'me' })
    const scout = card('scout')
    const view = game(
      player('me', [stack('in_play', [scout])]),
      player('opp', [stack('in_play', [kaezin, ancestral])]),
      { available_actions: ['take_control'] },
    )

    expect(cardMenu(view, cards, kaezin.id)!.control).toEqual({
      type: 'take_control',
      label: 'Take control of it',
      payload: { card_id: kaezin.id },
    })
    // an attachment the viewer controls on a character of the opponent
    expect(cardMenu(view, cards, ancestral.id)!.mine).toBe(true)
    expect(cardMenu(view, cards, ancestral.id)!.control).toBeNull()
    expect(cardMenu(view, cards, scout.id)!.control).toBeNull()
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

  it('breaks a face-up province that is not broken', () => {
    const raid = card('raid', { side: 'province' })
    const broken = card('raid', { side: 'province', orientation: 'upside_down' })
    const view = game(
      player('me', [stack('regular_province', [raid]), stack('regular_province', [broken])]),
      player('opp'),
      { available_actions: ['break_province'] },
    )

    expect(cardMenu(view, cards, raid.id)!.state).toEqual([
      { type: 'break_province', label: 'Break it', payload: { province_id: raid.id } },
    ])
    expect(cardMenu(view, cards, broken.id)!.state).toEqual([])
  })

  it('turns a face-up province that is not broken facedown, of either player', () => {
    const raid = card('raid', { side: 'province' })
    const broken = card('raid', { side: 'province', orientation: 'upside_down' })
    const hidden = card(null, { side: 'province', orientation: 'face_down' })
    const view = game(
      player('me', [stack('regular_province', [broken]), stack('regular_province', [hidden])]),
      player('opp', [stack('regular_province', [raid])]),
      { available_actions: ['turn_province_facedown'] },
    )

    expect(cardMenu(view, cards, raid.id)!.state).toEqual([
      { type: 'turn_province_facedown', label: 'Turn it facedown', payload: { card_id: raid.id } },
    ])
    expect(cardMenu(view, cards, broken.id)!.state).toEqual([])
    expect(cardMenu(view, cards, hidden.id)!.state).toEqual([])
  })

  it('moves an attachment in play', () => {
    const kaezin = card('kaezin')
    const katana = card('katana')
    const view = game(player('me', [stack('in_play', [kaezin, katana])]), player('opp'), {
      available_actions: ['move_attachment'],
    })
    const withKatana = { ...cards, katana: printed('katana', { type: 'attachment' }) }

    expect(cardMenu(view, withKatana, katana.id)!.moveAttachment).toBe(true)
    expect(cardMenu(view, withKatana, kaezin.id)!.moveAttachment).toBe(false)
  })

  it('puts an attachment out of play into play, from a hand, a discard pile or set aside', () => {
    const kaezin = card('kaezin')
    const inHand = card('katana')
    const discarded = card('katana')
    const removed = card('katana')
    const attached = card('katana')
    const view = game(
      player('me', [
        stack('in_play', [kaezin, attached]),
        stack('hand', [inHand]),
        stack('conflict_discard_pile', [discarded]),
        stack('removed_from_game', [removed]),
      ]),
      player('opp'),
      { available_actions: ['put_attachment_into_play'] },
    )
    const withKatana = { ...cards, katana: printed('katana', { type: 'attachment' }) }

    for (const attachment of [inHand, discarded, removed]) {
      expect(cardMenu(view, withKatana, attachment.id)!.attachIntoPlay).toBe(true)
    }
    // already in play: moved, not put into play
    expect(cardMenu(view, withKatana, attached.id)!.attachIntoPlay).toBe(false)
    expect(cardMenu(view, withKatana, kaezin.id)!.attachIntoPlay).toBe(false)
  })

  it('discards or moves the status token of a character, apart with the special actions', () => {
    const honored = card('kaezin', { personal_honor: 'honored' })
    const ordinary = card('scout')
    const view = game(player('me', [stack('in_play', [honored]), stack('in_play', [ordinary])]), player('opp'), {
      available_actions: ['discard_status_token', 'move_status_token'],
      special_actions: [
        { type: 'discard_status_token', card_ids: [] },
        { type: 'move_status_token', card_ids: [] },
      ],
    })

    const menu = cardMenu(view, cards, honored.id)!
    expect(menu.state).toEqual([])
    expect(menu.more).toEqual([
      {
        type: 'discard_status_token',
        label: 'Discard its honored status token',
        payload: { card_id: honored.id },
      },
    ])
    expect(menu.moveStatusToken).toBe(true)
    expect(cardMenu(view, cards, ordinary.id)!.more).toEqual([])
    expect(cardMenu(view, cards, ordinary.id)!.moveStatusToken).toBe(false)
  })

  it('places and discards the status tokens of a province or a character, apart with the special actions', () => {
    const dishonored = card('raid', { side: 'province', personal_honor: 'dishonored' })
    const hidden = card(null, { side: 'province', orientation: 'face_down' })
    const kaezin = card('kaezin', { personal_honor: 'honored' })
    const view = game(
      player('me', [stack('regular_province', [dishonored]), stack('in_play', [kaezin])]),
      player('opp', [stack('regular_province', [hidden])]),
      {
        available_actions: ['place_status_token', 'discard_status_token'],
        special_actions: [
          { type: 'place_status_token', card_ids: [] },
          { type: 'discard_status_token', card_ids: [] },
        ],
      },
    )

    expect(cardMenu(view, cards, dishonored.id)!.more).toEqual([
      {
        type: 'place_status_token',
        label: 'Place an honored status token',
        payload: { card_id: dishonored.id, token: 'honored' },
      },
      {
        type: 'discard_status_token',
        label: 'Discard its dishonored status token',
        payload: { card_id: dishonored.id },
      },
    ])
    expect(cardMenu(view, cards, hidden.id)!.more.map((action) => action.payload)).toEqual([
      { card_id: hidden.id, token: 'honored' },
      { card_id: hidden.id, token: 'dishonored' },
    ])
    expect(cardMenu(view, cards, kaezin.id)!.more.map((action) => action.label)).toEqual([
      'Place a dishonored status token',
      'Discard its honored status token',
    ])
  })

  it('places and removes the honor tokens of a province or a holding, apart with the special actions', () => {
    const forum = card('raid', { side: 'province', honor_tokens: 1 })
    const academy = card('academy', { side: 'dynasty' })
    const hidden = card(null, { side: 'province', orientation: 'face_down' })
    const kaezin = card('kaezin')
    const view = game(
      player('me', [stack('regular_province', [forum, academy]), stack('in_play', [kaezin])]),
      player('opp', [stack('stronghold_province', [card('stronghold', { side: 'stronghold' }), hidden])]),
      {
        available_actions: ['place_honor_tokens', 'remove_honor_tokens'],
        special_actions: [
          { type: 'place_honor_tokens', card_ids: [] },
          { type: 'remove_honor_tokens', card_ids: [] },
        ],
      },
    )

    expect(cardMenu(view, cards, forum.id)!.more).toEqual([
      { type: 'place_honor_tokens', label: 'Place 1 honor token', payload: { card_id: forum.id, amount: 1 } },
      { type: 'remove_honor_tokens', label: 'Remove 1 honor token', payload: { card_id: forum.id, amount: 1 } },
    ])
    expect(cardMenu(view, cards, academy.id)!.more.map((action) => action.type)).toEqual(['place_honor_tokens'])
    expect(cardMenu(view, cards, hidden.id)!.more.map((action) => action.type)).toEqual(['place_honor_tokens'])
    expect(cardMenu(view, cards, kaezin.id)!.more).toEqual([])
  })

  it('switches a dynasty card with the dynasty cards of the other provinces of its owner', () => {
    const scout = card('scout', { side: 'dynasty' })
    const hidden = card(null, { side: 'dynasty', orientation: 'face_down' })
    const kaezin = card('kaezin', { side: 'dynasty' })
    const view = game(
      player('me', [
        stack('regular_province', [card('raid', { side: 'province' }), scout]),
        stack('regular_province', [card('raid', { side: 'province' }), hidden]),
        stack('regular_province', [card(null, { side: 'province', orientation: 'face_down' }), kaezin]),
      ]),
      player('opp'),
      { available_actions: ['switch_province_cards'] },
    )

    expect(cardMenu(view, cards, scout.id)!.switchWith).toEqual([
      { id: hidden.id, name: 'Facedown card (raid)' },
      { id: kaezin.id, name: 'kaezin (province 3)' },
    ])
  })

  it('puts a dynasty card into another province of its owner, the stronghold province included', () => {
    const scout = card('scout', { side: 'dynasty' })
    const keeper = card('kaezin', { side: 'dynasty' })
    const toturi = card('toturi', { side: 'dynasty' })
    const katana = card('ancestral', { side: 'conflict' })
    const raid = card('raid', { side: 'province' })
    const hidden = card(null, { side: 'province', orientation: 'face_down' })
    const shameful = card('raid', { side: 'province' })
    const view = game(
      player('me', [
        stack('regular_province', [raid, scout]),
        stack('regular_province', [hidden]),
        stack('stronghold_province', [card('stronghold', { side: 'stronghold' }), shameful]),
        stack('dynasty_discard_pile', [keeper]),
        stack('in_play', [toturi, katana]),
      ]),
      player('opp'),
      { available_actions: ['put_card_into_province'] },
    )

    expect(cardMenu(view, cards, scout.id)!.putIntoProvince).toEqual([
      { id: hidden.id, name: 'Province 2' },
      { id: shameful.id, name: 'raid (stronghold)' },
    ])
    expect(cardMenu(view, cards, keeper.id)!.putIntoProvince.map((choice) => choice.id)).toEqual([
      raid.id,
      hidden.id,
      shameful.id,
    ])
    expect(cardMenu(view, cards, toturi.id)!.putIntoProvince).toHaveLength(3)
    expect(cardMenu(view, cards, katana.id)!.putIntoProvince).toEqual([])
    expect(cardMenu(view, cards, raid.id)!.putIntoProvince).toEqual([])
  })

  it('offers a dynasty card moved onto the stronghold province as a dynasty card, not as the province', () => {
    const scout = card('scout', { side: 'dynasty' })
    const raid = card('raid', { side: 'province' })
    const view = game(
      player('me', [
        stack('regular_province', [raid]),
        stack('stronghold_province', [card('stronghold', { side: 'stronghold' }), card('raid'), scout]),
      ]),
      player('opp'),
      { available_actions: ['put_card_into_province', 'discard_card', 'break_province'] },
    )

    const menu = cardMenu(view, cards, scout.id)!

    expect(menu.putIntoProvince).toEqual([{ id: raid.id, name: 'raid' }])
    expect(menu.removals.map((action) => action.type)).toEqual(['discard_card'])
    expect(menu.state).toEqual([])
  })

  it('does not point at a card when the action is not available', () => {
    const kaezin = card('kaezin')
    const view = game(player('me', [stack('in_play', [kaezin])]), player('opp'), { available_actions: EFFECTS })

    expect(cardMenu(view, cards, kaezin.id)!.point).toBeNull()
  })

  it('removes a card from the game, face up or face down, apart with the special actions', () => {
    const banzai = card('banzai')
    const view = game(player('me', [stack('hand', [banzai])]), player('opp'), {
      available_actions: ['remove_from_game'],
      special_actions: [{ type: 'remove_from_game', card_ids: [] }],
    })

    expect(cardMenu(view, cards, banzai.id)!.more).toEqual([
      { type: 'remove_from_game', label: 'Remove it from the game', payload: { card_id: banzai.id, face_down: false } },
      {
        type: 'remove_from_game',
        label: 'Remove it from the game face down',
        payload: { card_id: banzai.id, face_down: true },
      },
    ])
  })

  it('places a card under the cards of both players, those of the viewer first, but not under itself', () => {
    const kaezin = card('kaezin')
    const ancestral = card('ancestral')
    const academy = card('academy', { side: 'dynasty' })
    const raid = card('raid', { side: 'province' })
    const stronghold = card('stronghold', { side: 'stronghold' })
    const scout = card('scout')
    const view = game(
      player('me', [
        stack('in_play', [kaezin, ancestral]),
        stack('regular_province', [raid, academy]),
        stack('stronghold_province', [stronghold, card('raid', { id: 'sp' })]),
      ]),
      player('opp', [stack('in_play', [scout])]),
      { available_actions: ['place_card_under'] },
    )

    expect(hostChoices(view, cards, kaezin.id).map((host) => [host.name, host.where, host.group])).toEqual([
      ['ancestral', 'attached to kaezin', 'yours'],
      ['academy', 'in raid', 'yours'],
      ['Stronghold', 'stronghold', 'yours'],
      ['raid', 'province', 'provinces'],
      ['raid', 'stronghold province', 'provinces'],
      ['scout', 'in play', 'opponent'],
    ])
    expect(cardMenu(view, cards, kaezin.id)!.placeUnder.map((host) => host.id)).not.toContain(kaezin.id)
  })

  it('does not place a card under the card it lies under, nor without the action', () => {
    const kaezin = card('kaezin')
    const banzai = card('banzai', { orientation: 'face_down' })
    const view = game(player('me', [stack('in_play', [kaezin]), underneath(kaezin, [banzai])]), player('opp'), {
      available_actions: ['place_card_under'],
    })

    expect(cardMenu(view, cards, banzai.id)!.placeUnder).toEqual([])
    expect(cardMenu({ ...view, available_actions: [] }, cards, kaezin.id)!.placeUnder).toEqual([])
  })

  it('plays a card under a card, puts it into a hand, a deck or the discard pile, or removes it from the game', () => {
    const kaezin = card('kaezin')
    const banzai = card('banzai', { orientation: 'face_down' })
    const view = game(player('me', [stack('in_play', [kaezin]), underneath(kaezin, [banzai])]), player('opp'), {
      available_actions: [
        'play_action_event',
        'add_card_to_hand',
        'put_card_on_deck',
        'discard_card',
        'remove_from_game',
        'place_card_under',
      ],
      special_actions: [
        { type: 'remove_from_game', card_ids: [] },
        { type: 'place_card_under', card_ids: [] },
      ],
    })

    const menu = cardMenu(view, cards, banzai.id)!

    expect(menu.located.host).toBe(kaezin)
    expect(menu.plays.map((action) => action.type)).toEqual(['play_action_event'])
    expect(menu.removals.map((action) => action.label)).toEqual([
      'Add it to your hand',
      'Put on top of its deck',
      'Put at the bottom of its deck',
      'Discard',
    ])
    expect(menu.more.map((action) => action.type)).toEqual(['remove_from_game', 'remove_from_game'])
  })

  it('puts a character removed from the game into play, and a holding into a province, but does not remove them again', () => {
    const scout = card('scout', { side: 'dynasty' })
    const academy = card('academy', { side: 'dynasty' })
    const raid = card('raid', { side: 'province' })
    const view = game(
      player('me', [stack('regular_province', [raid]), stack('removed_from_game', [scout, academy])]),
      player('opp'),
      {
        available_actions: [
          'play_character',
          'put_character_into_play',
          'put_holding_into_province',
          'remove_from_game',
        ],
        special_actions: [
          { type: 'put_character_into_play', card_ids: [] },
          { type: 'remove_from_game', card_ids: [] },
        ],
      },
    )

    const character = cardMenu(view, cards, scout.id)!
    expect(character.plays.map((action) => action.type)).toEqual(['play_character'])
    expect(character.more.map((action) => action.type)).toEqual(['put_character_into_play'])
    expect(cardMenu(view, cards, academy.id)!.intoProvince).toEqual([{ id: raid.id, name: 'raid' }])
  })
})
