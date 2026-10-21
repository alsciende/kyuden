import { describe, expect, it } from 'vitest'

import { ApiProblem } from '@/api/client'
import { formErrors, passwordProblem, usernameProblem } from '@/lib/credentials'

describe('usernameProblem', () => {
  it('accepts 3 to 32 letters, digits or underscores', () => {
    expect(usernameProblem('hida_takeshi')).toBeNull()
    expect(usernameProblem('abc')).toBeNull()
    expect(usernameProblem('a'.repeat(32))).toBeNull()
  })

  it('refuses anything else', () => {
    expect(usernameProblem('ab')).not.toBeNull()
    expect(usernameProblem('a'.repeat(33))).not.toBeNull()
    expect(usernameProblem('hida takeshi')).not.toBeNull()
    expect(usernameProblem('hida-takeshi')).not.toBeNull()
    expect(usernameProblem('hidā')).not.toBeNull()
  })
})

describe('passwordProblem', () => {
  it('counts characters, not code units', () => {
    expect(passwordProblem('12345678')).toBeNull()
    expect(passwordProblem('1234567')).not.toBeNull()
    // 4 characters, 8 UTF-16 code units
    expect(passwordProblem('🀄🀄🀄🀄')).not.toBeNull()
    expect(passwordProblem('🀄🀄🀄🀄🀄🀄🀄🀄')).toBeNull()
  })
})

describe('formErrors', () => {
  it('sends the violations to their field', () => {
    const problem = new ApiProblem(422, 'validation_failed', 'Invalid.', [
      { property_path: 'username', message: 'This username is taken.' },
      { property_path: 'other', message: 'Something else.' },
    ])

    expect(formErrors(problem)).toEqual({ username: 'This username is taken.', form: 'Something else.' })
  })

  it('gives the detail of a problem without violations to the form', () => {
    expect(formErrors(new ApiProblem(409, 'already_registered', 'You already have an account.'))).toEqual({
      form: 'You already have an account.',
    })
  })
})
