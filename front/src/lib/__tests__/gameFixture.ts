import type { Card } from '@/api/client'
import type { CardView, GameView, PlayerView, StackType, StackView } from '@/api/game'

/**
 * Views of a game for the tests: a viewer (`me`) against an opponent (`opp`), with the stacks given.
 */

let next = 0

export function card(code: string | null, changes: Partial<CardView> = {}): CardView {
  next += 1

  return {
    id: changes.id ?? `card-${next}`,
    code,
    side: 'conflict',
    orientation: 'face_up',
    fate: 0,
    personal_honor: 'ordinary',
    military_skill: null,
    political_skill: null,
    ...changes,
  }
}

export function stack(type: StackType, cards: CardView[] | null, id = `${type}-${++next}`): StackView {
  return { id, type, count: cards?.length ?? 0, cards }
}

export function player(id: string, stacks: StackView[] = [], changes: Partial<PlayerView> = {}): PlayerView {
  return {
    id,
    user_id: `user-${id}`,
    name: id,
    clan: 'crane',
    honor: 10,
    fate: 5,
    honor_bid: null,
    pending_honor_bid: null,
    honor_bid_selected: false,
    stronghold_province_id: null,
    passed: false,
    acted: false,
    resolving_interrupts_and_reactions: 0,
    fate_collected: false,
    conflict_opportunities: 2,
    declared_conflict_types: [],
    glory_count: null,
    stacks,
    ...changes,
  }
}

export function game(me: PlayerView, opp: PlayerView, changes: Partial<GameView> = {}): GameView {
  const ring = { fate: 0, claimed_by_id: null }

  return {
    id: 'game',
    version: 1,
    place: 'conflict_phase_action_window',
    viewer_id: me.id,
    player_ids: [me.id, opp.id],
    first_player_id: me.id,
    active_player_ids: [],
    players: [me, opp],
    rings: { air: ring, earth: ring, fire: ring, void: ring, water: ring },
    conflict: null,
    duel: null,
    imperial_favor_holder_id: null,
    imperial_favor_side: null,
    glory_count_winner_id: null,
    winner_id: null,
    victory_condition: null,
    available_actions: [],
    ...changes,
  }
}

export function printed(code: string, changes: Partial<Card> = {}): Card {
  return { code, name: code, type: 'character', clan: 'crane', image_url: null, ...changes }
}
