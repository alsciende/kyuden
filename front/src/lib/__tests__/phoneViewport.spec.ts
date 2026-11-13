import { afterEach, describe, expect, it, vi } from 'vitest'

import { DESIGN_HEIGHT, followPhoneViewport, MIN_WIDTH, phoneLayoutWidth } from '@/lib/phoneViewport'

describe('phoneLayoutWidth', () => {
  it('leaves a tablet and a computer at the width of the device', () => {
    expect(phoneLayoutWidth(744, 1133, 'landscape')).toBeNull()
    expect(phoneLayoutWidth(1920, 1080, 'landscape')).toBeNull()
  })

  it('makes the board of a phone in landscape as tall as on a tablet', () => {
    // an iPhone 15: 393 × 852
    expect(phoneLayoutWidth(393, 852, 'landscape')).toBe(Math.round((852 * DESIGN_HEIGHT) / 393))
    // whatever side the screen reports first
    expect(phoneLayoutWidth(852, 393, 'landscape')).toBe(1613)
  })

  it('never goes narrower than the body', () => {
    expect(phoneLayoutWidth(500, 560, 'landscape')).toBe(MIN_WIDTH)
  })

  it('gives a phone held upright the narrowest layout', () => {
    expect(phoneLayoutWidth(393, 852, 'portrait')).toBe(MIN_WIDTH)
  })
})

describe('followPhoneViewport', () => {
  const meta = (): HTMLMetaElement => document.head.querySelector<HTMLMetaElement>('meta[name="viewport"]')!

  /** a screen and an orientation the test turns */
  function device(width: number, height: number, portrait: boolean): (portrait: boolean) => void {
    vi.spyOn(window.screen, 'width', 'get').mockReturnValue(width)
    vi.spyOn(window.screen, 'height', 'get').mockReturnValue(height)
    const listeners: (() => void)[] = []
    const query = {
      matches: portrait,
      addEventListener: (_: string, listener: () => void) => listeners.push(listener),
    }
    window.matchMedia = vi.fn().mockReturnValue(query)

    return (turned) => {
      query.matches = turned
      listeners.forEach((listener) => listener())
    }
  }

  afterEach(() => {
    vi.restoreAllMocks()
    delete document.documentElement.dataset.phone
  })

  it('sets the width of the layout of a phone, then follows it as it turns', () => {
    document.head.innerHTML = '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
    const turn = device(393, 852, false)

    followPhoneViewport()
    expect(meta().content).toBe('width=1613')
    expect(document.documentElement.dataset.phone).toBe('landscape')

    turn(true)
    expect(meta().content).toBe(`width=${MIN_WIDTH}`)
    expect(document.documentElement.dataset.phone).toBe('portrait')
  })

  it('leaves a tablet as it is', () => {
    document.head.innerHTML = '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
    device(744, 1133, false)

    followPhoneViewport()

    expect(meta().content).toBe('width=device-width, initial-scale=1.0')
    expect(document.documentElement.dataset.phone).toBeUndefined()
  })
})
