import { describe, expect, it } from 'vitest'

import { cardMenu } from '@/lib/cardActions'

import { card, game, player, printed, stack } from './gameFixture'

const EFFECTS = [
  'bow_card',
  'ready_card',
  'honor_character',
  'dishonor_character',
  'remove_fate',
  'move_home',
  'move_to_conflict',
  'set_character_skill',
  'discard_card',
  'reveal_dynasty_card',
  'fill_province',
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
      'remove_fate',
    ])
    expect(menu.skill).toBe(true)
    expect(menu.discard?.payload).toEqual({ card_id: kaezin.id })
  })

  it('offers the manipulations on the cards of the opponent too, but not their state they already have', () => {
    const kaezin = card('kaezin', { orientation: 'sideways', personal_honor: 'honored' })
    const view = game(player('me'), player('opp', [stack('in_play', [kaezin])]), { available_actions: EFFECTS })

    const menu = cardMenu(view, cards, kaezin.id)!

    expect(menu.mine).toBe(false)
    expect(menu.state.map((action) => action.type)).toEqual(['ready_card', 'dishonor_character'])
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

  it('fills an empty province', () => {
    const raid = card('raid')
    const view = game(player('me', [stack('regular_province', [raid])]), player('opp'), { available_actions: EFFECTS })

    expect(cardMenu(view, cards, raid.id)!.state).toEqual([
      { type: 'fill_province', label: 'Fill this province', payload: { province_id: raid.id } },
    ])
  })

  it('puts a province under the stronghold during setup', () => {
    const raid = card('raid', { orientation: 'face_down' })
    const view = game(player('me', [stack('stronghold_province', [card('stronghold'), raid])]), player('opp'), {
      place: 'choose_stronghold',
      available_actions: ['choose_stronghold_province'],
    })

    expect(cardMenu(view, cards, raid.id)!.state.map((action) => action.type)).toEqual(['choose_stronghold_province'])
  })
})
