import { fetchSubscription, type Subscription } from '@/api/client'

/** delay before subscribing again once the hub has closed the connection or the token could not be read */
export const RETRY_DELAY = 2000

/**
 * Follows topics of the Mercure hub. The topics are private: the subscriber token of the user
 * (`GET /api/self/subscription`) goes in the `authorization` query parameter. The browser reconnects by itself after a
 * network error; once the hub refuses the connection (the token has expired), a new token is read and the
 * subscription opened again from the last update received (`lastEventID`), so that the hub sends those missed
 * meanwhile. Returns the function that stops following.
 */
export function follow(
  token: string,
  topics: string[] | ((subscription: Subscription) => string[]),
  onUpdate: (data: Record<string, unknown>) => void,
): () => void {
  let source: EventSource | null = null
  let retry: ReturnType<typeof setTimeout> | null = null
  let stopped = false
  let lastEventId: string | null = null

  function again(): void {
    retry = setTimeout(() => {
      retry = null
      void connect()
    }, RETRY_DELAY)
  }

  async function connect(): Promise<void> {
    let subscription: Subscription
    try {
      subscription = await fetchSubscription(token)
    } catch {
      if (!stopped) {
        again()
      }

      return
    }
    if (stopped) {
      return
    }
    const url = new URL(subscription.hub, window.location.href)
    for (const topic of typeof topics === 'function' ? topics(subscription) : topics) {
      url.searchParams.append('topic', topic)
    }
    url.searchParams.set('authorization', subscription.token)
    if (lastEventId !== null) {
      url.searchParams.set('lastEventID', lastEventId)
    }
    const opened = new EventSource(url)
    opened.onmessage = (message: MessageEvent<string>) => {
      lastEventId = message.lastEventId || lastEventId
      try {
        const data: unknown = JSON.parse(message.data)
        if (typeof data === 'object' && data !== null) {
          onUpdate(data as Record<string, unknown>)
        }
      } catch {
        // not an update of the engine
      }
    }
    opened.onerror = () => {
      if (opened.readyState === EventSource.CLOSED && source === opened) {
        source = null
        again()
      }
    }
    source = opened
  }

  if (typeof EventSource !== 'undefined') {
    void connect()
  }

  return () => {
    stopped = true
    source?.close()
    source = null
    if (retry !== null) {
      clearTimeout(retry)
    }
  }
}
