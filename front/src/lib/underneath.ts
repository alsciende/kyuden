import { computed, inject, type ComputedRef, type InjectionKey } from 'vue'
import type { UnderCard } from '@/lib/board'

/**
 * The cards under each card of the board (`cardsUnder()` of `board.ts`), provided by the page of the game to the
 * components that draw the cards they lie under (`UnderPile.vue`): by the id of that card, the last placed first.
 */
export const CARDS_UNDER: InjectionKey<ComputedRef<Map<string, UnderCard[]>>> = Symbol('cards-under')

export function useCardsUnder(): ComputedRef<Map<string, UnderCard[]>> {
  return inject(CARDS_UNDER, () => computed(() => new Map<string, UnderCard[]>()), true)
}
