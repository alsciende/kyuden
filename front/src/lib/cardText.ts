import type { AbilityTiming } from '@/api/client'
import type { ConflictType, Element } from '@/api/game'
import { findClan } from '@/data/clans'
import { CONFLICT_ICONS, ELEMENT_ICONS, ELEMENT_NAMES } from '@/lib/icons'

/**
 * The text of a card as printed, read from the markup of the card database (`<b>`, `<i>`, `<em>`, `<br>` and the
 * symbols in brackets, `[conflict-military]`), into paragraphs drawn without `v-html`. A paragraph knows the timings of
 * the abilities it holds (its bold `Action:`, `Reaction:`, `Interrupt:`, forced or not, those granted in quotes
 * included), so that the ability being resolved stands out.
 */

export type TextSegment =
  /** `timing`: the bold timing of an ability, e.g. `Action:` */
  | { kind: 'text'; text: string; bold: boolean; italic: boolean; timing: boolean }
  /** a glyph of the icon font */
  | { kind: 'icon'; glyph: string; label: string }
  /** the mon of a clan */
  | { kind: 'clan'; mon: string; label: string }

export interface TextParagraph {
  segments: TextSegment[]
  timings: AbilityTiming[]
}

const TOKEN = /(<\/?(?:b|i|em)>|\[[a-z]+-[a-z]+\])/
const TIMING = /^\s*(?:forced\s+)?(action|reaction|interrupt)\b/i

function symbol(name: string): TextSegment | null {
  const [family, value] = name.split('-') as [string, string]
  if (family === 'conflict' && value in CONFLICT_ICONS) {
    return { kind: 'icon', glyph: CONFLICT_ICONS[value as ConflictType], label: value }
  }
  if (family === 'element' && value in ELEMENT_ICONS) {
    return { kind: 'icon', glyph: ELEMENT_ICONS[value as Element], label: ELEMENT_NAMES[value as Element] }
  }
  const clan = family === 'clan' ? findClan(value) : undefined
  if (clan !== undefined) {
    return { kind: 'clan', mon: clan.mon, label: clan.name }
  }

  return null
}

function parseParagraph(markup: string): TextParagraph {
  const segments: TextSegment[] = []
  const timings: AbilityTiming[] = []
  let bold = false
  let italic = false
  for (const token of markup.split(TOKEN)) {
    if (token === '') {
      continue
    }
    const tag = /^<(\/?)(b|i|em)>$/.exec(token)
    if (tag !== null) {
      if (tag[2] === 'b') {
        bold = tag[1] === ''
      } else {
        italic = tag[1] === ''
      }
      continue
    }
    const icon = /^\[([a-z]+-[a-z]+)\]$/.exec(token)
    const segment = icon === null ? null : symbol(icon[1]!)
    if (segment !== null) {
      segments.push(segment)
      continue
    }
    // any other markup is dropped: the text is shown as text
    const text = token.replace(/<[^>]*>/g, '')
    if (text === '') {
      continue
    }
    const timing = bold ? TIMING.exec(text) : null
    if (timing !== null) {
      timings.push(timing[1]!.toLowerCase() as AbilityTiming)
    }
    segments.push({ kind: 'text', text, bold, italic, timing: timing !== null })
  }

  return { segments, timings }
}

export function parseCardText(text: string | null | undefined): TextParagraph[] {
  return (text ?? '')
    .split(/<br\s*\/?>|\n/)
    .map(parseParagraph)
    .filter((paragraph) => paragraph.segments.length > 0)
}

/**
 * Whether each paragraph holds an ability of the timing: those that do not are dimmed, unless none does (the engine
 * does not check the timings of the abilities, which cards gain and lose).
 */
export function emphasized(paragraphs: TextParagraph[], timing: AbilityTiming): boolean[] {
  const matches = paragraphs.map((paragraph) => paragraph.timings.includes(timing))

  return matches.some(Boolean) ? matches : paragraphs.map(() => true)
}
