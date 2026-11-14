import { describe, expect, it } from 'vitest'

import type { GameViewData, PlayerView } from '@/api/game'
import { announcementOf } from '@/lib/matchAnnouncement'

function player(id: string, name: string, clan: string | null, banner?: PlayerView['banner']): PlayerView {
  return { id, name, clan, ...(banner ? { banner } : {}) } as PlayerView
}

function view(viewerId: string | null, players: PlayerView[]): GameViewData {
  return { viewer_id: viewerId, players } as GameViewData
}

describe('announcementOf', () => {
  it('sets the viewer against the other player, with their nobori and clan', () => {
    const announcement = announcementOf(
      view('p2', [
        player('p1', 'Hida Tetsuo', 'crab', { mon: 'crab', pattern: 'seigaiha' }),
        player('p2', 'Doji Akiko', 'crane', { mon: 'enso', pattern: 'asanoha' }),
      ]),
    )

    expect(announcement).toEqual({
      you: { name: 'Doji Akiko', banner: { mon: 'enso', pattern: 'asanoha' }, clanName: 'Crane' },
      opponent: { name: 'Hida Tetsuo', banner: { mon: 'crab', pattern: 'seigaiha' }, clanName: 'Crab' },
    })
  })

  it('shows the ensō without pattern when the view has no nobori, and no clan before the cards exist', () => {
    const announcement = announcementOf(
      view('p1', [player('p1', 'Doji Akiko', null), player('p2', 'Kyūden no Karō', 'neutral')]),
    )

    expect(announcement?.opponent).toEqual({
      name: 'Kyūden no Karō',
      banner: { mon: 'enso', pattern: 'none' },
      clanName: null,
    })
  })

  it('tells nothing without a viewer or an opponent', () => {
    expect(announcementOf(view(null, [player('p1', 'A', null), player('p2', 'B', null)]))).toBeNull()
    expect(announcementOf(view('p1', [player('p1', 'A', null)]))).toBeNull()
  })
})
