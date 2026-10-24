import type { Card } from '@/api/client'
import type { CardView, ConflictType, Element, GameView, PlayerView, StackType, StackView } from '@/api/game'

/**
 * Reading the view of a game: the players, their provinces and characters, the step of the round, the rings, and the
 * totals the players declare (prefilled from the cards: the engine does not apply card effects).
 */

/** the cards as printed, by code */
export type Cards = Record<string, Card>

export const ELEMENTS: Element[] = ['air', 'earth', 'fire', 'water', 'void']

export function viewer(game: GameView): PlayerView {
  return game.players.find((player) => player.id === game.viewer_id)!
}

export function opponent(game: GameView): PlayerView | undefined {
  return game.players.find((player) => player.id !== game.viewer_id)
}

export function playerById(game: GameView, id: string | null): PlayerView | undefined {
  return game.players.find((player) => player.id === id)
}

export function stacksOf(player: PlayerView, type: StackType): StackView[] {
  return player.stacks.filter((stack) => stack.type === type)
}

export function stackOf(player: PlayerView, type: StackType): StackView | undefined {
  return player.stacks.find((stack) => stack.type === type)
}

/** the cards of a stack, from the bottom to the top (none when the viewer only sees their number) */
export function cardsOf(stack: StackView | undefined): CardView[] {
  return stack?.cards ?? []
}

export interface Province {
  stack: StackView
  /** the province card, at the bottom of its stack */
  province: CardView | null
  /** the dynasty cards on the province (the stronghold on the stronghold province) */
  cards: CardView[]
  /** the stronghold, on the stronghold province */
  stronghold: CardView | null
  broken: boolean
}

/**
 * The provinces of a player, the regular ones first and the stronghold province last. In the stronghold province
 * stack, the stronghold is at the bottom and the province above it.
 */
export function provinces(player: PlayerView): Province[] {
  const regular = stacksOf(player, 'regular_province').map((stack): Province => {
    const [province = null, ...cards] = cardsOf(stack)

    return { stack, province, cards, stronghold: null, broken: province?.orientation === 'upside_down' }
  })
  const strongholdStack = stackOf(player, 'stronghold_province')
  if (strongholdStack === undefined) {
    return regular
  }
  const [stronghold = null, province = null] = cardsOf(strongholdStack)

  return [
    ...regular,
    { stack: strongholdStack, province, cards: [], stronghold, broken: province?.orientation === 'upside_down' },
  ]
}

/** the provinces a player may put under their stronghold, during setup */
export function strongholdCandidates(player: PlayerView): CardView[] {
  return cardsOf(stackOf(player, 'stronghold_province')).slice(1)
}

export interface Character {
  stack: StackView
  card: CardView
  /** the attachments on the character, from the first attached */
  attachments: CardView[]
}

/** the characters in play of a player: each one has its own stack, the character at the bottom */
export function charactersInPlay(player: PlayerView): Character[] {
  return stacksOf(player, 'in_play')
    .filter((stack) => (stack.cards?.length ?? 0) > 0)
    .map((stack) => {
      const [card, ...attachments] = stack.cards!

      return { stack, card: card!, attachments }
    })
}

export interface Located {
  player: PlayerView
  stack: StackView
  card: CardView
  /** the card a card is attached to, or the province a dynasty card is on */
  under: CardView | null
}

export function locate(game: GameView, cardId: string): Located | undefined {
  for (const player of game.players) {
    for (const stack of player.stacks) {
      const cards = stack.cards ?? []
      const index = cards.findIndex((card) => card.id === cardId)
      if (index === -1) {
        continue
      }
      const under = (stack.type === 'in_play' || stack.type === 'regular_province') && index > 0 ? cards[0]! : null

      return { player, stack, card: cards[index]!, under }
    }
  }

  return undefined
}

export type Phase = 'setup' | 'dynasty' | 'draw' | 'conflict' | 'fate'

export const PHASES: { id: Phase; name: string }[] = [
  { id: 'dynasty', name: 'Dynasty' },
  { id: 'draw', name: 'Draw' },
  { id: 'conflict', name: 'Conflict' },
  { id: 'fate', name: 'Fate' },
]

export interface Step {
  phase: Phase | null
  title: string
  /** what the players do in this step, in a sentence or two */
  help: string
  /** how the player does it on the board, when it is with the cards */
  hint?: string
}

const ACTION_WINDOW_HINT = 'Click a card to play it or to use its ability.'

const STEPS: Record<string, Step> = {
  join_game: {
    phase: 'setup',
    title: 'Waiting for an opponent',
    help: 'The game starts once both players have joined.',
  },
  choose_stronghold: {
    phase: 'setup',
    title: 'Choose your stronghold province',
    help: 'Choose the province placed under your stronghold. The others are placed face down at random.',
    hint: 'Click the province to put under your stronghold.',
  },
  reveal_facedown_dynasty_cards: {
    phase: 'dynasty',
    title: 'Reveal your dynasty cards',
    help: 'Each player turns face up the dynasty cards in their provinces.',
    hint: 'Click each of your face-down dynasty cards to turn it face up.',
  },
  collect_fate: {
    phase: 'dynasty',
    title: 'Collect fate',
    help: 'Each player collects the fate value of their stronghold, in player order.',
  },
  play_cards_from_provinces: {
    phase: 'dynasty',
    title: 'Play cards from your provinces',
    help: 'Pay the cost of a character to bring it into play, or pass. The first player to pass gains 1 fate.',
    hint: 'Click a character in your provinces to play it: its province is refilled.',
  },
  honor_bid: {
    phase: 'draw',
    title: 'Bid honor',
    help: 'You draw as many cards as your bid. The higher bidder gives the difference in honor to the lower one.',
  },
  reveal_honor_dials: {
    phase: 'draw',
    title: 'Honor dials revealed',
    help: 'React to the revealed bids, then pass.',
  },
  draw_phase_action_window: {
    phase: 'draw',
    title: 'Draw phase actions',
    help: 'Play a card or trigger an ability, or pass. The window closes once both players pass in a row.',
    hint: ACTION_WINDOW_HINT,
  },
  conflict_phase_action_window: {
    phase: 'conflict',
    title: 'Conflict phase actions',
    help: 'Play a card or trigger an ability, or pass. A conflict may be declared once both players pass in a row.',
    hint: ACTION_WINDOW_HINT,
  },
  declare_conflict: {
    phase: 'conflict',
    title: 'Declare a conflict',
    help: 'Choose a type, a ring, a province and your attackers, then declare. You may pass this conflict instead.',
  },
  react_to_conflict_declaration: {
    phase: 'conflict',
    title: 'Conflict declared',
    help: 'React to the declaration of the conflict, then pass.',
  },
  declare_defenders: {
    phase: 'conflict',
    title: 'Declare defenders',
    help: 'Choose the ready characters defending the province, or declare no defenders.',
  },
  conflict_action_window: {
    phase: 'conflict',
    title: 'Conflict actions',
    help: 'Play a card or trigger an ability, or pass. The window closes once both players pass in a row.',
    hint: ACTION_WINDOW_HINT,
  },
  determine_result: {
    phase: 'conflict',
    title: 'Declare your skill',
    help: 'Each side declares its total skill, every modifier applied.',
  },
  react_to_conflict_result: {
    phase: 'conflict',
    title: 'Conflict result',
    help: 'React to the result of the conflict, then pass.',
  },
  react_to_broken_province: {
    phase: 'conflict',
    title: 'Province broken',
    help: 'React to the broken province, then pass.',
  },
  resolve_ring_effects: {
    phase: 'conflict',
    title: 'Ring effects',
    help: 'The attacker resolves the effect of the contested ring if they won, then passes.',
    hint: 'Apply the effect with the cards and players it concerns: click them.',
  },
  react_to_ring_claim: {
    phase: 'conflict',
    title: 'Ring claimed',
    help: 'React to the ring being claimed, then pass.',
  },
  glory_count: {
    phase: 'conflict',
    title: 'Count your glory',
    help: 'The glory of your ready characters, plus one for each ring you claimed.',
  },
  claim_imperial_favor: {
    phase: 'conflict',
    title: 'Claim the Imperial Favor',
    help: 'The winner of the glory count claims the Imperial Favor, on its military or political side.',
  },
  fate_phase_action_window: {
    phase: 'fate',
    title: 'Fate phase actions',
    help: 'Play a card or trigger an ability, or pass. The window closes once both players pass in a row.',
    hint: ACTION_WINDOW_HINT,
  },
  discard_from_provinces: {
    phase: 'fate',
    title: 'Discard from your provinces',
    help: 'Discard the face up dynasty cards you do not want, then pass. Your provinces are refilled.',
    hint: 'Click a dynasty card to discard it.',
  },
  game_ended: { phase: null, title: 'The game is over', help: '' },
}

/** the steps where the players have opportunities to act and pass them (the `opportunities` places of the engine) */
const OPPORTUNITY_PLACES = [
  'play_cards_from_provinces',
  'reveal_honor_dials',
  'draw_phase_action_window',
  'conflict_phase_action_window',
  'conflict_action_window',
  'resolve_ring_effects',
  'fate_phase_action_window',
  'discard_from_provinces',
  'react_to_conflict_declaration',
  'react_to_conflict_result',
  'react_to_broken_province',
  'react_to_ring_claim',
]

/** the steps of a conflict once its result is determined, until it ends */
const RESULT_PLACES = [
  'react_to_conflict_result',
  'apply_unopposed',
  'break_province',
  'react_to_broken_province',
  'resolve_ring_effects',
  'claim_ring',
  'react_to_ring_claim',
  'return_home',
  'conflict_ends',
]

/** whether the result of the conflict being resolved is determined */
export function conflictResolved(game: GameView): boolean {
  return game.conflict !== null && RESULT_PLACES.includes(game.place ?? '')
}

export function gameEnded(game: GameView): boolean {
  return game.place === 'game_ended' || game.winner_id !== null
}

/** how the game was won, from the point of view of the viewer */
export function victoryText(game: GameView): string {
  const won = game.winner_id === game.viewer_id
  switch (game.victory_condition) {
    case 'honor_win':
      return won ? 'You reached 25 honor.' : 'The opponent reached 25 honor.'
    case 'honor_loss':
      return won ? 'The opponent has no honor left.' : 'You have no honor left.'
    case 'stronghold_province_broken':
      return won ? "You broke the opponent's stronghold province." : 'The opponent broke your stronghold province.'
    case 'concession':
      return won ? 'The opponent conceded.' : 'You conceded.'
    default:
      return ''
  }
}

/** whether a player has passed in the current step (the flag of an earlier step is not reset until the next one) */
export function hasPassed(game: GameView, player: PlayerView): boolean {
  return player.passed && OPPORTUNITY_PLACES.includes(game.place ?? '')
}

const PHASE_PREFIXES: [string, Phase][] = [
  ['dynasty', 'dynasty'],
  ['draw', 'draw'],
  ['conflict', 'conflict'],
  ['fate', 'fate'],
]

/**
 * The step of the round a game place stands for. Places the players do not act in are passed through at once: they
 * only get a phase.
 */
export function stepOf(place: string | null): Step {
  if (place !== null && place in STEPS) {
    return STEPS[place]!
  }
  const phase = PHASE_PREFIXES.find(([prefix]) => place?.includes(prefix))?.[1] ?? null

  return { phase, title: place === null ? '' : capitalize(place.replaceAll('_', ' ')), help: '' }
}

function capitalize(text: string): string {
  return text.charAt(0).toUpperCase() + text.slice(1)
}

export type RingStatus = 'unclaimed' | 'contested' | 'yours' | 'theirs'

export interface Ring {
  element: Element
  fate: number
  status: RingStatus
}

export function rings(game: GameView): Ring[] {
  return ELEMENTS.flatMap((element) => {
    const ring = game.rings[element]
    if (ring === undefined) {
      return []
    }
    let status: RingStatus = 'unclaimed'
    if (game.conflict?.ring === element) {
      status = 'contested'
    } else if (ring.claimed_by_id !== null) {
      status = ring.claimed_by_id === game.viewer_id ? 'yours' : 'theirs'
    }

    return [{ element, fate: ring.fate, status }]
  })
}

/** the conflict types a player may still declare this round */
export function conflictsLeft(player: PlayerView): ConflictType[] {
  if (player.conflict_opportunities <= 0) {
    return []
  }

  return (['military', 'political'] as const).filter((type) => !player.declared_conflict_types.includes(type))
}

/** a printed value: a number, or 0 for a dash or an X */
export function printed(value: string | number | null | undefined): number {
  const number = typeof value === 'number' ? value : Number.parseInt(value ?? '', 10)

  return Number.isNaN(number) ? 0 : number
}

/** whether a character has a skill of that type at all (a dash is no skill) */
export function hasSkill(card: Card | undefined, type: ConflictType): boolean {
  const value = card?.[type]

  return value !== null && value !== undefined && value !== '-'
}

/**
 * The current skill of a character: the one recorded by the players, else the printed skill with the bonuses of its
 * attachments and its personal honor (glory added when honored, subtracted when dishonored), at least 0.
 */
export function skillOf(character: Pick<Character, 'card' | 'attachments'>, cards: Cards, type: ConflictType): number {
  const recorded = type === 'military' ? character.card.military_skill : character.card.political_skill
  if (recorded !== null) {
    return recorded
  }
  const card = character.card.code ? cards[character.card.code] : undefined
  if (!hasSkill(card, type)) {
    return 0
  }
  let skill = printed(card![type])
  for (const attachment of character.attachments) {
    const attached = attachment.code ? cards[attachment.code] : undefined
    skill += printed(type === 'military' ? attached?.military_bonus : attached?.political_bonus)
  }
  const glory = printed(card!.glory)
  if (character.card.personal_honor === 'honored') {
    skill += glory
  } else if (character.card.personal_honor === 'dishonored') {
    skill -= glory
  }

  return Math.max(0, skill)
}

export interface ShownSkill {
  /** null for a character without that skill (a dash) */
  value: number | null
  /** recorded by a player (`SetCharacterSkill`), else computed by the client from the cards */
  recorded: boolean
}

/** the skill shown on a character: the one recorded by the players, else the one computed from the cards */
export function shownSkill(
  character: Pick<Character, 'card' | 'attachments'>,
  cards: Cards,
  type: ConflictType,
): ShownSkill {
  const recorded = type === 'military' ? character.card.military_skill : character.card.political_skill
  if (recorded !== null) {
    return { value: recorded, recorded: true }
  }
  const card = character.card.code ? cards[character.card.code] : undefined

  return { value: hasSkill(card, type) ? skillOf(character, cards, type) : null, recorded: false }
}

/** the total skill of the participating characters among `ids` */
export function sideSkill(game: GameView, cards: Cards, ids: string[], type: ConflictType): number {
  return game.players
    .flatMap(charactersInPlay)
    .filter((character) => ids.includes(character.card.id) && character.card.orientation !== 'sideways')
    .reduce((total, character) => total + skillOf(character, cards, type), 0)
}

/** the fate value of the stronghold of a player */
export function strongholdFate(player: PlayerView, cards: Cards): number {
  const stronghold = cardsOf(stackOf(player, 'stronghold_province'))[0]

  return printed(stronghold?.code ? cards[stronghold.code]?.fate : null)
}

/** the strength of a province: printed, plus the bonus of the stronghold on the stronghold province */
export function provinceStrength(game: GameView, cards: Cards, provinceId: string): number {
  const located = locate(game, provinceId)
  if (located === undefined) {
    return 0
  }
  let strength = printed(located.card.code ? cards[located.card.code]?.strength : null)
  if (located.stack.type === 'stronghold_province') {
    const stronghold = cardsOf(located.stack)[0]
    strength += printed(stronghold?.code ? cards[stronghold.code]?.strength_bonus : null)
  }

  return strength
}

/** the glory count of a player: the glory of their ready characters, plus one for each ring they claimed */
export function gloryCount(game: GameView, player: PlayerView, cards: Cards): number {
  const glory = charactersInPlay(player)
    .filter((character) => character.card.orientation !== 'sideways')
    .reduce((total, character) => total + printed(character.card.code ? cards[character.card.code]?.glory : null), 0)
  const claimed = ELEMENTS.filter((element) => game.rings[element]?.claimed_by_id === player.id).length

  return glory + claimed
}

/** every code the viewer sees, to read the cards as printed */
export function visibleCodes(game: GameView): string[] {
  const codes = new Set<string>()
  for (const player of game.players) {
    for (const stack of player.stacks) {
      for (const card of stack.cards ?? []) {
        if (card.code !== null) {
          codes.add(card.code)
        }
      }
    }
  }

  return [...codes]
}
