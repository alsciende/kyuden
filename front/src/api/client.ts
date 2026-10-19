/**
 * The HTTP API of the engine, on the same origin (nginx sends /api to Symfony). Errors are problem details
 * (RFC 9457), thrown as ApiProblem.
 */

export interface Violation {
  property_path: string
  message: string
}

export class ApiProblem extends Error {
  constructor(
    public readonly status: number,
    public readonly type: string,
    public readonly detail: string,
    public readonly violations: Violation[] = [],
  ) {
    super(detail)
    this.name = 'ApiProblem'
  }
}

export interface Card {
  code: string
  name: string
  type: string
  clan: string | null
  image_url: string | null
}

export interface Guest {
  user_id: string
  name: string
  token: string
}

export interface BotGame {
  game_id: string
  player_id: string
  opponent: {
    user_id: string
    name: string
    deck_id: string
  }
}

interface RequestOptions {
  method?: string
  body?: unknown
  token?: string | null
}

export async function request<T>(path: string, { method = 'GET', body, token }: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  const response = await fetch(path, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  if (!response.ok) {
    throw await toProblem(response)
  }

  return (response.status === 204 ? undefined : await response.json()) as T
}

async function toProblem(response: Response): Promise<ApiProblem> {
  try {
    const problem = (await response.json()) as Partial<{ type: string; detail: string; violations: Violation[] }>

    return new ApiProblem(
      response.status,
      problem.type ?? 'unknown',
      problem.detail ?? response.statusText,
      problem.violations ?? [],
    )
  } catch {
    return new ApiProblem(response.status, 'unknown', response.statusText)
  }
}

const cards = new Map<string, Promise<Card>>()

/**
 * A card as printed, read once per page (the API is cacheable).
 */
export function fetchCard(code: string): Promise<Card> {
  let card = cards.get(code)
  if (card === undefined) {
    card = request<Card>(`/api/cards/${encodeURIComponent(code)}`)
    card.catch(() => cards.delete(code))
    cards.set(code, card)
  }

  return card
}

export function createGuest(name: string): Promise<Guest> {
  return request<Guest>('/api/guests', { method: 'POST', body: { name } })
}

export function createBotGame(token: string, deckId: string): Promise<BotGame> {
  return request<BotGame>('/api/bot-games', { method: 'POST', body: { deck_id: deckId }, token })
}
