import { describe, expect, it } from 'vitest'

import { extractToolbar, markApiRequest } from '@/lib/debugToolbar'

describe('markApiRequest', () => {
  it('marks the calls to the API as XMLHttpRequest, keeping their headers', () => {
    const init = markApiRequest('/api/self', { method: 'PATCH', headers: { Authorization: 'Bearer l5r_token' } })

    expect(init?.method).toBe('PATCH')
    const headers = new Headers(init?.headers)
    expect(headers.get('X-Requested-With')).toBe('XMLHttpRequest')
    expect(headers.get('Authorization')).toBe('Bearer l5r_token')
  })

  it('leaves the other requests as they are', () => {
    const init = { method: 'GET' }

    expect(markApiRequest('/console', init)).toBe(init)
    expect(markApiRequest('/.well-known/mercure', undefined)).toBeUndefined()
  })
})

describe('extractToolbar', () => {
  const page = `<!DOCTYPE html><html><body><main>Console</main>
    <link rel="stylesheet" href="http://localhost:8080/_wdt/styles" /><script> </script>
    <div class="sf-toolbar" role="toolbar"><div id="sfwdtabc123"></div><script>window.toolbarLoaded = 'abc123'</script></div>
    </body></html>`

  it('takes the stylesheet and the toolbar of the page', () => {
    const [stylesheet, toolbar] = extractToolbar(page)

    expect(stylesheet.getAttribute('href')).toBe('http://localhost:8080/_wdt/styles')
    expect(toolbar.querySelector('#sfwdtabc123')).not.toBeNull()
  })

  // jsdom runs no script: that the scripts run once added is checked in a browser
  it('keeps the scripts of the toolbar', () => {
    const [, toolbar] = extractToolbar(page)

    expect(toolbar.querySelector('script')?.textContent).toBe("window.toolbarLoaded = 'abc123'")
  })

  it('takes nothing from a page without the toolbar', () => {
    expect(extractToolbar('<html><body><main>Console</main></body></html>')).toEqual([])
  })
})
