import { describe, expect, it } from 'vitest'

import { seenFrom, type GameViewData } from '@/api/game'
import { game, player } from '@/lib/__tests__/gameFixture'

const data = (viewerId: string | null): GameViewData => ({
  ...game(player('first'), player('second'), { player_ids: ['first', 'second'] }),
  viewer_id: viewerId,
})

describe('seenFrom', () => {
  it('keeps the view of a player as it is', () => {
    expect(seenFrom(data('second'), 'first')).toMatchObject({ viewer_id: 'second', spectating: false })
  })

  it('shows a spectator the side of the first player in player order', () => {
    expect(seenFrom(data(null), null)).toMatchObject({ viewer_id: 'first', spectating: true })
  })

  it('shows a spectator the side they chose', () => {
    expect(seenFrom(data(null), 'second')).toMatchObject({ viewer_id: 'second', spectating: true })
    expect(seenFrom(data(null), 'unknown')).toMatchObject({ viewer_id: 'first', spectating: true })
  })
})
