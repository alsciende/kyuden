import { reactive, watch } from 'vue'

import type { BotGame } from '@/api/client'
import type { ClanId } from '@/data/clans'

/**
 * The choices of a newcomer on the way to their first game: their clan, their name at court and the game created for
 * them. Kept by the tab, so that reloading a page of the way loses nothing.
 */
export interface NewcomerState {
  clanId: ClanId | null
  name: string | null
  /** the clan the name was drawn for: another clan draws another name */
  nameClanId: ClanId | null
  game: BotGame | null
}

const KEY = 'kyuden.newcomer'

function load(): NewcomerState {
  const empty: NewcomerState = { clanId: null, name: null, nameClanId: null, game: null }
  try {
    const value = sessionStorage.getItem(KEY)

    return value === null ? empty : { ...empty, ...(JSON.parse(value) as Partial<NewcomerState>) }
  } catch {
    return empty
  }
}

export const newcomer = reactive<NewcomerState>(load())

watch(newcomer, (state) => {
  try {
    sessionStorage.setItem(KEY, JSON.stringify(state))
  } catch {
    // nothing is kept: reloading starts the way again
  }
})
