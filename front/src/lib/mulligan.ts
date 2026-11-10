import type { GameView, LoggedMessage, LogParam } from '@/api/game'

/**
 * The mulligans of setup (`dynasty_mulligan`, then `conflict_mulligan`): in player order, each player sets aside any
 * number of their dynasty cards, then of their starting hand, which are replaced from their deck. The client shows a
 * window over the board until the player has decided, then their new cards until they are done with them (the engine
 * records nothing then). The face-down dynasty cards are hidden from their owner in the view: the log gives them to
 * the player (`{player} looks at {cards} in {stack}`, an entry per province), when the step begins and once they have
 * mulliganed.
 */

export type MulliganStep = 'dynasty' | 'conflict'

/** choosing the cards; waiting for the player before them in player order; looking at the new cards */
export type MulliganPhase = 'choose' | 'wait' | 'result'

export interface MulliganCard {
  id: string
  code: string
  /** drawn by the mulligan */
  isNew: boolean
  /** the province the dynasty card lies on */
  provinceId: string | null
}

export interface MulliganWindow {
  step: MulliganStep
  phase: MulliganPhase
  cards: MulliganCard[]
  /** the cards set aside, then shuffled back into the deck (result) */
  setAside: { id: string; code: string }[]
  /** the version of the mulligan of the viewer (result), which "Done" dismisses */
  version: number | null
}

const MESSAGES: Record<MulliganStep, string> = {
  dynasty: 'mulligan_dynasty_cards',
  conflict: 'mulligan_conflict_cards',
}

const PLACES: Record<MulliganStep, string> = {
  dynasty: 'dynasty_mulligan',
  conflict: 'conflict_mulligan',
}

function paramsOf(entry: { params: Record<string, LogParam> | [] }): Record<string, LogParam> {
  return Array.isArray(entry.params) ? {} : entry.params
}

/** the cards known by id and code in a param, a list or a card */
function knownCards(param: LogParam | undefined): { id: string; code: string }[] {
  if (param?.type === 'card') {
    return param.id !== null && param.code !== null ? [{ id: param.id, code: param.code }] : []
  }
  if (param?.type === 'list') {
    return param.items.flatMap((item) => knownCards(item))
  }

  return []
}

/** the dynasty cards of their provinces the viewer looks at in a message, with their province */
function provinceLooks(message: LoggedMessage, viewerId: string): MulliganCard[] {
  return message.entries.flatMap((entry) => {
    const params = paramsOf(entry)
    const { player, stack } = params
    if (
      !entry.template.startsWith('{player} looks at') ||
      player?.type !== 'player' ||
      player.id !== viewerId ||
      stack?.type !== 'stack' ||
      stack.stack_type !== 'regular_province' ||
      stack.player_id !== viewerId
    ) {
      return []
    }

    return knownCards(params.cards).map((card) => ({ ...card, isNew: false, provinceId: stack.id }))
  })
}

/** the cards of the hand of the viewer, from the view */
function hand(game: GameView): MulliganCard[] {
  const viewer = game.players.find((player) => player.id === game.viewer_id)
  const stack = viewer?.stacks.find((candidate) => candidate.type === 'hand')

  return (stack?.cards ?? []).flatMap((card) =>
    card.code === null ? [] : [{ id: card.id, code: card.code, isNew: false, provinceId: null }],
  )
}

/** the dynasty cards of the viewer before their mulligan: the last look at their provinces before it */
function dynastyCardsBefore(log: LoggedMessage[], viewerId: string, before: number): MulliganCard[] {
  for (const message of [...log].reverse()) {
    if (message.version >= before) {
      continue
    }
    const cards = provinceLooks(message, viewerId)
    if (cards.length > 0) {
      return cards
    }
  }

  return []
}

/**
 * The window of the mulligan the viewer has to see, if any. `shownAfter` is the version the results are shown after:
 * those of the messages read when the page opened are not shown again, nor those the viewer is done with.
 */
export function mulliganWindow(game: GameView, log: LoggedMessage[], shownAfter: number): MulliganWindow | null {
  if (game.spectating) {
    return null
  }
  const viewerId = game.viewer_id

  for (const step of ['dynasty', 'conflict'] as const) {
    const mine = log.find((message) => message.type === MESSAGES[step] && message.player_id === viewerId)
    if (mine !== undefined) {
      if (mine.version <= shownAfter) {
        continue
      }

      return step === 'dynasty' ? dynastyResult(log, mine, viewerId) : conflictResult(game, mine)
    }
    if (game.place !== PLACES[step]) {
      continue
    }

    return {
      step,
      phase: game.active_player_ids.includes(viewerId) ? 'choose' : 'wait',
      cards: step === 'dynasty' ? dynastyCardsBefore(log, viewerId, Number.MAX_SAFE_INTEGER) : hand(game),
      setAside: [],
      version: null,
    }
  }

  return null
}

function dynastyResult(log: LoggedMessage[], mine: LoggedMessage, viewerId: string): MulliganWindow {
  const before = dynastyCardsBefore(log, viewerId, mine.version)
  const after = provinceLooks(mine, viewerId)
  // the cards kept: the viewer does not look at them again
  const cards = after.length === 0 ? before : after
  const beforeIds = new Set(before.map((card) => card.id))
  const afterIds = new Set(cards.map((card) => card.id))

  return {
    step: 'dynasty',
    phase: 'result',
    cards: cards.map((card) => ({ ...card, isNew: !beforeIds.has(card.id) })),
    setAside: before.filter((card) => !afterIds.has(card.id)).map(({ id, code }) => ({ id, code })),
    version: mine.version,
  }
}

function conflictResult(game: GameView, mine: LoggedMessage): MulliganWindow {
  const cards = hand(game)
  const handIds = new Set(cards.map((card) => card.id))
  // the cards the mulligan names: those drawn, still in hand, and those shuffled back into the deck
  const named = mine.entries.flatMap((entry) => knownCards(paramsOf(entry).card))
  const namedIds = new Set(named.map((card) => card.id))

  return {
    step: 'conflict',
    phase: 'result',
    cards: cards.map((card) => ({ ...card, isNew: namedIds.has(card.id) })),
    setAside: named.filter((card) => !handIds.has(card.id)),
    version: mine.version,
  }
}
