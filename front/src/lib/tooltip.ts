import { reactive, type Directive } from 'vue'

/**
 * A short text shown under an element while it is hovered or focused from the keyboard (`v-tip`), drawn above the board
 * and the bar by `TooltipLayer.vue`, so that no scrolling area cuts it. On a touch screen, a press held on the element
 * shows it above the finger, until the next touch: the press does not click the element.
 */
export interface Tooltip {
  text: string | null
  rect: DOMRect | null
  /** above the element, where the finger does not hide it */
  above: boolean
}

export const tooltip = reactive<Tooltip>({ text: null, rect: null, above: false })

const texts = new WeakMap<HTMLElement, string>()

/** how long a touch is held before the text shows, and how far the finger may move meanwhile */
export const LONG_PRESS_DELAY = 500
const LONG_PRESS_SLOP = 10

function showOn(element: HTMLElement, above = false): boolean {
  const text = texts.get(element) || null
  if (text === null) {
    return false
  }
  tooltip.text = text
  tooltip.rect = element.getBoundingClientRect()
  tooltip.above = above

  return true
}

export function hideTooltip(): void {
  tooltip.text = null
  tooltip.rect = null
  tooltip.above = false
}

/** the press being held, before its text shows */
let press: { timer: ReturnType<typeof setTimeout>; x: number; y: number } | null = null
/** the click that ends a press whose text has shown, which must not act */
let swallowClick = false

function cancelPress(): void {
  if (press !== null) {
    clearTimeout(press.timer)
    press = null
  }
}

function isTouch(event: PointerEvent): boolean {
  return event.pointerType === 'touch'
}

function onEnter(event: PointerEvent): void {
  if (!isTouch(event)) {
    showOn(event.currentTarget as HTMLElement)
  }
}

function onLeave(event: PointerEvent): void {
  if (!isTouch(event)) {
    hideTooltip()
  }
}

/** the focus of the keyboard only: a tap focuses a button on some touch screens */
function onFocus(event: FocusEvent): void {
  const element = event.currentTarget as HTMLElement
  if (focusVisible(element)) {
    showOn(element)
  }
}

function focusVisible(element: HTMLElement): boolean {
  try {
    return element.matches(':focus-visible')
  } catch {
    return true
  }
}

function onDown(event: PointerEvent): void {
  if (!isTouch(event)) {
    return
  }
  const element = event.currentTarget as HTMLElement
  cancelPress()
  if (!texts.get(element)) {
    return
  }
  press = {
    x: event.clientX,
    y: event.clientY,
    timer: setTimeout(() => {
      press = null
      swallowClick = showOn(element, true)
    }, LONG_PRESS_DELAY),
  }
}

function onMove(event: PointerEvent): void {
  if (press !== null && Math.hypot(event.clientX - press.x, event.clientY - press.y) > LONG_PRESS_SLOP) {
    cancelPress()
  }
}

/** the menu a long press opens on some touch screens, in place of the text */
function onContextMenu(event: Event): void {
  if (press !== null || swallowClick) {
    event.preventDefault()
  }
}

// once for the page: a new touch hides the text a press has shown and forgets its click; the click is caught before
// any listener of the element
let listening = false

function listen(): void {
  if (listening) {
    return
  }
  listening = true
  window.addEventListener(
    'pointerdown',
    (event) => {
      swallowClick = false
      if (isTouch(event) && tooltip.above) {
        hideTooltip()
      }
    },
    true,
  )
  window.addEventListener(
    'click',
    (event) => {
      if (swallowClick) {
        swallowClick = false
        event.preventDefault()
        event.stopPropagation()
      }
    },
    true,
  )
  window.addEventListener('scroll', () => tooltip.above && hideTooltip(), true)
}

const EVENTS: [string, EventListener][] = [
  ['pointerenter', onEnter as EventListener],
  ['pointerleave', onLeave as EventListener],
  ['focus', onFocus as EventListener],
  ['blur', hideTooltip],
  ['pointerdown', onDown as EventListener],
  ['pointermove', onMove as EventListener],
  ['pointerup', cancelPress],
  ['pointercancel', cancelPress],
  ['contextmenu', onContextMenu],
]

export const vTip: Directive<HTMLElement, string> = {
  mounted(element, binding) {
    listen()
    texts.set(element, binding.value)
    // no callout nor selection on a long press (base.css)
    element.classList.add('has-tip')
    EVENTS.forEach(([name, listener]) => element.addEventListener(name, listener))
  },
  updated(element, binding) {
    texts.set(element, binding.value)
  },
  beforeUnmount(element) {
    EVENTS.forEach(([name, listener]) => element.removeEventListener(name, listener))
    cancelPress()
    hideTooltip()
  },
}

export const TOOLTIP_WIDTH = 240
const GAP = 8
const MARGIN = 8

/**
 * Under the element and centered on it, above it when there is no room below (or when asked: `above`, unless there is
 * no room above), always inside the window.
 */
export function tooltipPosition(
  rect: DOMRect,
  height: number,
  windowWidth: number,
  windowHeight: number,
  above = false,
): { left: number; top: number } {
  const left = Math.min(
    Math.max(rect.left + rect.width / 2 - TOOLTIP_WIDTH / 2, MARGIN),
    windowWidth - TOOLTIP_WIDTH - MARGIN,
  )
  const roomBelow = rect.bottom + GAP + height <= windowHeight - MARGIN
  const roomAbove = rect.top - GAP - height >= MARGIN
  const top = (above ? roomAbove || !roomBelow : !roomBelow) ? rect.top - GAP - height : rect.bottom + GAP

  return { left, top }
}
