import type { GameView } from '@/api/game'
import { gameEnded, provinces, viewer, type Cards } from '@/lib/board'
import { resolvingOf, ringResolving } from '@/lib/resolutions'

/**
 * The tutorial of the games against the house bot: tips over the screen of the game, each shown once, which explain
 * the interface to a player who knows the game but not the site. A tip points at a part of the screen (its anchor,
 * `data-tip-anchor` on the element) and tells how to do there what the step asks. It only says what the client knows
 * (the step, the available actions, the name of the card resolved): never what the text of a card means, which only
 * the player reads.
 *
 * A rule reads the step as well as the available actions: the effects on the board (revealing a card, bowing it…)
 * are available in every step.
 *
 * The tips the user has seen are kept in their settings (`seen_tips`). One tip shows at a time, the first of the
 * catalogue that applies and was not seen; it is seen once the player closes it, or once it no longer applies (the
 * player did what it says).
 */

export type TipAnchor =
  'step' | 'action-panel' | 'resolution' | 'ring-effect' | 'card-actions' | 'my-provinces' | 'my-bar' | 'rings' | 'log'

/** a part of a paragraph, in bold or not */
export interface TipSegment {
  text: string
  strong: boolean
}

export interface Tip {
  id: string
  anchor: TipAnchor
  /** "1 of 2", when tips follow each other */
  sequence: string | null
  title: string
  paragraphs: TipSegment[][]
}

export interface TipContext {
  game: GameView
  cards: Cards
  /** a card is opened over the board (CardDialog) */
  cardOpen: boolean
}

interface TipRule {
  id: string
  anchor: TipAnchor
  sequence?: string
  applies: (context: TipContext) => boolean
  title: string | ((context: TipContext) => string)
  /** `**bold**` in the text */
  text: string[] | ((context: TipContext) => string[])
}

const ACTION_WINDOWS = new Set([
  'draw_phase_action_window',
  'conflict_phase_action_window',
  'conflict_action_window',
  'fate_phase_action_window',
])

function can(context: TipContext, type: string): boolean {
  return context.game.available_actions.includes(type)
}

function resolving(context: TipContext) {
  return resolvingOf(context.game, viewer(context.game), context.cards)
}

function myRingEffect(context: TipContext): boolean {
  return ringResolving(context.game)?.player.id === context.game.viewer_id
}

/** the catalogue, in the order the tips are shown when several apply */
const RULES: TipRule[] = [
  // a card opened: the tips about it only, the rest of the screen being under its window
  {
    id: 'card_actions',
    anchor: 'card-actions',
    // the first cards a player opens to play them: the characters of their provinces
    applies: (context) => context.cardOpen && context.game.place === 'play_cards_from_provinces',
    title: 'Everything a card can undergo',
    text: (context) =>
      resolving(context) === null
        ? [
            'Here is everything that can happen to this card, the same for your cards and your opponent’s: play it, bow it, discard it, move it to the conflict…',
            'Rarer effects are behind the **+**.',
          ]
        : [
            'Here is everything that can happen to this card, the same for your cards and your opponent’s. Choose what the text you resolve asks for: bow it, discard it, move it to the conflict…',
            'Rarer effects are behind the **+**.',
          ],
  },
  {
    id: 'welcome',
    anchor: 'step',
    applies: (context) => context.game.place !== null,
    title: 'Kyūden keeps the board',
    text: [
      'It runs the rounds, the phases and the steps, and it lets you do anything to the cards. It does not read their texts: what a card does, you do.',
      'Here is the step of the game and what to do in it.',
    ],
  },
  {
    id: 'resolve_event',
    anchor: 'resolution',
    applies: (context) => resolving(context)?.current.event === true,
    title: 'You apply the effect yourself',
    text: (context) => [
      'Kyūden has paid its fate cost, but it does not read the text box: everything written there is yours to do, additional costs included. Click each card or player the text concerns and choose what happens to it; honor and fate are in the bars of the players.',
      `Once the board shows what the card did, click **Done with ${resolving(context)?.current.card.name ?? 'it'}**.`,
    ],
  },
  {
    id: 'resolve_ability',
    anchor: 'resolution',
    applies: (context) => resolving(context)?.current.event === false,
    title: 'You apply the effect yourself',
    text: (context) => [
      'Kyūden does not read the text of the ability: everything written there is yours to do, its costs included. Click each card or player the text concerns and choose what happens to it; honor and fate are in the bars of the players.',
      `Once the board shows what the ability did, click **Done with ${resolving(context)?.current.card.name ?? 'it'}**.`,
    ],
  },
  {
    id: 'ring_effect',
    anchor: 'ring-effect',
    applies: myRingEffect,
    title: 'The ring effect is yours to apply',
    text: (context) => [
      'You won the conflict as the attacker: you may resolve the effect of the contested ring. Kyūden does not apply it: click the cards or the players it concerns and choose what happens to them.',
      `Then click **Done with the ${ringResolving(context.game)?.name ?? 'ring'}**, whether you resolved it or not.`,
    ],
  },
  {
    id: 'end_action',
    anchor: 'action-panel',
    applies: (context) => can(context, 'end_resolution') && resolving(context) === null && !myRingEffect(context),
    title: 'End what you played',
    text: [
      'Kyūden waits until you have done everything the card you played needs: fate on it, its reactions…',
      'Then click **End resolution**: the opportunity goes to your opponent.',
    ],
  },
  {
    id: 'choose_stronghold',
    anchor: 'my-provinces',
    applies: (context) => can(context, 'choose_stronghold_province'),
    title: 'Your stronghold province',
    text: ['Click the province that goes under your stronghold. The others are placed face down, at random.'],
  },
  {
    id: 'reveal_dynasty',
    anchor: 'my-provinces',
    // revealing a dynasty card is an effect accepted in any place: the step tells, and the cards left face down
    applies: (context) =>
      context.game.place === 'reveal_facedown_dynasty_cards' &&
      provinces(viewer(context.game)).some((province) =>
        province.cards.some((card) => card.side === 'dynasty' && card.orientation === 'face_down'),
      ),
    title: 'Turn your cards face up',
    text: ['Click each face-down card on your provinces to turn it face up: Kyūden waits until they all are.'],
  },
  {
    id: 'collect_fate',
    anchor: 'action-panel',
    applies: (context) => can(context, 'collect_fate'),
    title: 'Collect your fate',
    text: ['Kyūden prefills the fate value of your stronghold. A card may change it: check it, then collect.'],
  },
  {
    id: 'play_from_provinces',
    anchor: 'my-provinces',
    applies: (context) => can(context, 'play_character_from_province'),
    title: 'Play your characters',
    text: [
      'Click a character on your provinces to play it: you choose how much fate to place on it besides its cost. Each fate keeps it in play one more round.',
      'Pass in the panel once you are done: the first player to pass gains 1 fate.',
    ],
  },
  {
    id: 'honor_bid',
    anchor: 'action-panel',
    applies: (context) => context.game.place === 'honor_bid' && can(context, 'bid_honor'),
    title: 'Your honor bid',
    text: [
      'Choose your bid in the panel: it stays secret until both players have bid. You draw as many cards as your bid.',
    ],
  },
  {
    id: 'duel',
    anchor: 'action-panel',
    applies: (context) =>
      context.game.duel !== null && (can(context, 'bid_honor') || can(context, 'declare_duel_statistic')),
    title: 'A duel',
    text: [
      'Bid honor in the panel, then declare your dueling statistic: Kyūden prefills it from the cards, without your bid, which it adds itself. Cards may change it: check it before declaring.',
    ],
  },
  {
    id: 'action_window',
    anchor: 'action-panel',
    applies: (context) =>
      context.game.place !== null && ACTION_WINDOWS.has(context.game.place) && can(context, 'pass_opportunity'),
    title: 'An action window',
    text: [
      'Click a card in your hand or in play to play it or to use its ability. The players act in turn, and the window closes once both pass in a row.',
      'Nothing to do? Click **Pass**.',
    ],
  },
  {
    id: 'declare_conflict',
    anchor: 'action-panel',
    applies: (context) => can(context, 'declare_conflict'),
    title: 'Declare a conflict',
    text: [
      'On the board, click the province you attack, the ring and its type, then your attackers. The panel tells what is chosen: declare there, or pass the conflict.',
    ],
  },
  {
    id: 'declare_defenders',
    anchor: 'action-panel',
    applies: (context) => can(context, 'declare_defenders'),
    title: 'Defend your province',
    text: [
      'Click on the board the ready characters that defend, then declare them in the panel. You may also declare no defenders.',
    ],
  },
  {
    id: 'declare_skill',
    anchor: 'action-panel',
    applies: (context) => can(context, 'declare_conflict_skill') || can(context, 'declare_province_strength'),
    title: 'The totals are yours',
    text: [
      'Kyūden prefills the skill of each side and the strength of the province from the cards, but it does not know their effects. Check them, correct them, then declare.',
    ],
  },
  {
    id: 'glory_count',
    anchor: 'action-panel',
    applies: (context) => can(context, 'declare_glory_count'),
    title: 'The glory count',
    text: [
      'Kyūden prefills your glory: your ready characters, plus one for each ring you claimed. Check it, then declare: the higher count claims the Imperial Favor.',
    ],
  },
  {
    id: 'discard_from_provinces',
    anchor: 'my-provinces',
    applies: (context) => context.game.place === 'discard_from_provinces' && can(context, 'pass_opportunity'),
    title: 'Clear your provinces',
    text: ['Click a face-up card on your provinces to discard it: its province is refilled. Pass once you are done.'],
  },
  // while nothing else asks for the player: what the screen shows
  {
    id: 'player_bar',
    anchor: 'my-bar',
    applies: (context) => context.game.rings.air !== undefined,
    title: 'Your bar',
    text: [
      'Your honor, your fate, your hand and your decks. Click any of them for what can happen to it: lose honor, gain fate, draw, look at the top of a deck…',
    ],
  },
  {
    id: 'rings',
    anchor: 'rings',
    applies: (context) => context.game.conflict !== null,
    title: 'The rings',
    text: ['Click a ring for what can happen to it: claim it, take its fate, switch it with the contested ring…'],
  },
  {
    id: 'log',
    anchor: 'log',
    applies: (context) => context.game.rings.air !== undefined,
    title: 'The log',
    text: [
      'Everything that changes is written here, what your opponent does too. Hover a card name to see its image; **Show all** opens the whole log.',
    ],
  },
]

export const TIP_IDS: string[] = RULES.map((rule) => rule.id)

/** `**bold**` in a text */
export function segmentsOf(text: string): TipSegment[] {
  return text
    .split('**')
    .map((part, index) => ({ text: part, strong: index % 2 === 1 }))
    .filter((segment) => segment.text !== '')
}

/** the tips show for the player of a game against the house bot, until it ends, unless they turned them off */
export function tipsShown(game: GameView, tutorial: boolean): boolean {
  return tutorial && game.training && !game.spectating && !game.replaying && !gameEnded(game)
}

/** whether a tip still applies, a card opened or not: once it no longer does, the player has done what it says */
export function tipApplies(id: string, context: TipContext): boolean {
  return RULES.find((rule) => rule.id === id)?.applies(context) ?? false
}

/** the first tip of the catalogue that applies and was not seen, null when there is none */
export function nextTip(context: TipContext, seen: ReadonlySet<string>): Tip | null {
  const rule = RULES.find(
    (candidate) =>
      !seen.has(candidate.id) &&
      // a card opened hides the rest of the screen
      (candidate.anchor === 'card-actions') === context.cardOpen &&
      candidate.applies(context),
  )
  if (rule === undefined) {
    return null
  }
  const text = typeof rule.text === 'function' ? rule.text(context) : rule.text

  return {
    id: rule.id,
    anchor: rule.anchor,
    sequence: rule.sequence ?? null,
    title: typeof rule.title === 'function' ? rule.title(context) : rule.title,
    paragraphs: text.map(segmentsOf),
  }
}

export interface Rect {
  left: number
  top: number
  width: number
  height: number
}

export type TipSide = 'left' | 'right' | 'above' | 'below' | 'inside'

export interface TipPlacement {
  left: number
  top: number
  /** where the tip is, from its anchor: its arrow points the other way */
  side: TipSide
  /** the position of the arrow along the edge of the tip facing the anchor, in px */
  arrow: number
}

const GAP = 16
const MARGIN = 8
const ARROW_INSET = 20

function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(value, max))
}

/**
 * Where a tip goes beside its anchor, in the window: on its left (the bar on the right of the screen), else on its
 * right (or the other way round, `right` first), below, above, else inside it, in its top right corner (an anchor as
 * large as the window).
 */
export function placeTip(
  anchor: Rect,
  tip: { width: number; height: number },
  window: { width: number; height: number },
  first: 'left' | 'right' = 'left',
): TipPlacement {
  const centerX = anchor.left + anchor.width / 2
  const centerY = anchor.top + Math.min(anchor.height / 2, 40)
  const topBeside = clamp(centerY - 40, MARGIN, window.height - tip.height - MARGIN)
  const leftAround = clamp(centerX - tip.width / 2, MARGIN, window.width - tip.width - MARGIN)
  const alongY = clamp(centerY - topBeside, ARROW_INSET, tip.height - ARROW_INSET)
  const alongX = clamp(centerX - leftAround, ARROW_INSET, tip.width - ARROW_INSET)

  const onLeft: TipPlacement | null =
    anchor.left - GAP - tip.width >= MARGIN
      ? { left: anchor.left - GAP - tip.width, top: topBeside, side: 'left', arrow: alongY }
      : null
  const onRight: TipPlacement | null =
    anchor.left + anchor.width + GAP + tip.width <= window.width - MARGIN
      ? { left: anchor.left + anchor.width + GAP, top: topBeside, side: 'right', arrow: alongY }
      : null
  const beside = first === 'left' ? (onLeft ?? onRight) : (onRight ?? onLeft)
  if (beside !== null) {
    return beside
  }
  if (anchor.top + anchor.height + GAP + tip.height <= window.height - MARGIN) {
    return { left: leftAround, top: anchor.top + anchor.height + GAP, side: 'below', arrow: alongX }
  }
  if (anchor.top - GAP - tip.height >= MARGIN) {
    return { left: leftAround, top: anchor.top - GAP - tip.height, side: 'above', arrow: alongX }
  }

  return {
    left: clamp(anchor.left + anchor.width - tip.width - GAP, MARGIN, window.width - tip.width - MARGIN),
    top: clamp(anchor.top + GAP, MARGIN, window.height - tip.height - MARGIN),
    side: 'inside',
    arrow: 0,
  }
}
