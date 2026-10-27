/**
 * The invitations to a friendly game: what the host gives their bot (the configuration of an MCP client with the key of
 * the invitation, and a prompt of one line: the rest comes from the server) or their friend (a link).
 */

/** what the bot is asked: how to play is told by the MCP server */
export const BOT_PROMPT = 'Find your game on Kyūden and play it to the end.'

/**
 * The configuration of an MCP client (Claude Code, Claude Desktop…) to play as the bot of the invitation.
 */
export function mcpConfiguration(origin: string, token: string): string {
  return JSON.stringify(
    {
      mcpServers: {
        kyuden: {
          type: 'http',
          url: `${origin}/api/mcp`,
          headers: { Authorization: `Bearer ${token}` },
        },
      },
    },
    null,
    2,
  )
}

/**
 * The link a friend opens to use the invitation.
 */
export function invitationLink(origin: string, code: string): string {
  return `${origin}/join/${code}`
}

const KEY = 'kyuden.invitation-key'

/**
 * The key of the bot is sent once, when the invitation is created: the tab keeps it, so that reloading the page shows
 * it again.
 */
export function keepBotKey(invitationId: string, token: string): void {
  try {
    sessionStorage.setItem(KEY, JSON.stringify({ invitationId, token }))
  } catch {
    // the key is shown until the page is left
  }
}

export function keptBotKey(invitationId: string): string | null {
  try {
    const value = sessionStorage.getItem(KEY)
    const kept = value === null ? null : (JSON.parse(value) as { invitationId?: string; token?: string })

    return kept?.invitationId === invitationId && typeof kept.token === 'string' ? kept.token : null
  } catch {
    return null
  }
}

/**
 * How long an invitation still waits (`23 h 58 min`, `5 min`, `less than a minute`), from its end and the time now.
 */
export function timeLeft(expiresAt: string, now: number): string {
  const minutes = Math.max(0, Math.floor((Date.parse(expiresAt) - now) / 60_000))
  if (minutes === 0) {
    return 'less than a minute'
  }

  return minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)} h ${String(minutes % 60).padStart(2, '0')} min`
}
