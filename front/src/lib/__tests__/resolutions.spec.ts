import { describe, expect, it } from 'vitest'

import type { ResolutionView } from '@/api/game'
import { endingOrder, othersResolving, resolvingCardIds, resolvingOf, ringResolving } from '@/lib/resolutions'

import { card, game, player, printed, stack } from './gameFixture'

const cards = {
  kuwanan: printed('kuwanan', {
    name: 'Doji Kuwanan',
    text: 'If you control Doji Hotaru, discard her.<br><b>Action:</b> Bow a character.',
  }),
  banzai: printed('banzai', { name: 'Banzai!', type: 'event', text: '<b>Action:</b> +2 skill.' }),
  gift: printed('gift', { name: 'Gift', type: 'event', text: '<b>Reaction:</b> Gain 1 honor.' }),
}

const kuwanan = card('kuwanan', { id: 'kuwanan' })
const banzai = card('banzai', { id: 'banzai' })
const gift = card('gift', { id: 'gift' })

function view(resolutions: ResolutionView[] = []) {
  const me = player('me', [stack('in_play', [kuwanan]), stack('conflict_discard_pile', [banzai, gift])], {
    acted: true,
    resolutions,
  })

  return game(me, player('opp'), {
    conflict: {
      attacking_player_id: 'me',
      defending_player_id: 'opp',
      ring: 'fire',
      type: 'military',
      province_id: 'province',
      attacked_province_ids: ['province'],
      attacker_ids: ['kuwanan'],
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
}

describe('resolvingOf', () => {
  it('shows the ability being resolved, the paragraph of its timing standing out', () => {
    const current = view([{ card_id: 'kuwanan', code: 'kuwanan', timing: 'action' }])
    const resolving = resolvingOf(current, current.players[0]!, cards)

    expect(resolving?.current).toMatchObject({
      cardId: 'kuwanan',
      event: false,
      label: 'Action · ability',
      where: 'Attacking in the conflict',
    })
    expect(resolving?.current.paragraphs.map((paragraph) => paragraph.emphasized)).toEqual([false, true])
    expect(resolving?.then).toEqual([])
  })

  it('shows first what EndResolution ends: the last interrupt or reaction, then the action', () => {
    const current = view([
      { card_id: 'banzai', code: 'banzai', timing: 'action' },
      { card_id: 'gift', code: 'gift', timing: 'reaction' },
      { card_id: 'kuwanan', code: 'kuwanan', timing: 'action' },
    ])
    const resolving = resolvingOf(current, current.players[0]!, cards)

    expect(resolving?.current).toMatchObject({ cardId: 'gift', event: true, label: 'Reaction · event', where: null })
    expect(resolving?.then).toEqual(['Doji Kuwanan', 'Banzai!'])
  })

  it('shows nothing for a character played or a card no longer in sight', () => {
    const played = view()
    const lost = view([{ card_id: null, code: null, timing: 'reaction' }])

    expect(resolvingOf(played, played.players[0]!, cards)).toBeNull()
    expect(resolvingOf(lost, lost.players[0]!, cards)).toBeNull()
  })
})

describe('endingOrder', () => {
  it('ends the interrupts and reactions first, the last one first', () => {
    const order = endingOrder([
      { card_id: 'a', code: null, timing: 'action' },
      { card_id: 'i', code: null, timing: 'interrupt' },
      { card_id: 'b', code: null, timing: 'action' },
      { card_id: 'r', code: null, timing: 'reaction' },
    ])

    expect(order.map((resolution) => resolution.card_id)).toEqual(['r', 'i', 'b', 'a'])
  })
})

describe('othersResolving and resolvingCardIds', () => {
  function both(spectating: boolean) {
    const me = player('me', [stack('in_play', [kuwanan])], {
      acted: true,
      resolutions: [{ card_id: 'kuwanan', code: 'kuwanan', timing: 'action' }],
    })
    const opp = player('opp', [stack('conflict_discard_pile', [gift])], {
      resolutions: [{ card_id: 'gift', code: 'gift', timing: 'reaction' }],
    })

    return game(me, opp, { spectating })
  }

  it('tells a player what the opponent resolves, their own being in the panel of actions', () => {
    const others = othersResolving(both(false), cards)

    expect(others.map(({ player: other, resolving }) => [other.id, resolving.current.card.name])).toEqual([
      ['opp', 'Gift'],
    ])
  })

  it('tells a spectator what every player resolves', () => {
    expect(othersResolving(both(true), cards).map(({ player: other }) => other.id)).toEqual(['me', 'opp'])
  })

  it('marks on the board the card each player resolves now', () => {
    expect(resolvingCardIds(both(false), cards)).toEqual(new Set(['kuwanan', 'gift']))
  })
})

describe('ringResolving', () => {
  it('shows the effect of the contested ring while the attacker resolves it', () => {
    const current = { ...view(), place: 'resolve_ring_effects', active_player_ids: ['me'] }

    expect(ringResolving(current)).toMatchObject({
      player: { id: 'me' },
      element: 'fire',
      name: 'Fire ring',
      text: 'Honor or dishonor a character.',
    })
  })

  it('shows nothing once the attacker has passed, nor in another step', () => {
    expect(ringResolving({ ...view(), place: 'resolve_ring_effects', active_player_ids: [] })).toBeNull()
    expect(ringResolving({ ...view(), active_player_ids: ['me'] })).toBeNull()
  })
})
