import { describe, expect, it } from 'vitest'

import { playerBar, playerEffects } from '@/lib/playerBar'

import { card, game, player, stack } from './gameFixture'

describe('playerBar', () => {
  it('counts the cards of each stack and reads the discard piles from the top', () => {
    const first = card('first')
    const last = card('last')
    const me = player('me', [
      stack('hand', [card('a'), card('b')]),
      { ...stack('conflict_draw_deck', null), count: 28 },
      { ...stack('dynasty_draw_deck', null), count: 31 },
      stack('conflict_discard_pile', [first, last]),
      stack('dynasty_discard_pile', []),
    ])
    const bar = playerBar(game(me, player('opp')), me)

    expect(bar.hand).toBe(2)
    expect(bar.conflictDeck).toBe(28)
    expect(bar.dynastyDeck).toBe(31)
    expect(bar.conflictDiscard).toEqual([last, first])
    expect(bar.dynastyDiscard).toEqual([])
  })

  it('counts the intact provinces', () => {
    const me = player('me', [
      stack('regular_province', [card('raid')]),
      stack('regular_province', [card('garden', { orientation: 'upside_down' })]),
      stack('stronghold_province', [card('stronghold'), card('lands')]),
    ])
    const bar = playerBar(game(me, player('opp')), me)

    expect(bar.intactProvinces).toBe(2)
    expect(bar.provinces).toBe(3)
  })

  it('tells how many provinces must be broken before the stronghold can be attacked', () => {
    const broken = () => stack('regular_province', [card('garden', { orientation: 'upside_down' })])
    const intact = () => stack('regular_province', [card('raid')])
    const me = player('me', [broken(), intact(), intact(), intact(), stack('stronghold_province', [card('s')])])
    const opp = player('opp', [broken(), broken(), intact(), intact()])
    const view = game(me, opp)

    expect(playerBar(view, me).strongholdTip).toBe('Opp. must break 2 more provinces before attacking your stronghold.')
    expect(playerBar(view, opp).strongholdTip).toBe(
      "You must break 1 more province before attacking Opp.'s stronghold.",
    )
  })

  it('tells when the stronghold can be attacked', () => {
    const broken = () => stack('regular_province', [card('garden', { orientation: 'upside_down' })])
    const me = player('me', [broken(), broken(), broken()])
    const opp = player('opp', [broken(), broken(), broken(), broken()])
    const view = game(me, opp)

    expect(playerBar(view, me).strongholdTip).toBe('Opp. can attack your stronghold.')
    expect(playerBar(view, opp).strongholdTip).toBe("You can attack Opp.'s stronghold.")
  })

  it('reads the conflicts left, the claimed rings and the Imperial Favor', () => {
    const me = player('me', [], { declared_conflict_types: ['military'] })
    const opp = player('opp')
    const ring = { fate: 0, claimed_by_id: null, claimed_conflict_type: null }
    const view = game(me, opp, {
      rings: {
        air: ring,
        earth: ring,
        fire: { fate: 0, claimed_by_id: 'me', claimed_conflict_type: 'military' },
        void: ring,
        water: ring,
      },
      imperial_favor_holder_id: 'opp',
      imperial_favor_side: 'political',
    })

    expect(playerBar(view, me)).toMatchObject({ conflictsLeft: ['political'], rings: ['fire'], favor: false })
    expect(playerBar(view, opp)).toMatchObject({ conflictsLeft: ['military', 'political'], rings: [], favor: true })
  })

  it('notes the first player and the bids during the draw phase only', () => {
    const me = player('me', [], { honor_bid: 3 })
    const view = game(me, player('opp'), { first_player_id: 'me' })

    expect(playerBar(view, me)).toMatchObject({ firstPlayer: true, notes: [] })
    expect(playerBar({ ...view, place: 'draw_phase_action_window' }, me).notes).toEqual(['Bid 3'])
  })
})

describe('playerEffects', () => {
  const me = player('me')
  const opp = player('opp')
  const everything = game(me, opp, {
    available_actions: [
      'gain_honor',
      'lose_honor',
      'transfer_honor',
      'gain_fate',
      'lose_fate',
      'move_fate',
      'draw_conflict_cards',
      'discard_random_card_from_hand',
      'shuffle_conflict_deck',
      'shuffle_dynasty_deck',
    ],
  })

  it('applies the amount to the player of the bar', () => {
    expect(playerEffects(everything, opp, 'fate', 2)).toEqual([
      { type: 'gain_fate', label: 'Gain 2 fate', payload: { target_player_id: 'opp', fate_gain: 2 } },
      { type: 'lose_fate', label: 'Lose 2 fate', payload: { target_player_id: 'opp', fate_loss: 2 } },
      {
        type: 'move_fate',
        label: 'Give 2 to you',
        payload: { source: 'player', source_id: 'opp', target: 'player', target_id: 'me', amount: 2 },
      },
    ])
  })

  it('looks at and reveals the top cards of the decks, and the hand of the opponent', () => {
    const inspecting = game(me, opp, {
      available_actions: ['look_at_cards', 'reveal_cards', 'shuffle_dynasty_deck'],
    })

    expect(playerEffects(inspecting, me, 'dynasty_deck', 3)).toEqual([
      { type: 'shuffle_dynasty_deck', label: 'Shuffle', payload: { target_player_id: 'me' } },
      {
        type: 'look_at_cards',
        label: 'Look at the top 3',
        payload: { target_player_id: 'me', stack: 'dynasty_deck', quantity: 3 },
      },
      {
        type: 'reveal_cards',
        label: 'Reveal the top 3',
        payload: { target_player_id: 'me', stack: 'dynasty_deck', quantity: 3 },
      },
    ])
    expect(playerEffects(inspecting, me, 'hand', 1).map((effect) => effect.label)).toEqual(['Reveal the hand'])
    expect(playerEffects(inspecting, opp, 'hand', 1).map((effect) => effect.label)).toEqual([
      'Look at the hand',
      'Reveal the hand',
    ])
  })

  it('gives honor to the other player', () => {
    expect(playerEffects(everything, me, 'honor', 1).map((effect) => effect.label)).toEqual([
      'Gain 1 honor',
      'Lose 1 honor',
      'Give 1 to Opp.',
    ])
    expect(playerEffects(everything, opp, 'honor', 1)[2]).toEqual({
      type: 'transfer_honor',
      label: 'Give 1 to you',
      payload: { giver_id: 'opp', recipient_id: 'me', amount: 1 },
    })
  })

  it('draws, discards and shuffles the conflict cards', () => {
    expect(playerEffects(everything, me, 'hand', 1).map((effect) => effect.label)).toEqual([
      'Draw 1 card',
      'Discard 1 at random',
    ])
    expect(playerEffects(everything, me, 'conflict_deck', 3).map((effect) => effect.label)).toEqual([
      'Draw 3 cards',
      'Shuffle',
    ])
  })

  it('offers no effect on a discard pile, nor an effect the engine does not make available', () => {
    expect(playerEffects(everything, me, 'conflict_discard', 1)).toEqual([])
    expect(playerEffects(game(me, opp, { available_actions: ['gain_fate'] }), me, 'fate', 1)).toHaveLength(1)
  })
})

describe('playerEffects of the first player token', () => {
  it('gives the token to the other player', () => {
    const me = player('me')
    const opp = player('opp')
    const view = game(me, opp, { available_actions: ['take_first_player_token'], first_player_id: 'me' })

    expect(playerEffects(view, me, 'first_player', 1)).toEqual([
      { type: 'take_first_player_token', label: 'Give it to Opp.', payload: { target_player_id: 'opp' } },
    ])
    expect(playerEffects(view, opp, 'first_player', 1)).toEqual([
      { type: 'take_first_player_token', label: 'Take it', payload: { target_player_id: 'me' } },
    ])
  })
})
