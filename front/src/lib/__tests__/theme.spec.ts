import { describe, expect, it } from 'vitest'

import { applyTheme } from '@/lib/theme'

describe('applyTheme', () => {
  it('forces the day or the night on the root', () => {
    const root = document.createElement('html')

    applyTheme('dark', root)
    expect(root.dataset.theme).toBe('dark')

    applyTheme('light', root)
    expect(root.dataset.theme).toBe('light')
  })

  it('follows the system in auto', () => {
    const root = document.createElement('html')
    root.dataset.theme = 'dark'

    applyTheme('auto', root)

    expect(root.hasAttribute('data-theme')).toBe(false)
  })
})
