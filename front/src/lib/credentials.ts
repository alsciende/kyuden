import { ApiProblem } from '@/api/client'

/**
 * The rules of the credentials of an account, as the API checks them: told before sending, so that a mistake is seen
 * at once.
 */
export const USERNAME_PATTERN = /^[A-Za-z0-9_]{3,32}$/
export const MIN_PASSWORD_LENGTH = 8

export function usernameProblem(username: string): string | null {
  return USERNAME_PATTERN.test(username) ? null : 'Use 3 to 32 letters, digits or _.'
}

export function passwordProblem(password: string): string | null {
  // characters, not UTF-16 code units: the API counts them so
  return [...password].length >= MIN_PASSWORD_LENGTH ? null : `Use at least ${MIN_PASSWORD_LENGTH} characters.`
}

export interface FormErrors {
  username?: string
  password?: string
  name?: string
  form?: string
}

/**
 * The errors of a form from a problem of the API: the violations go to their field, the rest to the form.
 */
export function formErrors(error: unknown): FormErrors {
  if (!(error instanceof ApiProblem)) {
    return { form: 'The court cannot be reached. Try again in a moment.' }
  }
  const errors: FormErrors = {}
  for (const violation of error.violations) {
    const field = violation.property_path
    if (field === 'username' || field === 'password' || field === 'name') {
      errors[field] ??= violation.message
    } else {
      errors.form ??= violation.message
    }
  }
  if (Object.keys(errors).length === 0) {
    errors.form = error.detail
  }

  return errors
}
