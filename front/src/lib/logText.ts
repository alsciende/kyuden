import type { GameView, LogEntry, LogParam, StackType } from '@/api/game'
import type { Cards } from '@/lib/board'
import { ELEMENT_NAMES } from '@/lib/icons'

/**
 * The text of an entry of the log: its template with its params, as the viewer knows them. The players are named
 * from the point of view of the viewer (you, the opponent).
 */

const STACK_NAMES: Record<StackType, string> = {
  conflict_draw_deck: 'conflict deck',
  conflict_discard_pile: 'conflict discard pile',
  dynasty_draw_deck: 'dynasty deck',
  dynasty_discard_pile: 'dynasty discard pile',
  stronghold_province: 'stronghold province',
  role: 'role',
  hand: 'hand',
  regular_province: 'province',
  in_play: 'play',
}

export function paramText(param: LogParam, game: GameView, cards: Cards): string {
  switch (param.type) {
    case 'player':
      return param.id === game.viewer_id ? 'You' : 'Opp.'
    case 'card':
      if (param.code !== null) {
        return cards[param.code]?.name ?? 'a card'
      }

      return 'a hidden card'
    case 'stack': {
      const owner = param.player_id === game.viewer_id ? 'your' : "the opponent's"

      return param.stack_type === 'in_play' ? 'play' : `${owner} ${STACK_NAMES[param.stack_type]}`
    }
    case 'ring':
      return `the ${ELEMENT_NAMES[param.element]} ring`
    case 'value':
      return param.hidden ? '?' : String(param.value ?? '')
    case 'list':
      return param.items.map((item) => paramText(item, game, cards)).join(', ') || 'nothing'
  }
}

/** the verb of a template written in the third person, for "you" */
function secondPerson(verb: string): string {
  if (verb === 'is') {
    return 'are'
  }
  if (verb === 'has') {
    return 'have'
  }
  if (/(ss|sh|ch|x)es$/.test(verb)) {
    return verb.slice(0, -2)
  }

  return verb.endsWith('s') ? verb.slice(0, -1) : verb
}

/** a piece of the text of an entry: a card names its code, so that its image can be shown */
export interface TextPart {
  text: string
  code: string | null
}

function paramParts(param: LogParam, game: GameView, cards: Cards): TextPart[] {
  if (param.type === 'card' && param.code !== null) {
    return [{ text: paramText(param, game, cards), code: param.code }]
  }
  if (param.type === 'list' && param.items.length > 0) {
    return param.items.flatMap((item, index) => [
      ...(index > 0 ? [{ text: ', ', code: null }] : []),
      ...paramParts(item, game, cards),
    ])
  }

  return [{ text: paramText(param, game, cards), code: null }]
}

/** the text of an entry, in pieces: the cards apart, the rest merged */
export function entryParts(entry: LogEntry, game: GameView, cards: Cards): TextPart[] {
  const params = Array.isArray(entry.params) ? {} : entry.params
  let template = entry.template

  // the templates are written in the third person: "{player} passes" reads "You pass" for the viewer
  const subject = /^\{(\w+)\} (\w+)/.exec(template)
  const subjectParam = subject ? params[subject[1]!] : undefined
  if (subject && subjectParam?.type === 'player' && subjectParam.id === game.viewer_id) {
    template = `{${subject[1]}} ${secondPerson(subject[2]!)}${template.slice(subject[0].length)}`.replace(
      /\btheir\b/g,
      'your',
    )
  }

  const parts: TextPart[] = []
  const push = (part: TextPart) => {
    const last = parts.at(-1)
    if (part.code === null && last !== undefined && last.code === null) {
      last.text += part.text
    } else if (part.text !== '') {
      parts.push({ ...part })
    }
  }
  let index = 0
  for (const match of template.matchAll(/\{(\w+)\}/g)) {
    push({ text: template.slice(index, match.index), code: null })
    const param = params[match[1]!]
    if (param === undefined) {
      push({ text: match[0], code: null })
    } else {
      paramParts(param, game, cards).forEach(push)
    }
    index = match.index + match[0].length
  }
  push({ text: template.slice(index), code: null })

  return parts
}

export function entryText(entry: LogEntry, game: GameView, cards: Cards): string {
  return entryParts(entry, game, cards)
    .map((part) => part.text)
    .join('')
}
