/** the narrowest layout of the site: the `min-width` of the body, an iPad mini in landscape */
export const MIN_WIDTH = 1133

/** the height the board is laid out for: an iPad mini in landscape */
export const DESIGN_HEIGHT = 744

/** a screen whose short side is narrower is a phone */
const PHONE_SHORT_SIDE = 600

export type PhoneOrientation = 'portrait' | 'landscape'

/**
 * The width of the layout of a phone: that of a tablet, scaled down by the browser. In landscape, wide enough for the
 * board to be as tall as on a tablet (the screen of a phone is longer); upright, the narrowest layout, under the
 * notice to turn the phone (`PortraitNotice.vue`). Null for a tablet or a computer, which keep the width of the device.
 */
export function phoneLayoutWidth(
  screenWidth: number,
  screenHeight: number,
  orientation: PhoneOrientation,
): number | null {
  const short = Math.min(screenWidth, screenHeight)
  const long = Math.max(screenWidth, screenHeight)
  if (short >= PHONE_SHORT_SIDE) {
    return null
  }

  return orientation === 'portrait' ? MIN_WIDTH : Math.max(MIN_WIDTH, Math.round((long * DESIGN_HEIGHT) / short))
}

/**
 * Shows a phone the layout of a tablet, scaled down: the width of the layout in the viewport of `index.html`, without
 * an initial scale, so that the browser fits it to the room it really has (in landscape, an iPhone with a notch keeps
 * the page in its safe area, narrower than the screen); `data-phone` on the root tells the orientation to the styles.
 * Called before the application is mounted, then again when the phone turns.
 */
export function followPhoneViewport(doc: Document = document): void {
  const view = doc.defaultView
  const meta = doc.querySelector<HTMLMetaElement>('meta[name="viewport"]')
  if (view === null || meta === null) {
    return
  }

  const portrait = view.matchMedia('(orientation: portrait)')
  const apply = (): void => {
    const orientation: PhoneOrientation = portrait.matches ? 'portrait' : 'landscape'
    const width = phoneLayoutWidth(view.screen.width, view.screen.height, orientation)
    if (width === null) {
      return
    }

    const content = `width=${width}`
    if (meta.content !== content) {
      meta.content = content
    }
    doc.documentElement.dataset.phone = orientation
  }

  apply()
  portrait.addEventListener('change', apply)
}
