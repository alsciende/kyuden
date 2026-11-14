import { ref } from 'vue'

import type { GameViewData } from '@/api/game'
import { findClan } from '@/data/clans'
import { DEFAULT_BANNER, type Banner } from '@/data/nobori'

/** how long the match is announced before the game opens */
export const ANNOUNCEMENT_DELAY = 2500

/** how long the announcement waits for the game to be read: past it, a plain "Opponent found" */
export const ANNOUNCEMENT_READ_LIMIT = 1500

/** once the game has opened, how long the announcement waits for its table to be drawn before it goes all the same */
export const TABLE_WAIT_LIMIT = 4000

/** how long the announcement takes to fade out over the table */
export const ANNOUNCEMENT_LEAVE = 250

/**
 * The game whose table is drawn (`GameView`, once its view is read): the announcement stays over the page until then,
 * so that nothing shows between them (the page left, the table being set).
 */
export const tableSet = ref<string | null>(null)

export interface AnnouncedPlayer {
  name: string
  banner: Banner
  /** the clan of their stronghold, once their cards exist */
  clanName: string | null
}

export interface Announcement {
  you: AnnouncedPlayer
  opponent: AnnouncedPlayer
}

/**
 * Who faces whom in a game the user has just been matched in, read from their view of it: the viewer and the other
 * player. Null when the view does not tell (no viewer, not two players).
 */
export function announcementOf(view: GameViewData): Announcement | null {
  const you = view.players.find((player) => player.id === view.viewer_id)
  const opponent = view.players.find((player) => player.id !== view.viewer_id)
  if (you === undefined || opponent === undefined || view.viewer_id === null) {
    return null
  }
  const announced = (player: typeof you): AnnouncedPlayer => ({
    name: player.name,
    banner: player.banner ?? DEFAULT_BANNER,
    clanName: findClan(player.clan)?.name ?? null,
  })

  return { you: announced(you), opponent: announced(opponent) }
}
