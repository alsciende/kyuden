import { computed, onBeforeUnmount, watch, type Ref } from 'vue'

import type { GameView } from '@/api/game'
import type { Cards } from '@/lib/board'
import { rememberTipsSeen, sendSeenTips, session } from '@/lib/session'
import { nextTip, tipApplies, tipsShown, type Tip, type TipContext } from '@/lib/tutorial'

/** the tips seen within this delay go to the API together: one request for several */
export const SEND_SEEN_TIPS_AFTER = 3000

/**
 * The tip of the tutorial the player of a game against the house bot sees now (`lib/tutorial.ts`), and what they do
 * with it. A tip is seen once closed, or once it no longer applies; the browser keeps it at once, the API a few
 * seconds later, once for the tips seen meanwhile, and when the page is left.
 */
export function useTutorial(game: Ref<GameView | null>, cards: Cards, cardOpen: Ref<boolean>) {
  let timer: ReturnType<typeof setTimeout> | null = null

  function send(tutorial?: boolean): void {
    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
    // kept by the browser: sent again with the next tip seen
    sendSeenTips(tutorial).catch(() => {})
  }

  function seen(id: string): void {
    rememberTipsSeen([id])
    if (timer === null) {
      timer = setTimeout(() => send(), SEND_SEEN_TIPS_AFTER)
    }
  }

  const context = computed<TipContext | null>(() => {
    const current = game.value
    if (current === null || session.value === null || !tipsShown(current, session.value.tutorial)) {
      return null
    }

    return { game: current, cards, cardOpen: cardOpen.value }
  })

  const tip = computed<Tip | null>(() => {
    const current = context.value

    return current === null ? null : nextTip(current, new Set(session.value?.seenTips ?? []))
  })

  // a tip that no longer applies has been followed: a tip hidden by a card opened has not
  watch(tip, (_, previous) => {
    const current = context.value
    if (previous && current !== null && previous.id !== tip.value?.id && !tipApplies(previous.id, current)) {
      seen(previous.id)
    }
  })

  onBeforeUnmount(() => {
    if (timer !== null) {
      send()
    }
  })

  return {
    tip,
    /** the player closes the tip */
    dismiss(): void {
      if (tip.value !== null) {
        seen(tip.value.id)
      }
    },
    /** the player turns the tips off: the tip shown counts as seen */
    hideTips(): void {
      if (tip.value !== null) {
        rememberTipsSeen([tip.value.id])
      }
      const current = session.value
      if (current !== null) {
        // at once, so that no tip shows meanwhile
        session.value = { ...current, tutorial: false }
      }
      send(false)
    },
  }
}
