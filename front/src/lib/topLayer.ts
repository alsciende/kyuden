import type { Directive } from 'vue'

/**
 * The top layer of the browser, where the windows over the page are drawn (`components/ModalDialog.vue`), above
 * everything else whatever its `z-index`.
 */

let modals = 0

export function modalOpened(): void {
  modals++
}

export function modalClosed(): void {
  modals = Math.max(0, modals - 1)
}

/** a window is open over the page: the keys go to it, not to what lies under */
export function isModalOpen(): boolean {
  return modals > 0
}

/**
 * Draws an element in the top layer once it is mounted (`v-top-layer`), above the windows open before it: what shows
 * over a window (a tooltip, the image of the card hovered) is mounted each time it shows. A browser without popovers
 * keeps the element where its styles put it, under the windows.
 */
export const vTopLayer: Directive<HTMLElement> = {
  mounted(element) {
    if (typeof element.showPopover !== 'function') {
      return
    }
    // no light dismiss, and none of the styles of a popover (base.css)
    element.setAttribute('popover', 'manual')
    element.showPopover()
  },
}
