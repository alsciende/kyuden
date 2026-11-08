import { describe, expect, it } from 'vitest'

import type { ConflictView, GameView } from '@/api/game'
import { ringEffects, ringsToSwitchWith } from '@/lib/rings'

import { game, player } from './gameFixture'

const conflict: ConflictView = {
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
}

function view(changes: Partial<GameView> = {}): GameView {
  return game(player('me'), player('opp'), {
    rings: {
      air: { fate: 2, claimed_by_id: null, claimed_conflict_type: null },
      earth: { fate: 0, claimed_by_id: 'opp', claimed_conflict_type: 'political' },
      fire: { fate: 0, claimed_by_id: null, claimed_conflict_type: null },
      void: { fate: 0, claimed_by_id: 'me', claimed_conflict_type: 'military' },
      water: { fate: 1, claimed_by_id: null, claimed_conflict_type: null },
    },
    available_actions: ['switch_ring', 'claim_ring', 'move_fate'],
    ...changes,
  })
}

describe('ringsToSwitchWith', () => {
  it('switches a ring with the rings of the other places', () => {
    expect(ringsToSwitchWith(view(), 'air').map((ring) => ring.element)).toEqual(['earth', 'void'])
    expect(ringsToSwitchWith(view(), 'void').map((ring) => ring.element)).toEqual(['air', 'earth', 'fire', 'water'])
  })

  it('switches the contested ring with the rings of the pools of the attacking player only', () => {
    const during = view({ conflict })

    expect(ringsToSwitchWith(during, 'fire').map((ring) => ring.element)).toEqual(['air', 'water', 'void'])
    expect(ringsToSwitchWith(during, 'earth').map((ring) => ring.element)).toEqual(['air', 'water', 'void'])
    expect(
      ringsToSwitchWith({ ...during, conflict: { ...conflict, attacking_player_id: 'opp' } }, 'void').map(
        (ring) => ring.element,
      ),
    ).toEqual(['air', 'earth', 'water'])
  })
})

describe('ringEffects', () => {
  it('claims an unclaimed ring in the type chosen outside of a conflict, takes and places fate', () => {
    expect(ringEffects(view(), 'air', 2)).toEqual([
      {
        type: 'claim_ring',
        label: 'Claim it, military',
        payload: { ring: 'air', target_player_id: 'me', conflict_type: 'military' },
      },
      {
        type: 'claim_ring',
        label: 'Claim it, political',
        payload: { ring: 'air', target_player_id: 'me', conflict_type: 'political' },
      },
      {
        type: 'move_fate',
        label: 'Take 2 fate',
        payload: { source: 'ring', source_id: 'air', target: 'player', target_id: 'me', amount: 2 },
      },
      {
        type: 'move_fate',
        label: 'Place 2 from your pool',
        payload: { source: 'player', source_id: 'me', target: 'ring', target_id: 'air', amount: 2 },
      },
    ])
  })

  it('claims the contested ring for either player in the type of the conflict', () => {
    expect(ringEffects(view({ conflict }), 'fire', 1).map((effect) => [effect.label, effect.payload])).toEqual([
      ['Claim it', { ring: 'fire', target_player_id: 'me' }],
      ['Opp. claims it', { ring: 'fire', target_player_id: 'opp' }],
      ['Place 1 from your pool', { source: 'player', source_id: 'me', target: 'ring', target_id: 'fire', amount: 1 }],
    ])
  })

  it('takes a ring the opponent has claimed, or returns it, in the type it was claimed in', () => {
    const all = view({ available_actions: ['claim_ring', 'return_ring', 'move_fate'] })

    expect(ringEffects(all, 'earth', 1).map((effect) => [effect.label, effect.payload])).toEqual([
      ['Take it', { ring: 'earth', target_player_id: 'me' }],
      ['Return it to the unclaimed pool', { ring: 'earth' }],
      ['Place 1 from your pool', { source: 'player', source_id: 'me', target: 'ring', target_id: 'earth', amount: 1 }],
    ])
    expect(ringEffects(all, 'void', 1).map((effect) => effect.label)).toEqual([
      'Opp. takes it',
      'Return it to the unclaimed pool',
      'Place 1 from your pool',
    ])
  })

  it('places fate from the token pool and removes the fate on a ring', () => {
    const all = view({ available_actions: ['place_fate_on_ring', 'remove_fate_from_ring'] })

    expect(ringEffects(all, 'water', 2).map((effect) => [effect.label, effect.payload])).toEqual([
      ['Remove 2 fate', { ring: 'water', amount: 2 }],
      ['Place 2 from the token pool', { ring: 'water', amount: 2 }],
    ])
    expect(ringEffects(all, 'fire', 1).map((effect) => effect.label)).toEqual(['Place 1 from the token pool'])
  })

  it('offers only the available actions', () => {
    expect(ringEffects(view({ available_actions: [] }), 'air', 1)).toEqual([])
  })
})
