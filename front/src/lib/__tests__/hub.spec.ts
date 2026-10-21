import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { follow, RETRY_DELAY } from '@/lib/hub'

class FakeEventSource {
  static readonly CONNECTING = 0
  static readonly OPEN = 1
  static readonly CLOSED = 2
  static opened: FakeEventSource[] = []

  readonly url: URL
  readyState = FakeEventSource.OPEN
  onmessage: ((message: MessageEvent<string>) => void) | null = null
  onerror: (() => void) | null = null

  constructor(url: URL) {
    this.url = url
    FakeEventSource.opened.push(this)
  }

  close(): void {
    this.readyState = FakeEventSource.CLOSED
  }

  receive(data: string, lastEventId = ''): void {
    this.onmessage?.(new MessageEvent('message', { data, lastEventId }))
  }

  refused(): void {
    this.readyState = FakeEventSource.CLOSED
    this.onerror?.()
  }
}

function subscription(token: string): Response {
  return new Response(
    JSON.stringify({ hub: 'http://hub.test/.well-known/mercure', token, topic: '/users/john', expires_at: '' }),
    { status: 200, headers: { 'Content-Type': 'application/json' } },
  )
}

describe('follow', () => {
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    vi.useFakeTimers()
    FakeEventSource.opened = []
    vi.stubGlobal('fetch', fetchMock)
    vi.stubGlobal('EventSource', FakeEventSource)
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
    vi.useRealTimers()
  })

  it('subscribes to the topics with the token of the user', async () => {
    fetchMock.mockResolvedValueOnce(subscription('jwt-1'))
    const updates: unknown[] = []

    follow('l5r_john', ['/games/game-1'], (data) => updates.push(data))
    await vi.waitFor(() => expect(FakeEventSource.opened).toHaveLength(1))

    expect(fetchMock.mock.calls[0]![0]).toBe('/api/self/subscription')
    const url = FakeEventSource.opened[0]!.url
    expect(url.origin + url.pathname).toBe('http://hub.test/.well-known/mercure')
    expect(url.searchParams.getAll('topic')).toEqual(['/games/game-1'])
    expect(url.searchParams.get('authorization')).toBe('jwt-1')
    FakeEventSource.opened[0]!.receive('{"event":"game_updated","version":3}')
    FakeEventSource.opened[0]!.receive('not json')
    expect(updates).toEqual([{ event: 'game_updated', version: 3 }])
  })

  it('reads the topics in the subscription', async () => {
    fetchMock.mockResolvedValueOnce(subscription('jwt-1'))

    follow(
      'l5r_john',
      (current) => [current.topic],
      () => {},
    )
    await vi.waitFor(() => expect(FakeEventSource.opened).toHaveLength(1))

    expect(FakeEventSource.opened[0]!.url.searchParams.getAll('topic')).toEqual(['/users/john'])
  })

  it('subscribes again with a new token from the last update once the hub refuses the connection', async () => {
    fetchMock.mockResolvedValueOnce(subscription('jwt-1')).mockResolvedValueOnce(subscription('jwt-2'))

    follow('l5r_john', ['/games/game-1'], () => {})
    await vi.waitFor(() => expect(FakeEventSource.opened).toHaveLength(1))
    FakeEventSource.opened[0]!.receive('{}', 'urn:uuid:42')
    FakeEventSource.opened[0]!.refused()
    await vi.advanceTimersByTimeAsync(RETRY_DELAY)
    await vi.waitFor(() => expect(FakeEventSource.opened).toHaveLength(2))

    const url = FakeEventSource.opened[1]!.url
    expect(url.searchParams.get('authorization')).toBe('jwt-2')
    expect(url.searchParams.get('lastEventID')).toBe('urn:uuid:42')
  })

  it('closes the subscription once stopped', async () => {
    fetchMock.mockResolvedValueOnce(subscription('jwt-1'))

    const stop = follow('l5r_john', ['/games/game-1'], () => {})
    await vi.waitFor(() => expect(FakeEventSource.opened).toHaveLength(1))
    stop()

    expect(FakeEventSource.opened[0]!.readyState).toBe(FakeEventSource.CLOSED)
  })
})
