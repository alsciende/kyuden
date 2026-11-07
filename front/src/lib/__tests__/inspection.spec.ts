import { describe, expect, it } from 'vitest'

import type { LoggedMessage } from '@/api/game'
import { inspectionActions, inspectionPlays, inspectionsOf, lostInTheShuffle, type Inspection } from '@/lib/inspection'

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

    expect(inspectionsOf([revealed], 'me')).toEqual([{ ...deck, mode: 'reveal', cards: [{ id: 'c3', code: 'fan' }] }])
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
