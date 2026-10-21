import { describe, expect, it } from 'vitest'

import { PREVIEW_HEIGHT, PREVIEW_WIDTH, previewPosition } from '@/lib/preview'

const rect = (left: number, top: number) => new DOMRect(left, top, 96, 134)

describe('previewPosition', () => {
  it('puts the image above and to the right of a card of the viewer', () => {
    expect(previewPosition(rect(100, 600), true, 1200, 900)).toEqual({
      left: 100 + 96 + 6,
      top: 600 - 6 - PREVIEW_HEIGHT,
    })
  })

  it('puts the image below and to the right of a card of the opponent', () => {
    expect(previewPosition(rect(100, 40), false, 1200, 900)).toEqual({ left: 202, top: 40 + 134 + 6 })
  })

  it('puts the image on the left when there is no room on the right, and keeps it in the window', () => {
    expect(previewPosition(rect(1000, 100), true, 1200, 900)).toEqual({ left: 1000 - 6 - PREVIEW_WIDTH, top: 8 })
  })
})
