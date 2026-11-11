import type { RouteLocationRaw } from 'vue-router'

/**
 * Where a visitor goes once signed in or registered (`?next=`): a page of the site only, never another origin.
 */
export function nextPage(value: unknown): string | null {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : null
}

/** The screen of a game that cannot be shown to the visitor. */
export interface GameProblem {
  title: string
  text: string | null
  /** to sign in, then come back to the game */
  signIn: RouteLocationRaw | null
  /** to create an account (a guest keeps their name and games), then come back to the game */
  signUp: RouteLocationRaw | null
}

/**
 * Why a game is not shown: a visitor without a session (a link to watch it, from Discord) signs in or creates an
 * account, a guest creates one (only an account watches the games of others), and both come back to the game.
 */
export function gameProblem(status: number, detail: string, gameId: string, guest: boolean): GameProblem {
  const back = { next: `/games/${gameId}` }
  switch (status) {
    case 401:
      return {
        title: 'Sign in to watch this game',
        text: 'Every game at court can be watched by the accounts of the site.',
        signIn: { name: 'login', query: back },
        signUp: { name: 'signup', query: back },
      }
    case 403:
      return guest
        ? {
            title: 'Create an account to watch this game',
            text: 'Only an account watches the games of others. You keep your name at court and your games.',
            signIn: null,
            signUp: { name: 'signup', query: back },
          }
        : { title: 'Only an account watches the games of others.', text: null, signIn: null, signUp: null }
    case 404:
      return { title: 'There is no such game.', text: null, signIn: null, signUp: null }
    default:
      return { title: detail, text: null, signIn: null, signUp: null }
  }
}
