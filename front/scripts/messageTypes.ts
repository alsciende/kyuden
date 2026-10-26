import { readdir, readFile, writeFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'

import { compile, type JSONSchema } from 'json-schema-to-typescript'
import { format, resolveConfig } from 'prettier'

/**
 * The types of the payloads of the public messages, generated from their JSON schemas (`schemas/messages`, dumped by
 * `bin/console app:message-schemas:dump`): one interface per message, and `Message`, the union of the messages the
 * client sends (`type` and `payload`). `npm run messages` writes them to `src/api/messages.ts`; a test checks that
 * the file matches the schemas.
 */

export const SCHEMAS_DIR = fileURLToPath(new URL('../../schemas/messages/', import.meta.url))
export const OUTPUT_FILE = fileURLToPath(new URL('../src/api/messages.ts', import.meta.url))

const BANNER = `/**
 * Generated from the JSON schemas of the messages (schemas/messages) by \`npm run messages\`: do not edit.
 */`

export async function generateMessageTypes(schemasDir: string = SCHEMAS_DIR): Promise<string> {
  const files = (await readdir(schemasDir)).filter((file) => file.endsWith('.json')).sort()
  const declarations: string[] = []
  const entries: string[] = []
  for (const file of files) {
    const schema = JSON.parse(await readFile(`${schemasDir}/${file}`, 'utf8')) as JSONSchema & { title: string }
    const declaration = await compile(schema, schema.title, { bannerComment: '', format: false, ignoreMinAndMaxItems: true })
    // a message without payload: an empty object, not any value
    declarations.push(declaration.replace(/export interface (\w+) \{\s*\}/, 'export type $1 = Record<string, never>'))
    entries.push(`  ${schema.title}: ${typeName(schema.title)}`)
  }
  const source = [
    BANNER,
    ...declarations,
    `/** the payload of each type of message */\nexport interface MessagePayloads {\n${entries.join('\n')}\n}`,
    'export type MessageType = keyof MessagePayloads',
    '/** a message the player sends: its type and its payload */\nexport type Message = { [T in MessageType]: { type: T; payload: MessagePayloads[T] } }[MessageType]',
  ].join('\n\n')

  return format(source, { ...(await resolveConfig(OUTPUT_FILE)), parser: 'typescript' })
}

/** the name json-schema-to-typescript gives the interface of a title: bid_honor → BidHonor */
function typeName(title: string): string {
  return title.replace(/(?:^|_)([a-z])/g, (_, letter: string) => letter.toUpperCase())
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await writeFile(OUTPUT_FILE, await generateMessageTypes())
}
