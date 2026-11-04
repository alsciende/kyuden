import type { GameView, StackView } from '@/api/game'
import { locate, type Cards } from '@/lib/board'

/**
 * The special actions: the effects few cards have (put a card on its deck, initiate a duel, switch a ring…), offered
 * apart from the others, behind a "+", so that they do not crowd what is used in every game. The view lists them with
 * the cards of the viewer whose text needs them: those are offered first, with the cards they are for, and the others
 * on demand, for a card the engine does not know about.
 */

/** where a card needing a special action is, for the viewer, its controller */
const WHERE: Partial<Record<StackView['type'], string>> = {
  in_play: 'in play',
  hand: 'in your hand',
  regular_province: 'in a province',
  stronghold_province: 'in your stronghold province',
  role: 'your role',
  conflict_discard_pile: 'in your conflict discard pile',
  dynasty_discard_pile: 'in your dynasty discard pile',
}

/** a card of the viewer needing a special action */
export interface Source {
  id: string
  name: string
  /** where it is: in play, in your hand… */
  where: string
}

export interface SpecialActions {
  /** whether a type of message is special */
  isSpecial(type: string): boolean
  /** whether a card of the viewer needs it */
  isNeeded(type: string): boolean
  /** the cards of the viewer that need it */
  sourcesOf(type: string): Source[]
}

export function specialActions(game: GameView, cards: Cards): SpecialActions {
  const byType = new Map(game.special_actions.map((action) => [action.type, action.card_ids]))
  const sourcesOf = (type: string): Source[] =>
    (byType.get(type) ?? []).flatMap((id) => {
      const located = locate(game, id)
      if (located === undefined) {
        return []
      }
      const code = located.card.code

      return [
        {
          id,
          name: (code !== null && cards[code]?.name) || 'A card',
          where: WHERE[located.stack.type] ?? '',
        },
      ]
    })

  return {
    isSpecial: (type) => byType.has(type),
    isNeeded: (type) => (byType.get(type)?.length ?? 0) > 0,
    sourcesOf,
  }
}

/** the special actions of one type, with the cards of the viewer that need them */
export interface SpecialGroup<T> {
  type: string
  items: T[]
  /** a form of the screen offers it too (choose a target…) */
  form: boolean
  /** the cards needing it: "Diplomatic Gift-Giver, in play" */
  sources: string
}

/**
 * The special actions of a list grouped by type, in their order, with the types offered by a form of the screen:
 * those the cards of the viewer need, then the others; the basic actions are left out.
 */
export function groupSpecial<T extends { type: string }>(
  specials: SpecialActions,
  items: T[],
  forms: string[] = [],
): { needed: SpecialGroup<T>[]; others: SpecialGroup<T>[] } {
  const byType = new Map<string, SpecialGroup<T>>()
  const group = (type: string): SpecialGroup<T> => {
    let found = byType.get(type)
    if (found === undefined) {
      found = { type, items: [], form: false, sources: sourcesLabel(specials, [type]) }
      byType.set(type, found)
    }

    return found
  }
  items.filter((item) => specials.isSpecial(item.type)).forEach((item) => group(item.type).items.push(item))
  forms.filter((type) => specials.isSpecial(type)).forEach((type) => (group(type).form = true))
  const all = [...byType.values()]

  return {
    needed: all.filter((candidate) => specials.isNeeded(candidate.type)),
    others: all.filter((candidate) => !specials.isNeeded(candidate.type)),
  }
}

/** the number of special actions the cards of the viewer need, the badge of the "+": a form counts as one */
export function neededCount<T>(groups: SpecialGroup<T>[]): number {
  return groups.reduce((count, group) => count + group.items.length + (group.form ? 1 : 0), 0)
}

/** the cards needing the special actions of a list, without repeating a card: "Diplomatic Gift-Giver, in play" */
export function sourcesLabel(specials: SpecialActions, types: string[]): string {
  const seen = new Set<string>()
  const labels: string[] = []
  for (const type of new Set(types)) {
    for (const source of specials.sourcesOf(type)) {
      if (!seen.has(source.id)) {
        seen.add(source.id)
        labels.push(source.where ? `${source.name}, ${source.where}` : source.name)
      }
    }
  }

  return labels.join(' · ')
}
