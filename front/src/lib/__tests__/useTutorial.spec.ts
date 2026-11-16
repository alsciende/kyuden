import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'

import type { GameView } from '@/api/game'
import { loadSession, session } from '@/lib/session'
import { SEND_SEEN_TIPS_AFTER, useTutorial } from '@/lib/useTutorial'

import { game, player } from './gameFixture'

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const stored = {
  userId: 'user-1',
  name: 'Hida Takeshi',
  username: 'takeshi',
  preferredClan: 'crab',
  cardDisplay: 'images',
  theme: 'auto',
  tutorial: true,
  seenTips: [] as string[],
  token: 'l5r_a',
}

function self(seenTips: string[], tutorial = true) {
  return {
    name: 'Hida Takeshi',
    username: 'takeshi',
    preferred_clan: 'crab',
    settings: { card_display: 'images', theme: 'auto', tutorial, seen_tips: seenTips },
  }
}

function setUp(view: GameView) {
  const current = ref<GameView | null>(view)
  const cardOpen = ref(false)
  let tutorial!: ReturnType<typeof useTutorial>
  const wrapper = mount(
    defineComponent({
      setup() {
        tutorial = useTutorial(current, {}, cardOpen)

        return () => h('div')
      },
    }),
  )

  return { current, cardOpen, tutorial: () => tutorial, wrapper }
}

describe('useTutorial', () => {
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    vi.useFakeTimers()
    localStorage.setItem('kyuden.session', JSON.stringify(stored))
    session.value = loadSession()
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
    vi.useRealTimers()
    localStorage.clear()
  })

  it('shows no tip outside of a game against the house bot', () => {
    const { tutorial } = setUp(game(player('me'), player('opp'), { training: false }))

    expect(tutorial().tip.value).toBeNull()
  })

  it('remembers the tips closed at once, and sends them together a few seconds later', async () => {
    fetchMock.mockResolvedValue(json(200, self(['welcome', 'player_bar'])))
    const { tutorial } = setUp(game(player('me'), player('opp'), { training: true }))

    expect(tutorial().tip.value?.id).toBe('welcome')
    tutorial().dismiss()
    await nextTick()
    expect(tutorial().tip.value?.id).toBe('player_bar')
    tutorial().dismiss()
    expect(loadSession()?.seenTips).toEqual(['welcome', 'player_bar'])
    expect(fetchMock).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(SEND_SEEN_TIPS_AFTER)

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(fetchMock.mock.calls[0]![1]!.method).toBe('PATCH')
    expect(JSON.parse(fetchMock.mock.calls[0]![1]!.body as string)).toEqual({
      settings: { seen_tips: ['welcome', 'player_bar'] },
    })
  })

  it('counts a tip as seen once the player has done what it says', async () => {
    const { current, tutorial } = setUp(
      game(player('me'), player('opp'), {
        training: true,
        place: 'play_cards_from_provinces',
        available_actions: ['play_character_from_province'],
      }),
    )
    tutorial().dismiss()
    await nextTick()
    expect(tutorial().tip.value?.id).toBe('play_from_provinces')

    current.value = game(player('me'), player('opp'), { training: true, place: 'play_cards_from_provinces' })
    await nextTick()

    expect(loadSession()?.seenTips).toEqual(['welcome', 'play_from_provinces'])
  })

  it('does not count a tip hidden by a card opened as seen', async () => {
    const { cardOpen, tutorial } = setUp(
      game(player('me'), player('opp'), { training: true, place: 'play_cards_from_provinces' }),
    )

    cardOpen.value = true
    await nextTick()

    expect(tutorial().tip.value?.id).toBe('card_actions')
    expect(loadSession()?.seenTips).toEqual([])
  })

  it('turns the tips off at once', async () => {
    fetchMock.mockResolvedValue(json(200, self(['welcome'], false)))
    const { tutorial } = setUp(game(player('me'), player('opp'), { training: true }))

    tutorial().hideTips()

    expect(tutorial().tip.value).toBeNull()
    expect(JSON.parse(fetchMock.mock.calls[0]![1]!.body as string)).toEqual({
      settings: { seen_tips: ['welcome'], tutorial: false },
    })
    await vi.runAllTimersAsync()
    expect(loadSession()?.tutorial).toBe(false)
  })

  it('sends the tips seen when the page is left', () => {
    fetchMock.mockResolvedValue(json(200, self(['welcome'])))
    const { tutorial, wrapper } = setUp(game(player('me'), player('opp'), { training: true }))
    tutorial().dismiss()

    wrapper.unmount()

    expect(fetchMock).toHaveBeenCalledTimes(1)
  })
})
