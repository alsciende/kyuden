import { describe, expect, it } from 'vitest'

import type { AbandonmentView } from '@/api/game'
import { isAsked, noPrompt, waitLonger, WAIT_LONGER } from '@/lib/abandonment'

const abandoned: AbandonmentView = {
  awaited_player_ids: ['jane'],
  claimable_at: '2026-11-07T12:03:00+00:00',
  claimable: true,
}

describe('abandonment', () => {
  it('asks the player once the engine says the opponent has abandoned the game', () => {
    expect(isAsked(noPrompt(), 0)).toBe(false)
    expect(isAsked({ view: { ...abandoned, claimable: false }, waitUntil: null }, 0)).toBe(false)
    expect(isAsked({ view: abandoned, waitUntil: null }, 0)).toBe(true)
  })

  it('asks again once the player has waited longer', () => {
    const waiting = waitLonger({ view: abandoned, waitUntil: null }, 1_000)

    expect(isAsked(waiting, 1_000 + WAIT_LONGER - 1)).toBe(false)
    expect(isAsked(waiting, 1_000 + WAIT_LONGER)).toBe(true)
  })
})
