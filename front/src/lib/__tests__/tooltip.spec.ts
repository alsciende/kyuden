import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, withDirectives } from 'vue'

import { hideTooltip, LONG_PRESS_DELAY, TOOLTIP_WIDTH, tooltip, tooltipPosition, vTip } from '@/lib/tooltip'

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

  it('puts the text above the element when asked, under it when there is no room above', () => {
    expect(tooltipPosition(new DOMRect(400, 100, 44, 44), 50, 1536, 900, true).top).toBe(100 - 8 - 50)
    expect(tooltipPosition(new DOMRect(400, 20, 44, 44), 50, 1536, 900, true).top).toBe(72)
  })
})

function pointer(type: string, pointerType: string, x = 0, y = 0): Event {
  return Object.assign(new Event(type, { bubbles: true }), { pointerType, clientX: x, clientY: y })
}

describe('vTip', () => {
  const onClick = vi.fn()
  const Tipped = defineComponent({
    props: { text: { type: String, default: 'Honor' } },
    setup: (props) => () => withDirectives(h('button', { onClick }, 'Honor'), [[vTip, props.text]]),
  })

  beforeEach(() => {
    vi.useFakeTimers()
    onClick.mockReset()
    hideTooltip()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows the text while the mouse is over the element, under it', () => {
    const button = mount(Tipped, { attachTo: document.body }).element
    button.dispatchEvent(pointer('pointerenter', 'mouse'))
    expect(tooltip).toMatchObject({ text: 'Honor', above: false })
    button.dispatchEvent(pointer('pointerleave', 'mouse'))
    expect(tooltip.text).toBeNull()
  })

  it('shows nothing on a tap', () => {
    const button = mount(Tipped, { attachTo: document.body }).element
    button.dispatchEvent(pointer('pointerenter', 'touch'))
    button.dispatchEvent(pointer('pointerdown', 'touch'))
    vi.advanceTimersByTime(LONG_PRESS_DELAY - 100)
    button.dispatchEvent(pointer('pointerup', 'touch'))
    vi.advanceTimersByTime(LONG_PRESS_DELAY)
    button.dispatchEvent(new Event('click', { bubbles: true }))
    expect(tooltip.text).toBeNull()
    expect(onClick).toHaveBeenCalledOnce()
  })

  it('shows the text above the element on a long press, without clicking it, until the next touch', () => {
    const button = mount(Tipped, { attachTo: document.body }).element
    button.dispatchEvent(pointer('pointerdown', 'touch'))
    vi.advanceTimersByTime(LONG_PRESS_DELAY)
    expect(tooltip).toMatchObject({ text: 'Honor', above: true })
    button.dispatchEvent(pointer('pointerup', 'touch'))
    button.dispatchEvent(pointer('pointerleave', 'touch'))
    button.dispatchEvent(new Event('click', { bubbles: true }))
    expect(tooltip.text).toBe('Honor')
    expect(onClick).not.toHaveBeenCalled()

    document.body.dispatchEvent(pointer('pointerdown', 'touch'))
    expect(tooltip.text).toBeNull()
    button.dispatchEvent(new Event('click', { bubbles: true }))
    expect(onClick).toHaveBeenCalledOnce()
  })

  it('shows nothing when the finger moves away, nor for an element without text', () => {
    const button = mount(Tipped, { attachTo: document.body }).element
    button.dispatchEvent(pointer('pointerdown', 'touch', 10, 10))
    button.dispatchEvent(pointer('pointermove', 'touch', 10, 40))
    vi.advanceTimersByTime(LONG_PRESS_DELAY)
    expect(tooltip.text).toBeNull()

    const empty = mount(Tipped, { props: { text: '' }, attachTo: document.body }).element
    empty.dispatchEvent(pointer('pointerdown', 'touch'))
    vi.advanceTimersByTime(LONG_PRESS_DELAY)
    expect(tooltip.text).toBeNull()
    empty.dispatchEvent(new Event('click', { bubbles: true }))
    expect(onClick).toHaveBeenCalledOnce()
  })
})
