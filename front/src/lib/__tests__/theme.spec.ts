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

  it('keeps the color of the browser of a forced theme, else the one of the system', () => {
    document.head.innerHTML =
      '<meta name="theme-color" content="#f4eee2" media="(prefers-color-scheme: light)" data-scheme="light">' +
      '<meta name="theme-color" content="#1f1b16" media="(prefers-color-scheme: dark)" data-scheme="dark">'
    const media = () =>
      [...document.head.querySelectorAll<HTMLMetaElement>('meta[name="theme-color"]')].map((meta) => meta.media)

    applyTheme('dark')
    expect(media()).toEqual(['not all', 'all'])

    applyTheme('light')
    expect(media()).toEqual(['all', 'not all'])

    applyTheme('auto')
    expect(media()).toEqual(['(prefers-color-scheme: light)', '(prefers-color-scheme: dark)'])

    document.head.innerHTML = ''
    delete document.documentElement.dataset.theme
  })
})
