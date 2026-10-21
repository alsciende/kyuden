import { reactive, type Directive } from 'vue'

/**
 * A short text shown under an element while it is hovered or focused (`v-tip`), drawn above the board and the bar
 * by `TooltipLayer.vue`, so that no scrolling area cuts it.
 */
export interface Tooltip {
  text: string | null
  rect: DOMRect | null
}

export const tooltip = reactive<Tooltip>({ text: null, rect: null })

const texts = new WeakMap<HTMLElement, string>()

function show(event: Event): void {
  const element = event.currentTarget as HTMLElement
  tooltip.text = texts.get(element) ?? null
  tooltip.rect = element.getBoundingClientRect()
}

export function hideTooltip(): void {
  tooltip.text = null
  tooltip.rect = null
}

const EVENTS: [string, (event: Event) => void][] = [
  ['mouseenter', show],
  ['focus', show],
  ['mouseleave', hideTooltip],
  ['blur', hideTooltip],
]

export const vTip: Directive<HTMLElement, string> = {
  mounted(element, binding) {
    texts.set(element, binding.value)
    EVENTS.forEach(([name, listener]) => element.addEventListener(name, listener))
  },
  updated(element, binding) {
    texts.set(element, binding.value)
  },
  beforeUnmount(element) {
    EVENTS.forEach(([name, listener]) => element.removeEventListener(name, listener))
    hideTooltip()
  },
}

export const TOOLTIP_WIDTH = 240
const GAP = 8
const MARGIN = 8

/** under the element and centered on it, above it when there is no room below, always inside the window */
export function tooltipPosition(
  rect: DOMRect,
  height: number,
  windowWidth: number,
  windowHeight: number,
): { left: number; top: number } {
  const left = Math.min(
    Math.max(rect.left + rect.width / 2 - TOOLTIP_WIDTH / 2, MARGIN),
    windowWidth - TOOLTIP_WIDTH - MARGIN,
  )
  const top = rect.bottom + GAP + height > windowHeight - MARGIN ? rect.top - GAP - height : rect.bottom + GAP

  return { left, top }
}
