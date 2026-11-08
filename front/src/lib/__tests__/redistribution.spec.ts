import { describe, expect, it } from 'vitest'

import type { Inspection } from '@/lib/inspection'
import {
  emptyProvinces,
  initialPlacement,
  provinceCardsCount,
  redistributedCards,
  redistributionPayload,
  redistributionProvinces,
} from '@/lib/redistribution'

import { card, game, player, printed, stack } from './gameFixture'

const raid = card('raid', { side: 'province' })
const garden = card('garden', { side: 'province' })
const hidden = card(null, { side: 'province', orientation: 'face_down' })
const asami = card('asami', { side: 'dynasty' })
const samurai = card(null, { side: 'dynasty', orientation: 'face_down' })
const scout = card(null, { side: 'dynasty', orientation: 'face_down' })
const keep = card('keep', { side: 'province' })
const opp = player('opp', [
  stack('regular_province', [raid, asami]),
  stack('regular_province', [garden, samurai, scout]),
  stack('regular_province', [hidden]),
  stack('stronghold_province', [card('palace', { side: 'stronghold' }), keep]),
])
const view = game(player('me'), opp)
const cards = { raid: printed('raid'), garden: printed('garden') }
const inspection: Inspection = {
  version: 3,
  type: 'look_at_cards',
  mode: 'look',
  stackType: 'regular_province',
  ownerId: 'opp',
  cards: [
    { id: asami.id, code: 'asami' },
    { id: samurai.id, code: 'samurai' },
    { id: scout.id, code: 'scout' },
    // gone since
    { id: 'gone', code: 'eiji' },
  ],
}

describe('redistribution', () => {
  it('lists the regular provinces of the player, not the stronghold province', () => {
    expect(redistributionProvinces(opp, cards)).toEqual([
      { id: raid.id, name: 'raid' },
      { id: garden.id, name: 'garden' },
      { id: hidden.id, name: 'Province 3' },
    ])
  })

  it('reads the cards looked at still in the provinces, with where they are and how they lie', () => {
    expect(redistributedCards(view, inspection)).toEqual([
      { id: asami.id, code: 'asami', fromId: raid.id, faceUp: true },
      { id: samurai.id, code: 'samurai', fromId: garden.id, faceUp: false },
      { id: scout.id, code: 'scout', fromId: garden.id, faceUp: false },
    ])
  })

  it('tells the provinces left without a card, and sends every card with its province', () => {
    const looked = redistributedCards(view, inspection)
    const placement = { ...initialPlacement(looked), [asami.id]: hidden.id }
    const provinces = redistributionProvinces(opp, cards)

    expect(emptyProvinces(provinces, initialPlacement(looked))).toEqual([{ id: hidden.id, name: 'Province 3' }])
    expect(emptyProvinces(provinces, placement)).toEqual([{ id: raid.id, name: 'raid' }])
    expect(redistributionPayload('opp', looked, placement)).toEqual({
      target_player_id: 'opp',
      card_ids: [asami.id, samurai.id, scout.id],
      province_ids: [hidden.id, garden.id, garden.id],
    })
  })

  it('counts the cards in the provinces and the provinces holding some', () => {
    expect(provinceCardsCount(opp)).toEqual({ cards: 3, provinces: 2 })
  })
})
