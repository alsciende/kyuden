import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { completeOAuth, enabledProviders, pendingPurpose, startOAuth } from '@/lib/oauth'
import { loadSession } from '@/lib/session'

function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

const account = {
  user_id: 'jane',
  name: 'Doji Hotaru',
  username: 'jane',
  preferred_clan: 'crane',
  settings: { card_display: 'images', theme: 'auto' },
  token: 'l5r_new',
}

const authorization = { authorization_url: 'https://accounts.google.com/o/oauth2/v2/auth?state=s1', state: 's1' }

describe('oauth', () => {
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    localStorage.clear()
    sessionStorage.clear()
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    fetchMock.mockReset()
    vi.unstubAllGlobals()
  })

  function signedIn(): void {
    localStorage.setItem(
      'kyuden.session',
      JSON.stringify({
        userId: 'jane',
        name: 'Doji Hotaru',
        username: 'jane',
        preferredClan: 'crane',
        token: 'l5r_jane',
      }),
    )
  }

  async function start(purpose: 'sign_in' | 'link'): Promise<string[]> {
    const visited: string[] = []
    fetchMock.mockResolvedValueOnce(json(201, authorization))
    await startOAuth('google', purpose, (url) => visited.push(url))

    return visited
  }

  it('lists the enabled providers it knows, in their order', async () => {
    fetchMock.mockResolvedValueOnce(json(200, ['discord', 'reddit', 'google']))

    expect(await enabledProviders()).toEqual(['google', 'discord'])
  })

  it('lists no provider when the API cannot tell', async () => {
    fetchMock.mockRejectedValueOnce(new TypeError('network'))

    expect(await enabledProviders()).toEqual([])
  })

  it('sends the visitor to the provider to sign in, and signs them in with the code sent back', async () => {
    expect(await start('sign_in')).toEqual([authorization.authorization_url])
    expect(fetchMock.mock.calls[0]![0]).toBe('/api/oauth/google/authorizations')
    expect(JSON.parse(fetchMock.mock.calls[0]![1]!.body as string)).toEqual({ purpose: 'sign_in' })
    expect((fetchMock.mock.calls[0]![1]!.headers as Record<string, string>).Authorization).toBeUndefined()
    expect(pendingPurpose()).toBe('sign_in')

    fetchMock.mockResolvedValueOnce(json(201, account))
    expect(await completeOAuth('google', { code: 'c1', state: 's1' })).toEqual({
      purpose: 'sign_in',
      outcome: 'signed_in',
    })
    expect(fetchMock.mock.calls[1]![0]).toBe('/api/oauth/google/sessions')
    expect(JSON.parse(fetchMock.mock.calls[1]![1]!.body as string)).toEqual({ code: 'c1', state: 's1' })
    expect(loadSession()?.token).toBe('l5r_new')
  })

  it('tells when no account is linked to the identity', async () => {
    await start('sign_in')
    fetchMock.mockResolvedValueOnce(json(401, { type: 'not_linked', detail: 'No account is linked.' }))

    expect(await completeOAuth('google', { code: 'c1', state: 's1' })).toEqual({
      purpose: 'sign_in',
      outcome: 'not_linked',
    })
    expect(loadSession()).toBeNull()
  })

  it('links the account the visitor is signed in to', async () => {
    signedIn()
    await start('link')
    expect((fetchMock.mock.calls[0]![1]!.headers as Record<string, string>).Authorization).toBe('Bearer l5r_jane')

    fetchMock.mockResolvedValueOnce(json(201, { provider: 'google', linked_at: '2026-10-26T10:00:00+00:00' }))
    expect(await completeOAuth('google', { code: 'c1', state: 's1' })).toEqual({ purpose: 'link', outcome: 'linked' })
    expect(fetchMock.mock.calls[1]![0]).toBe('/api/self/linked-accounts/google')
    expect(fetchMock.mock.calls[1]![1]!.method).toBe('PUT')
    expect((fetchMock.mock.calls[1]![1]!.headers as Record<string, string>).Authorization).toBe('Bearer l5r_jane')
  })

  it('tells when the identity is linked to another account', async () => {
    signedIn()
    await start('link')
    fetchMock.mockResolvedValueOnce(json(409, { type: 'identity_already_linked', detail: 'Already linked.' }))

    expect(await completeOAuth('google', { code: 'c1', state: 's1' })).toEqual({
      purpose: 'link',
      outcome: 'linked_elsewhere',
    })
  })

  it('refuses a state the tab did not start, and uses a state once', async () => {
    expect(await completeOAuth('google', { code: 'c1', state: 's1' })).toEqual({
      purpose: 'sign_in',
      outcome: 'failed',
    })

    await start('sign_in')
    expect(await completeOAuth('google', { code: 'c1', state: 'another' })).toEqual({
      purpose: 'sign_in',
      outcome: 'failed',
    })
    // the state was taken by the attempt above
    expect(await completeOAuth('google', { code: 'c1', state: 's1' })).toEqual({
      purpose: 'sign_in',
      outcome: 'failed',
    })
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('fails for another provider, or when the visitor refused at the provider', async () => {
    await start('sign_in')
    expect((await completeOAuth('discord', { code: 'c1', state: 's1' })).outcome).toBe('failed')

    fetchMock.mockResolvedValueOnce(json(201, authorization))
    await startOAuth('google', 'sign_in', () => {})
    expect((await completeOAuth('google', { error: 'access_denied', state: 's1' })).outcome).toBe('failed')
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })
})
