import { describe, expect, it } from 'vitest'

import type { AbilityWindowView, GameView } from '@/api/game'
import { shownInterruptWindow } from '@/lib/interrupts'

import { card, game, player, printed, stack } from './gameFixture'

const cards = {
  kisada: printed('kisada', { name: 'Hida Kisada', type: 'character' }),
  reprieve: printed('reprieve', { name: 'Reprieve', type: 'attachment' }),
  ground: printed('ground', { name: 'Stand Your Ground', type: 'event' }),
  banzai: printed('banzai', { name: 'Banzai!', type: 'event' }),
  mori: printed('mori', { name: 'Kuroi Mori', type: 'province' }),
  katana: printed('katana', { name: 'Fine Katana', type: 'attachment' }),
}

function window(changes: Partial<AbilityWindowView> = {}): AbilityWindowView {
  return {
    id: '1',
    timing: 'interrupt',
    trigger: 'card_leaving_play',
    params: { card: 'kisada' },
    card_ids: ['reprieve'],
    forced_card_ids: [],
    stage: 'would',
    awaited_player_ids: ['me'],
    timed: false,
    ...changes,
  }
}

function view(windows: AbilityWindowView[], changes: Partial<GameView> = {}): GameView {
  const me = player(
    'me',
    [
      stack('in_play', [card('kisada', { id: 'kisada' }), card('reprieve', { id: 'reprieve' })]),
      stack('hand', [card('ground', { id: 'ground' })]),
    ],
    { ability_windows: windows },
  )

  return game(me, player('opp', [], { name: 'Jane' }), changes)
}

describe('shownInterruptWindow', () => {
  it('tells what is about to happen and the cards of the viewer', () => {
    expect(shownInterruptWindow(view([window()]), cards)).toEqual({
      id: '1',
      trigger: 'Hida Kisada would leave play',
      stage: 'Would',
      offers: [{ cardId: 'reprieve', name: 'Reprieve', kind: 'attachment', forced: false }],
      mine: true,
      waitingFor: [],
      timed: false,
    })
  })

  it('tells the other players whom the game waits for', () => {
    const shown = shownInterruptWindow(view([window({ card_ids: [], awaited_player_ids: ['opp'] })]), cards)

    expect(shown?.mine).toBe(false)
    expect(shown?.waitingFor).toEqual(['Jane'])
  })

  it('passes for the viewer asked about a card out of sight, but for a forced interrupt', () => {
    expect(shownInterruptWindow(view([window({ card_ids: ['ground'], timed: true })]), cards)?.timed).toBe(true)
    expect(shownInterruptWindow(view([window({ card_ids: [], timed: true })]), cards)?.timed).toBe(true)
    expect(shownInterruptWindow(view([window({ timed: true, forced_card_ids: ['reprieve'] })]), cards)?.timed).toBe(
      false,
    )
  })

  it('names the card announced, an event in the hand of the opponent included', () => {
    const trigger = (name: string, params: Record<string, string>): string | undefined =>
      shownInterruptWindow(view([window({ trigger: name, params: { player: 'opp', ...params } })]), cards)?.trigger

    expect(trigger('effects_initiating', { card: 'hidden', code: 'banzai', kind: 'event' })).toBe(
      'Jane is about to play Banzai!',
    )
    expect(trigger('effects_initiating', { card: 'mori', code: 'mori', kind: 'ability' })).toBe(
      'Jane is about to trigger Kuroi Mori',
    )
    expect(trigger('attachment_being_played', { card: 'hidden', code: 'katana', target: 'kisada' })).toBe(
      'Jane is about to play Fine Katana on Hida Kisada',
    )
  })

  it('names the hand of an event offered', () => {
    expect(shownInterruptWindow(view([window({ card_ids: ['ground'] })]), cards)?.offers[0]?.kind).toBe('event · hand')
  })

  it('is none without interrupt window, for a spectator and in a replay', () => {
    const reaction = window({ timing: 'reaction' })

    expect(shownInterruptWindow(view([reaction]), cards)).toBeNull()
    expect(shownInterruptWindow(view([window()], { spectating: true }), cards)).toBeNull()
    expect(shownInterruptWindow(view([window()], { replaying: true }), cards)).toBeNull()
  })
})
