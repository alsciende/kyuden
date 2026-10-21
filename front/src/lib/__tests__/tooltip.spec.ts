import { describe, expect, it } from 'vitest'

import { TOOLTIP_WIDTH, tooltipPosition } from '@/lib/tooltip'

describe('tooltipPosition', () => {
  it('puts the text under the element, centered on it', () => {
    expect(tooltipPosition(new DOMRect(400, 100, 44, 44), 50, 1536, 900)).toEqual({
      left: 422 - TOOLTIP_WIDTH / 2,
      top: 152,
    })
  })

  it('puts the text above the element when there is no room below, and keeps it in the window', () => {
    expect(tooltipPosition(new DOMRect(1500, 860, 30, 30), 50, 1536, 900)).toEqual({
      left: 1536 - TOOLTIP_WIDTH - 8,
      top: 860 - 8 - 50,
    })
  })
})
