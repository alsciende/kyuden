// @vitest-environment node
import { readFile } from 'node:fs/promises'

import { describe, expect, it } from 'vitest'

import { generateMessageTypes, OUTPUT_FILE } from '../../../scripts/messageTypes'

describe('messages', () => {
  it('match the JSON schemas of the messages: after changing them, run npm run messages', async () => {
    expect(await readFile(OUTPUT_FILE, 'utf8')).toBe(await generateMessageTypes())
  })
})
