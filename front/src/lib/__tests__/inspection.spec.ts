import { describe, expect, it } from 'vitest'

import type { LoggedMessage } from '@/api/game'
import {
  inspectionActions,
  inspectionPlays,
  inspectionsOf,
  isRedistribution,
  lostInTheShuffle,
  type Inspection,
} from '@/lib/inspection'

import { printed } from './gameFixture'

const looked: LoggedMessage = {
  version: 7,
  type: 'look_at_cards',
  player_id: 'me',
  entries: [
    {
      template: '{player} looks at {cards} in {stack}',
      params: {
        player: { type: 'player', id: 'me' },
        cards: {
          type: 'list',
          items: [
            { type: 'card', id: 'c1', code: 'banzai' },
            { type: 'card', id: 'c2', code: 'katana' },
          ],
        },
        stack: { type: 'stack', stack_type: 'conflict_draw_deck', player_id: 'me', id: 'deck' },
      },
    },
  ],
}

const deck: Inspection = {
  version: 7,
  type: 'look_at_cards',
  mode: 'look',
  stackType: 'conflict_draw_deck',
  ownerId: 'me',
  cards: [
    { id: 'c1', code: 'banzai' },
    { id: 'c2', code: 'katana' },
  ],
}

describe('inspectionsOf', () => {
  it('reads the cards the viewer looked at in the log', () => {
    expect(inspectionsOf([looked], 'me')).toEqual([deck])
  })

  it('ignores the cards the opponent looked at, and the other messages', () => {
    expect(inspectionsOf([{ ...looked, player_id: 'opp' }], 'me')).toEqual([])
    expect(inspectionsOf([{ ...looked, type: 'draw_conflict_cards' }], 'me')).toEqual([])
  })

  it('reads the cards revealed, hidden ones left out', () => {
    const revealed: LoggedMessage = {
      ...looked,
      type: 'reveal_card',
      entries: [
        {
          ...looked.entries[0]!,
          params: {
            ...(looked.entries[0]!.params as Record<string, never>),
            cards: {
              type: 'list',
              items: [
                { type: 'card', id: null, code: null },
                { type: 'card', id: 'c3', code: 'fan' },
              ],
            },
          },
        },
      ],
    }

    expect(inspectionsOf([revealed], 'me')).toEqual([
      { ...deck, type: 'reveal_card', mode: 'reveal', cards: [{ id: 'c3', code: 'fan' }] },
    ])
  })

  it('sees the cards of the provinces of a player together, to redistribute them', () => {
    const entry = (id: string, code: string, province: string) => ({
      ...looked.entries[0]!,
      params: {
        player: { type: 'player' as const, id: 'me' },
        cards: { type: 'list' as const, items: [{ type: 'card' as const, id, code }] },
        stack: { type: 'stack' as const, stack_type: 'regular_province' as const, player_id: 'opp', id: province },
      },
    })
    const provinces: LoggedMessage = { ...looked, entries: [entry('a', 'asami', 'p1'), entry('b', 'scout', 'p2')] }

    const [inspection, ...others] = inspectionsOf([provinces], 'me')

    expect(others).toEqual([])
    expect(inspection).toMatchObject({
      stackType: 'regular_province',
      ownerId: 'opp',
      cards: [{ id: 'a' }, { id: 'b' }],
    })
    expect(isRedistribution(inspection!)).toBe(true)
    expect(isRedistribution(deck)).toBe(false)
    expect(isRedistribution({ ...inspection!, type: 'look_at_card' })).toBe(false)
  })
})

describe('inspectionActions', () => {
  it('offers the hand, the deck, the discard pile and revealing a card of a conflict deck looked at', () => {
    expect(
      inspectionActions(deck, deck.cards[0]!, 'me', 'conflict').map((action) => [action.type, action.label]),
    ).toEqual([
      ['add_card_to_hand', 'Add to your hand'],
      ['put_card_on_deck', 'On top of the deck'],
      ['put_card_on_deck', 'At the bottom'],
      ['shuffle_into_deck', 'Shuffle into the deck'],
      ['remove_from_game', 'Remove from the game'],
      ['remove_from_game', 'Remove from the game, facedown'],
      ['reveal_card', 'Reveal it'],
      ['discard_card', 'Discard'],
    ])
  })

  it('removes a card of the deck of the opponent from the game face down, or places it under a card of the viewer', () => {
    const actions = inspectionActions(
      { ...deck, ownerId: 'opp' },
      deck.cards[0]!,
      'me',
      'conflict',
      [],
      [{ id: 'uji', name: 'Daidoji Uji', where: 'in play', group: 'yours' }],
    )

    expect(actions.find((action) => action.label === 'Remove from the game, facedown')?.payload).toEqual({
      card_id: 'c1',
      face_down: true,
    })
    expect(actions.find((action) => action.type === 'place_card_under')).toMatchObject({
      label: 'Under Daidoji Uji, facedown',
      done: 'Under Daidoji Uji',
      payload: { card_id: 'c1', host_card_id: 'uji', face_down: true },
    })
  })

  it("adds a card of the opponent's deck to their hand, and does not reveal what is revealed", () => {
    const actions = inspectionActions({ ...deck, mode: 'reveal', ownerId: 'opp' }, deck.cards[0]!, 'me', 'conflict')

    expect(actions[0]).toMatchObject({
      type: 'add_card_to_hand',
      label: "Add to Opp.'s hand",
      payload: { card_id: 'c1' },
    })
    expect(actions.map((action) => action.type)).not.toContain('reveal_card')
  })

  it('puts a card of a hand into its deck, but not into a hand', () => {
    const types = inspectionActions(
      { ...deck, stackType: 'hand', ownerId: 'opp' },
      deck.cards[0]!,
      'me',
      'conflict',
    ).map((action) => action.type)

    expect(types).toEqual([
      'put_card_on_deck',
      'put_card_on_deck',
      'shuffle_into_deck',
      'remove_from_game',
      'remove_from_game',
      'reveal_card',
      'discard_card',
    ])
  })

  it('puts a card of the dynasty deck into a province of its owner, face up or face down', () => {
    const provinces = [
      { id: 'p1', name: 'Pilgrimage' },
      { id: 'p2', name: 'Shameful Display (stronghold)' },
    ]
    const dynasty = { ...deck, stackType: 'dynasty_draw_deck' as const }
    const puts = inspectionActions(dynasty, deck.cards[0]!, 'me', 'dynasty', provinces).filter(
      (action) => action.type === 'put_card_into_province',
    )

    expect(puts.map((action) => [action.label, action.payload])).toEqual([
      ['Into Pilgrimage, faceup', { card_id: 'c1', province_id: 'p1', face_up: true }],
      ['Into Pilgrimage, facedown', { card_id: 'c1', province_id: 'p1', face_up: false }],
      ['Into Shameful Display (stronghold), faceup', { card_id: 'c1', province_id: 'p2', face_up: true }],
      ['Into Shameful Display (stronghold), facedown', { card_id: 'c1', province_id: 'p2', face_up: false }],
    ])
    expect(
      inspectionActions(deck, deck.cards[0]!, 'me', 'conflict', provinces).map((action) => action.type),
    ).not.toContain('put_card_into_province')
  })

  it('puts a character of a deck into play, into the conflict when its controller takes part in it', () => {
    const intoPlay = { type: 'character', conflict: true, characters: [], provinces: [] }
    const puts = inspectionActions(deck, deck.cards[0]!, 'me', 'conflict', [], [], intoPlay).filter(
      (action) => action.type === 'put_character_into_play',
    )

    expect(puts.map((action) => [action.label, action.done, action.payload])).toEqual([
      ['Put into play', 'In play', { card_id: 'c1', into_conflict: false }],
      ['Put into play in the conflict', 'In the conflict', { card_id: 'c1', into_conflict: true }],
    ])
    expect(
      inspectionActions({ ...deck, ownerId: 'opp' }, deck.cards[0]!, 'me', 'conflict', [], [], {
        ...intoPlay,
        conflict: false,
      })
        .filter((action) => action.type === 'put_character_into_play')
        .map((action) => [action.label, action.payload]),
    ).toEqual([['Put into play under your control', { card_id: 'c1', into_conflict: false, controller_id: 'me' }]])
  })

  it('puts an attachment of a deck into play on a character of either player', () => {
    const intoPlay = {
      type: 'attachment',
      conflict: false,
      characters: [
        { id: 'kaezin', name: 'Kakita Kaezin', mine: true },
        { id: 'spear', name: 'Master of the Spear', mine: false },
      ],
      provinces: [],
    }
    const puts = inspectionActions(deck, deck.cards[1]!, 'me', 'conflict', [], [], intoPlay).filter(
      (action) => action.type === 'put_attachment_into_play',
    )

    expect(puts.map((action) => [action.label, action.done, action.payload])).toEqual([
      ['Attach to Kakita Kaezin', 'Attached to Kakita Kaezin', { card_id: 'c2', target_card_id: 'kaezin' }],
      [
        "Attach to Master of the Spear (Opp.'s)",
        'Attached to Master of the Spear',
        { card_id: 'c2', target_card_id: 'spear' },
      ],
    ])
  })

  it('puts a holding of the dynasty deck into play in a regular province of its owner', () => {
    const intoPlay = { type: 'holding', conflict: false, characters: [], provinces: [{ id: 'p1', name: 'Pilgrimage' }] }
    const dynasty = { ...deck, stackType: 'dynasty_draw_deck' as const }

    expect(
      inspectionActions(dynasty, deck.cards[0]!, 'me', 'dynasty', [], [], intoPlay)
        .filter((action) => action.type === 'put_holding_into_province')
        .map((action) => [action.label, action.payload]),
    ).toEqual([['Into play in Pilgrimage', { card_id: 'c1', province_id: 'p1' }]])
  })

  it('puts nothing into play out of a deck', () => {
    const intoPlay = { type: 'character', conflict: true, characters: [], provinces: [] }
    const types = inspectionActions({ ...deck, stackType: 'hand' }, deck.cards[0]!, 'me', 'conflict', [], [], intoPlay)

    expect(types.map((action) => action.type)).not.toContain('put_character_into_play')
  })

  it('leaves a province where it is', () => {
    expect(inspectionActions({ ...deck, stackType: 'regular_province' }, deck.cards[0]!, 'me', 'province')).toEqual([])
  })

  it('loses track of the cards left in a deck a card is shuffled into', () => {
    const [shuffle] = inspectionActions(deck, deck.cards[0]!, 'me', 'conflict').filter(
      (action) => action.type === 'shuffle_into_deck',
    )

    expect(lostInTheShuffle(deck, shuffle!)).toBe(true)
    expect(lostInTheShuffle({ ...deck, stackType: 'hand' }, shuffle!)).toBe(false)
  })
})

describe('inspectionPlays', () => {
  const banzai = printed('banzai', { type: 'event', cost: 1, ability_timings: ['action'] })

  it('plays a card of a deck as if it were in hand', () => {
    // Artisan Academy, Pillow Book, Infiltrator (a deck of the opponent too)
    expect(inspectionPlays({ ...deck, ownerId: 'opp' }, banzai, new Set(['play_action_event']))).toEqual([
      { type: 'play_action_event', kind: 'event', label: 'Play this event', cost: 1 },
    ])
  })

  it('plays nothing out of a deck, nor without the opportunity to act', () => {
    expect(inspectionPlays({ ...deck, stackType: 'hand' }, banzai, new Set(['play_action_event']))).toEqual([])
    expect(inspectionPlays(deck, banzai, new Set())).toEqual([])
  })
})
