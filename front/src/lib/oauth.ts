import {
  ApiProblem,
  createOAuthAuthorization,
  fetchOAuthProviders,
  linkAccount,
  type OAuthProvider,
  type OAuthPurpose,
} from '@/api/client'
import { loadSession, signInWithProvider } from '@/lib/session'

/**
 * Signing in with a provider of identities (Google, Discord), once the account is linked there. The API starts the
 * flow and gives the URL of the provider; the provider sends the visitor back to `/oauth/<provider>/callback` with a
 * code and the state, which the page sends to the API. The tab keeps the state it started with: a state it did not
 * start is refused, so that nobody can send a visitor a link that signs them in to another account.
 */

/** the providers in the order they are shown, with their name */
export const PROVIDERS: { id: OAuthProvider; name: string }[] = [
  { id: 'google', name: 'Google' },
  { id: 'discord', name: 'Discord' },
]

export function providerName(provider: OAuthProvider): string {
  return PROVIDERS.find((known) => known.id === provider)?.name ?? provider
}

export function isProvider(value: unknown): value is OAuthProvider {
  return PROVIDERS.some((known) => known.id === value)
}

/**
 * The providers configured on the server that the client knows, in their order; none when the API cannot tell.
 */
export async function enabledProviders(): Promise<OAuthProvider[]> {
  try {
    const enabled = await fetchOAuthProviders()

    return PROVIDERS.map((known) => known.id).filter((id) => enabled.includes(id))
  } catch {
    return []
  }
}

/** what the tab remembers while the visitor is at the provider */
interface PendingFlow {
  provider: OAuthProvider
  purpose: OAuthPurpose
  state: string
}

const KEY = 'kyuden.oauth'

function savePending(pending: PendingFlow | null): void {
  try {
    if (pending === null) {
      sessionStorage.removeItem(KEY)
    } else {
      sessionStorage.setItem(KEY, JSON.stringify(pending))
    }
  } catch {
    // the tab keeps nothing (private window): the flow cannot be completed
  }
}

function takePending(): PendingFlow | null {
  try {
    const value = sessionStorage.getItem(KEY)
    sessionStorage.removeItem(KEY)

    return value === null ? null : (JSON.parse(value) as PendingFlow)
  } catch {
    return null
  }
}

/**
 * Why the visitor went to the provider, while the tab remembers it.
 */
export function pendingPurpose(): OAuthPurpose | null {
  try {
    const value = sessionStorage.getItem(KEY)

    return value === null ? null : (JSON.parse(value) as PendingFlow).purpose
  } catch {
    return null
  }
}

/**
 * Sends the visitor to the provider: to sign in, or to link the account they are signed in to.
 */
export async function startOAuth(
  provider: OAuthProvider,
  purpose: OAuthPurpose,
  go: (url: string) => void = (url) => window.location.assign(url),
): Promise<void> {
  const token = purpose === 'link' ? (loadSession()?.token ?? null) : null
  const authorization = await createOAuthAuthorization(provider, purpose, token)
  savePending({ provider, purpose, state: authorization.state })
  go(authorization.authorization_url)
}

/**
 * How a flow ended: signed in or linked; no account linked to the identity (signing in); the identity linked to
 * another account (linking); anything else failed (a state this tab did not start, the visitor refused at the
 * provider, the provider out of reach…).
 */
export type OAuthOutcome = 'signed_in' | 'linked' | 'not_linked' | 'linked_elsewhere' | 'failed'

export interface OAuthResult {
  purpose: OAuthPurpose
  outcome: OAuthOutcome
}

/**
 * Completes the flow the provider sent the visitor back from, with the query of the callback (`code` and `state`, or
 * `error` when the visitor refused). The state is used once.
 */
export async function completeOAuth(
  provider: OAuthProvider,
  query: { code?: unknown; state?: unknown; error?: unknown },
): Promise<OAuthResult> {
  const pending = takePending()
  const purpose = pending?.purpose ?? 'sign_in'
  const { code, state } = query
  if (
    pending === null ||
    pending.provider !== provider ||
    typeof state !== 'string' ||
    state !== pending.state ||
    typeof code !== 'string' ||
    code === '' ||
    query.error !== undefined
  ) {
    return { purpose, outcome: 'failed' }
  }

  try {
    if (purpose === 'sign_in') {
      await signInWithProvider(provider, code, state)

      return { purpose, outcome: 'signed_in' }
    }
    const token = loadSession()?.token
    if (token === undefined) {
      return { purpose, outcome: 'failed' }
    }
    await linkAccount(token, provider, code, state)

    return { purpose, outcome: 'linked' }
  } catch (problem) {
    if (problem instanceof ApiProblem && problem.type === 'not_linked') {
      return { purpose, outcome: 'not_linked' }
    }
    if (problem instanceof ApiProblem && problem.type === 'identity_already_linked') {
      return { purpose, outcome: 'linked_elsewhere' }
    }

    return { purpose, outcome: 'failed' }
  }
}
