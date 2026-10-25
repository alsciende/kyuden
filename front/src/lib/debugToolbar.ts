/**
 * The web debug toolbar of Symfony, in development: Symfony only injects it in the HTML pages it renders, not in those
 * of Vite, so the client takes it from a page of Symfony (the development console) and adds it to its own. Once there,
 * it follows the calls to the API, and shows the profile of the last one (`toolbar.ajax_replace`, which Symfony only
 * applies to the requests marked as `XMLHttpRequest`).
 */
const TOOLBAR_PAGE = '/console'

/** Marks a call to the API as an `XMLHttpRequest`, so that its response tells the toolbar to show its profile. */
export function markApiRequest(input: RequestInfo | URL, init?: RequestInit): RequestInit | undefined {
  const path = typeof input === 'string' ? input : input instanceof URL ? input.pathname : null
  if (path === null || !path.startsWith('/api/')) {
    return init
  }

  const headers = new Headers(init?.headers)
  if (!headers.has('X-Requested-With')) {
    headers.set('X-Requested-With', 'XMLHttpRequest')
  }

  return { ...init, headers }
}

/**
 * The toolbar of a page of Symfony (its stylesheet and its markup), ready to be added to another document: the
 * scripts are created again, those of a parsed document never run.
 */
export function extractToolbar(html: string, target: Document = document): Element[] {
  const page = new DOMParser().parseFromString(html, 'text/html')
  const stylesheet = page.querySelector('link[rel="stylesheet"][href*="/_wdt/"]')
  const toolbar = page.querySelector('.sf-toolbar')
  if (stylesheet === null || toolbar === null) {
    return []
  }

  const nodes = [target.importNode(stylesheet, true), target.importNode(toolbar, true)]
  for (const script of nodes[1].querySelectorAll('script')) {
    const runnable = target.createElement('script')
    runnable.textContent = script.textContent
    script.replaceWith(runnable)
  }

  return nodes
}

export function showDebugToolbar(): void {
  const fetchApi = window.fetch.bind(window)
  window.fetch = (input, init) => fetchApi(input, markApiRequest(input, init))

  fetchApi(TOOLBAR_PAGE)
    .then((response) => response.text())
    .then((html) => document.body.append(...extractToolbar(html)))
    .catch((error: unknown) => console.warn('The web debug toolbar of Symfony is not available', error))
}
