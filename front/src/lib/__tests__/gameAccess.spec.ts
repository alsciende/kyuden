import { describe, expect, it } from 'vitest'

import { gameProblem, nextPage } from '@/lib/gameAccess'

describe('nextPage', () => {
  it('keeps a page of the site only', () => {
    expect(nextPage('/games/g1')).toBe('/games/g1')
    expect(nextPage('//evil.example/x')).toBeNull()
    expect(nextPage('https://evil.example')).toBeNull()
    expect(nextPage(['/games/g1'])).toBeNull()
    expect(nextPage(undefined)).toBeNull()
  })
})

describe('gameProblem', () => {
  it('leads a visitor without a session to sign in or create an account, then back to the game', () => {
    const problem = gameProblem(401, 'Unauthorized', 'g1', false)

    expect(problem.title).toBe('Sign in to watch this game')
    expect(problem.signIn).toEqual({ name: 'login', query: { next: '/games/g1' } })
    expect(problem.signUp).toEqual({ name: 'signup', query: { next: '/games/g1' } })
  })

  it('leads a guest to create an account only', () => {
    const problem = gameProblem(403, 'Forbidden', 'g1', true)

    expect(problem.title).toBe('Create an account to watch this game')
    expect(problem.signIn).toBeNull()
    expect(problem.signUp).toEqual({ name: 'signup', query: { next: '/games/g1' } })
  })

  it('tells the other problems as they are', () => {
    expect(gameProblem(403, 'Forbidden', 'g1', false).signUp).toBeNull()
    expect(gameProblem(404, 'Not found', 'g1', false).title).toBe('There is no such game.')
    expect(gameProblem(500, 'Something broke.', 'g1', false).title).toBe('Something broke.')
  })
})
