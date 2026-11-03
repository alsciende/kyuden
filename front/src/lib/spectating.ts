import { computed, inject, type ComputedRef, type InjectionKey } from 'vue'

/**
 * Whether the game on screen is watched by a spectator, provided by the page of the game to the components that name
 * the players (a spectator reads their names, a player "You" and "Opp.").
 */
export const SPECTATING: InjectionKey<ComputedRef<boolean>> = Symbol('spectating')

export function useSpectating(): ComputedRef<boolean> {
  return inject(SPECTATING, () => computed(() => false), true)
}
