import { reactive } from 'vue'

/**
 * The image of the card under the pointer, shown beside it after a short delay (so that crossing the board does not
 * flash images): above and to the right of the cards of the viewer, below and to the right of the opponent's.
 */
export interface Preview {
  code: string | null
  rect: DOMRect | null
  mine: boolean
}

export const preview = reactive<Preview>({ code: null, rect: null, mine: true })

const DELAY = 150

let timer: ReturnType<typeof setTimeout> | null = null

export function showPreview(code: string | null, element: HTMLElement, mine: boolean): void {
  hidePreview()
  if (code === null) {
    return
  }
  timer = setTimeout(() => {
    preview.code = code
    preview.rect = element.getBoundingClientRect()
    preview.mine = mine
  }, DELAY)
}

export function hidePreview(): void {
  if (timer !== null) {
    clearTimeout(timer)
    timer = null
  }
  preview.code = null
  preview.rect = null
}

export const PREVIEW_WIDTH = 250
export const PREVIEW_HEIGHT = 349
const GAP = 6
const MARGIN = 8

/**
 * Where the image goes: its bottom left corner on the top right corner of a card of the viewer, its top left corner
 * on the bottom right corner of a card of the opponent; on the left of the card when there is no room on its right
 * (before `right`, the edge of the board), and always inside the window.
 */
export function previewPosition(
  rect: DOMRect,
  mine: boolean,
  right: number,
  height: number,
): { left: number; top: number } {
  let left = rect.right + GAP
  if (left + PREVIEW_WIDTH > right) {
    left = rect.left - GAP - PREVIEW_WIDTH
  }
  let top = mine ? rect.top - GAP - PREVIEW_HEIGHT : rect.bottom + GAP
  top = Math.min(Math.max(top, MARGIN), height - PREVIEW_HEIGHT - MARGIN)

  return { left: Math.max(left, MARGIN), top }
}
