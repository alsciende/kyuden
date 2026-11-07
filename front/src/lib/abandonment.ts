import type { ComputedRef, InjectionKey } from 'vue'

import type { AbandonmentView } from '@/api/game'

/**
 * Asking the player whether to end a game their opponent no longer plays. The engine tells when the opponent may be
 * held to have stopped playing (three minutes without acting while the game waits for them, one minute once they no
 * longer read it); the player then ends the game, which they win, or waits longer: they are asked again once the game
 * has waited for WAIT_LONGER more, unless it changes meanwhile.
 */

/** how long the player waits once they chose to, in milliseconds */
export const WAIT_LONGER = 180_000

export interface AbandonmentPrompt {
  /** what the engine said last; null while the game changes */
  view: AbandonmentView | null
  /** when the player asked to be asked again (ms since the epoch); null when they have not chosen to wait */
  waitUntil: number | null
}

export function noPrompt(): AbandonmentPrompt {
  return { view: null, waitUntil: null }
}

/** whether the player is asked now */
export function isAsked(prompt: AbandonmentPrompt, now: number): boolean {
  return prompt.view?.claimable === true && (prompt.waitUntil === null || now >= prompt.waitUntil)
}

/** the player chooses to wait longer */
export function waitLonger(prompt: AbandonmentPrompt, now: number): AbandonmentPrompt {
  return { ...prompt, waitUntil: now + WAIT_LONGER }
}

/** the question, provided by the page of the game to the panel of actions */
export interface AbandonmentContext {
  /** the player is asked now */
  asked: ComputedRef<boolean>
  /** the player ends the game, which they win */
  end(): void
  /** the player waits longer */
  wait(): void
}

export const ABANDONMENT: InjectionKey<AbandonmentContext> = Symbol('abandonment')
