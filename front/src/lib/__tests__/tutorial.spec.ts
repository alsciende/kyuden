import { describe, expect, it } from 'vitest'

import type { GameView } from '@/api/game'
import { nextTip, placeTip, segmentsOf, tipApplies, tipsShown, TIP_IDS, type TipContext } from '@/lib/tutorial'

import { card, game, player, printed, stack } from './gameFixture'

const cards = {
  assassination: printed('assassination', {
    name: 'Assassination',
    type: 'event',
    text: '<b>Action:</b> Choose a character – discard it.',
  }),
  kuwanan: printed('kuwanan', { name: 'Doji Kuwanan', text: '<b>Action:</b> Bow a character.' }),
}

function context(changes: Partial<GameView> = {}, cardOpen = false, me = player('me')): TipContext {
  return { game: game(me, player('opp'), { training: true, ...changes }), cards, cardOpen }
}

/** every tip seen but those given */
function allSeenBut(...ids: string[]): Set<string> {
  return new Set(TIP_IDS.filter((id) => !ids.includes(id)))
}

describe('tipsShown', () => {
  it('shows the tips to the player of a game against the house bot', () => {
    expect(tipsShown(context().game, true)).toBe(true)
  })

  it('shows none in another game, to a spectator, in a replay, once the game has ended or when turned off', () => {
    expect(tipsShown(context({ training: false }).game, true)).toBe(false)
    expect(tipsShown(context({ spectating: true }).game, true)).toBe(false)
    expect(tipsShown(context({ replaying: true }).game, true)).toBe(false)
    expect(tipsShown(context({ place: 'game_ended' }).game, true)).toBe(false)
    expect(tipsShown(context().game, false)).toBe(false)
  })
})

describe('nextTip', () => {
  it('welcomes the player first', () => {
    expect(nextTip(context(), new Set())?.id).toBe('welcome')
  })

  it('shows the first tip not seen that applies', () => {
    const current = context({ place: 'play_cards_from_provinces', available_actions: ['play_character_from_province'] })

    expect(nextTip(current, new Set(['welcome']))).toMatchObject({ id: 'play_from_provinces', anchor: 'my-provinces' })
  })

  it('asks to reveal the dynasty cards in their step only, while some are face down', () => {
    const faceDown = card('kuwanan', { side: 'dynasty', orientation: 'face_down' })
    const faceUp = card('kuwanan', { side: 'dynasty', orientation: 'face_up' })
    const me = (dynasty: typeof faceDown) =>
      player('me', [stack('regular_province', [card(null, { side: 'province' }), dynasty])])
    // revealing a dynasty card is available in every step: an effect
    const actions = { available_actions: ['reveal_dynasty_card', 'choose_stronghold_province'] }
    const seen = new Set(['welcome', 'choose_stronghold'])

    expect(nextTip(context({ ...actions, place: 'choose_stronghold' }, false, me(faceDown)), seen)?.id).not.toBe(
      'reveal_dynasty',
    )
    expect(
      nextTip(context({ ...actions, place: 'reveal_facedown_dynasty_cards' }, false, me(faceDown)), seen)?.id,
    ).toBe('reveal_dynasty')
    expect(
      nextTip(context({ ...actions, place: 'reveal_facedown_dynasty_cards' }, false, me(faceUp)), seen)?.id,
    ).not.toBe('reveal_dynasty')
  })

  it('tells the player who plays an event that its text box is theirs to apply, naming the card', () => {
    const assassination = card('assassination', { id: 'assassination' })
    const me = player('me', [stack('conflict_discard_pile', [assassination])], {
      acted: true,
      resolutions: [{ card_id: 'assassination', code: 'assassination', timing: 'action', cancelled: false }],
    })

    const tip = nextTip(context({ available_actions: ['end_resolution'] }, false, me), new Set(['welcome']))

    expect(tip).toMatchObject({ id: 'resolve_event', anchor: 'resolution', sequence: null })
    expect(tip?.paragraphs[0]!.map((segment) => segment.text).join('')).toContain('additional costs included')
    expect(tip?.paragraphs[1]).toContainEqual({ text: 'Done with Assassination', strong: true })
  })

  it('tells the player who triggers an ability that its costs are theirs too', () => {
    const kuwanan = card('kuwanan', { id: 'kuwanan' })
    const me = player('me', [stack('in_play', [kuwanan])], {
      acted: true,
      resolutions: [{ card_id: 'kuwanan', code: 'kuwanan', timing: 'action', cancelled: false }],
    })

    const tip = nextTip(context({ available_actions: ['end_resolution'] }, false, me), new Set(['welcome']))

    expect(tip?.id).toBe('resolve_ability')
    expect(tip?.paragraphs[0]!.map((segment) => segment.text).join('')).toContain('its costs included')
  })

  it('asks to end what was played when nothing is named (a character played)', () => {
    const tip = nextTip(context({ available_actions: ['end_resolution'] }), new Set(['welcome']))

    expect(tip).toMatchObject({ id: 'end_action', anchor: 'action-panel' })
  })

  it('shows the tip of a card opened while the characters are played from the provinces only', () => {
    const playing = { place: 'play_cards_from_provinces' }

    expect(nextTip(context(playing, true), new Set())).toMatchObject({ id: 'card_actions', anchor: 'card-actions' })
    expect(nextTip(context({ place: 'choose_stronghold' }, true), new Set())).toBeNull()
    expect(nextTip(context({ place: 'conflict_action_window' }, true), new Set())).toBeNull()
  })

  it('shows only the tip of a card opened while it is open, the rest of the screen being under it', () => {
    expect(nextTip(context({ place: 'play_cards_from_provinces' }, true), new Set(['card_actions']))).toBeNull()
    expect(nextTip(context(), allSeenBut('card_actions'))).toBeNull()
  })

  it('shows nothing once every tip that applies has been seen', () => {
    expect(nextTip(context(), new Set(TIP_IDS))).toBeNull()
  })
})

describe('tipApplies', () => {
  it('tells whether the player still has to do what the tip says, a card opened or not', () => {
    const playing = context({ place: 'play_cards_from_provinces', available_actions: ['play_character_from_province'] })

    expect(tipApplies('play_from_provinces', playing)).toBe(true)
    expect(tipApplies('play_from_provinces', { ...playing, cardOpen: true })).toBe(true)
    expect(tipApplies('play_from_provinces', context({ place: 'play_cards_from_provinces' }))).toBe(false)
  })
})

describe('segmentsOf', () => {
  it('reads the bold parts of a text', () => {
    expect(segmentsOf('Click **Pass** now.')).toEqual([
      { text: 'Click ', strong: false },
      { text: 'Pass', strong: true },
      { text: ' now.', strong: false },
    ])
  })
})

describe('placeTip', () => {
  const screen = { width: 1536, height: 864 }
  const tip = { width: 320, height: 200 }

  it('puts the tip on the left of an anchor in the bar on the right', () => {
    expect(placeTip({ left: 1216, top: 100, width: 300, height: 200 }, tip, screen)).toEqual({
      left: 880,
      top: 100,
      side: 'left',
      arrow: 40,
    })
  })

  it('puts the tip on the right first when asked', () => {
    expect(placeTip({ left: 600, top: 300, width: 360, height: 400 }, tip, screen, 'right').side).toBe('right')
  })

  it('puts the tip above an anchor as wide as the board, at the bottom of the screen', () => {
    const placement = placeTip({ left: 0, top: 790, width: 1196, height: 74 }, tip, screen)

    expect(placement.side).toBe('above')
    expect(placement.top).toBe(790 - 16 - 200)
  })

  it('puts the tip inside an anchor as large as the screen', () => {
    expect(placeTip({ left: 0, top: 0, width: 1536, height: 864 }, tip, screen).side).toBe('inside')
  })
})
