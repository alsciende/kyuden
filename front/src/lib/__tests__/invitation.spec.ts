import { afterEach, describe, expect, it } from 'vitest'

import { invitationLink, keepBotKey, keptBotKey, mcpConfiguration, timeLeft } from '@/lib/invitation'

describe('invitation', () => {
  afterEach(() => sessionStorage.clear())

  it('configures an MCP client with the key of the bot', () => {
    expect(JSON.parse(mcpConfiguration('https://kyuden.app', 'l5r_key'))).toEqual({
      mcpServers: {
        kyuden: {
          type: 'http',
          url: 'https://kyuden.app/api/mcp',
          headers: { Authorization: 'Bearer l5r_key' },
        },
      },
    })
  })

  it('links to the invitation of a friend', () => {
    expect(invitationLink('https://kyuden.app', 'KX7-QM4-T9P')).toBe('https://kyuden.app/join/KX7-QM4-T9P')
  })

  it('keeps the key of the bot of the invitation only', () => {
    keepBotKey('invitation-1', 'l5r_key')

    expect(keptBotKey('invitation-1')).toBe('l5r_key')
    expect(keptBotKey('invitation-2')).toBeNull()
  })

  it('tells how long an invitation still waits', () => {
    const now = Date.parse('2026-10-27T10:00:00Z')

    expect(timeLeft('2026-10-28T09:58:00Z', now)).toBe('23 h 58 min')
    expect(timeLeft('2026-10-27T11:05:00Z', now)).toBe('1 h 05 min')
    expect(timeLeft('2026-10-27T10:05:07Z', now)).toBe('5 min')
    expect(timeLeft('2026-10-27T09:00:00Z', now)).toBe('less than a minute')
  })
})
