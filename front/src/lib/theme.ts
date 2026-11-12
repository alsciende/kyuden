import { watch } from 'vue'

import type { Theme } from '@/api/client'
import { session } from '@/lib/session'

/**
 * Shows the site in the theme of the player: `data-theme` on the root forces the day or the night (the color scheme
 * of `styles/base.css`), none follows the system. The color of the browser around the page follows: of the two
 * `theme-color` of `index.html` (one per scheme, chosen by the system), only the one of a forced theme is kept.
 */
export function applyTheme(theme: Theme, root: HTMLElement = document.documentElement): void {
  if (theme === 'auto') {
    delete root.dataset.theme
  } else {
    root.dataset.theme = theme
  }

  root.ownerDocument.querySelectorAll<HTMLMetaElement>('meta[name="theme-color"][data-scheme]').forEach((meta) => {
    const scheme = meta.dataset.scheme
    if (theme === 'auto') {
      meta.media = `(prefers-color-scheme: ${scheme})`
    } else {
      meta.media = scheme === theme ? 'all' : 'not all'
    }
  })
}

/**
 * Follows the theme of the session: at once (the session is read from the browser before the first render; a script
 * of `index.html` has set a forced theme before the first paint), then when it changes (chosen on the account, signed
 * in or out).
 */
export function followTheme(): void {
  watch(
    () => session.value?.theme ?? 'auto',
    (theme) => applyTheme(theme),
    { immediate: true },
  )
}
