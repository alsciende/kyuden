import { watch } from 'vue'

import type { Theme } from '@/api/client'
import { session } from '@/lib/session'

/**
 * Shows the site in the theme of the player: `data-theme` on the root forces the day or the night (`styles/base.css`),
 * none follows the system.
 */
export function applyTheme(theme: Theme, root: HTMLElement = document.documentElement): void {
  if (theme === 'auto') {
    delete root.dataset.theme
  } else {
    root.dataset.theme = theme
  }
}

/**
 * Follows the theme of the session: at once (the session is read from the browser before the first render), then
 * when it changes (chosen on the account, signed in or out).
 */
export function followTheme(): void {
  watch(
    () => session.value?.theme ?? 'auto',
    (theme) => applyTheme(theme),
    { immediate: true },
  )
}
