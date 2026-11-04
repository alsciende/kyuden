import type { CardView, ConflictType, Element, GameView, PlayerView } from '@/api/game'
import type { Message } from '@/api/messages'
import { ELEMENTS, cardsOf, conflictsLeft, hasPassed, provinces, stackOf, stepOf } from '@/lib/board'

/**
 * The bar of a player, above the board for the opponent and below it for the viewer: what the player has, in three
 * sections (the player, their dynasty cards, their conflict cards), and the effects applied to them (honor, fate,
 * cards, decks), for the costs and effects the engine does not apply itself.
 */

export interface PlayerBar {
  honor: number | null
  fate: number
  conflictsLeft: ConflictType[]
  /** the rings the player has claimed */
  rings: Element[]
  intactProvinces: number
  provinces: number
  /** how far the stronghold is from being attacked, from the point of view of the viewer */
  strongholdTip: string
  dynastyDeck: number
  /** from the top: the last card discarded first */
  dynastyDiscard: CardView[]
  hand: number
  conflictDeck: number
  conflictDiscard: CardView[]
  firstPlayer: boolean
  /** the player holds the Imperial Favor */
  favor: boolean
  /** what the player has done in the step: passed, bid, glory count */
  notes: string[]
}

export function playerBar(game: GameView, player: PlayerView): PlayerBar {
  const all = provinces(player)
  /** the bids are shown during the draw phase and the duels, the steps they are made for */
  const bidding = stepOf(game.place).phase === 'draw' || game.duel !== null

  return {
    honor: player.honor,
    fate: player.fate,
    conflictsLeft: conflictsLeft(player),
    rings: ELEMENTS.filter((element) => game.rings[element]?.claimed_by_id === player.id),
    intactProvinces: all.filter((province) => !province.broken).length,
    provinces: all.length,
    strongholdTip: strongholdTip(
      game,
      player,
      all.filter((province) => !province.stronghold && province.broken).length,
    ),
    dynastyDeck: stackOf(player, 'dynasty_draw_deck')?.count ?? 0,
    dynastyDiscard: [...cardsOf(stackOf(player, 'dynasty_discard_pile'))].reverse(),
    hand: stackOf(player, 'hand')?.count ?? 0,
    conflictDeck: stackOf(player, 'conflict_draw_deck')?.count ?? 0,
    conflictDiscard: [...cardsOf(stackOf(player, 'conflict_discard_pile'))].reverse(),
    firstPlayer: player.id === game.first_player_id,
    favor: game.imperial_favor_holder_id === player.id,
    notes: [
      hasPassed(game, player) ? 'Passed' : null,
      bidding && player.honor_bid !== null ? `Bid ${player.honor_bid}` : null,
      player.honor_bid_selected ? 'Has bid' : null,
      player.glory_count !== null ? `Glory ${player.glory_count}` : null,
    ].filter((note): note is string => note !== null),
  }
}

/** the stronghold province can be attacked once three of the other provinces of its player are broken */
const BROKEN_FOR_STRONGHOLD = 3

function strongholdTip(game: GameView, player: PlayerView, broken: number): string {
  const left = Math.max(BROKEN_FOR_STRONGHOLD - broken, 0)
  const mine = player.id === game.viewer_id
  if (game.spectating) {
    const other = game.players.find((candidate) => candidate.id !== player.id)?.name ?? 'The opponent'

    return left === 0
      ? `${other} can attack ${player.name}'s stronghold.`
      : `${other} must break ${left} more province${left === 1 ? '' : 's'} before attacking ${player.name}'s stronghold.`
  }
  if (left === 0) {
    return mine ? 'Opp. can attack your stronghold.' : "You can attack Opp.'s stronghold."
  }
  const count = `${left} more province${left === 1 ? '' : 's'}`

  return mine
    ? `Opp. must break ${count} before attacking your stronghold.`
    : `You must break ${count} before attacking Opp.'s stronghold.`
}

/** the values of the bar a click opens, with the effects on them or the cards of a discard pile */
export type BarStat =
  | 'honor'
  | 'fate'
  | 'hand'
  | 'dynasty_deck'
  | 'conflict_deck'
  | 'dynasty_discard'
  | 'conflict_discard'
  | 'first_player'
  | 'imperial_favor'

/** the values whose effects take no amount */
export const WITHOUT_AMOUNT: BarStat[] = ['first_player', 'imperial_favor']

const SIDES: ConflictType[] = ['military', 'political']

export type Effect = Message & { label: string }

/**
 * The effects on a value of the bar of a player, applied with an amount; only those the engine makes available now.
 * Looking at cards or revealing them opens the window of those cards (`lib/inspection.ts`).
 */
export function playerEffects(game: GameView, player: PlayerView, stat: BarStat, amount: number): Effect[] {
  const target = { target_player_id: player.id }
  const cards = `${amount} card${amount === 1 ? '' : 's'}`
  const other = game.players.find((candidate) => candidate.id !== player.id)
  const top = amount === 1 ? 'the top card' : `the top ${amount}`
  const inspect = (stack: 'conflict_deck' | 'dynasty_deck'): Effect[] => [
    { type: 'look_at_cards', label: `Look at ${top}`, payload: { ...target, stack, quantity: amount } },
    { type: 'reveal_cards', label: `Reveal ${top}`, payload: { ...target, stack, quantity: amount } },
  ]
  let effects: (Effect | false | undefined)[] = []

  switch (stat) {
    case 'honor':
      effects = [
        { type: 'gain_honor', label: `Gain ${amount} honor`, payload: { ...target, honor_gain: amount } },
        { type: 'lose_honor', label: `Lose ${amount} honor`, payload: { ...target, honor_loss: amount } },
        other && {
          type: 'transfer_honor',
          label: `Give ${amount} to ${other.id === game.viewer_id ? 'you' : 'Opp.'}`,
          payload: { giver_id: player.id, recipient_id: other.id, amount },
        },
        player.honor_bid !== null && {
          type: 'set_honor_bid',
          label: `Set the bid to ${amount}`,
          payload: { ...target, bid: amount },
        },
      ]
      break
    case 'fate':
      effects = [
        { type: 'gain_fate', label: `Gain ${amount} fate`, payload: { ...target, fate_gain: amount } },
        { type: 'lose_fate', label: `Lose ${amount} fate`, payload: { ...target, fate_loss: amount } },
        other && {
          type: 'move_fate',
          label: `Give ${amount} to ${other.id === game.viewer_id ? 'you' : 'Opp.'}`,
          payload: { source: 'player', source_id: player.id, target: 'player', target_id: other.id, amount },
        },
      ]
      break
    case 'hand':
      effects = [
        { type: 'draw_conflict_cards', label: `Draw ${cards}`, payload: { ...target, quantity: amount } },
        {
          type: 'discard_random_card_from_hand',
          label: `Discard ${amount} at random`,
          payload: { ...target, quantity: amount },
        },
        // the viewer sees their own hand
        player.id !== game.viewer_id && {
          type: 'look_at_cards',
          label: 'Look at the hand',
          payload: { ...target, stack: 'hand' },
        },
        { type: 'reveal_cards', label: 'Reveal the hand', payload: { ...target, stack: 'hand' } },
      ]
      break
    case 'conflict_deck':
      effects = [
        { type: 'draw_conflict_cards', label: `Draw ${cards}`, payload: { ...target, quantity: amount } },
        { type: 'shuffle_conflict_deck', label: 'Shuffle', payload: target },
        ...inspect('conflict_deck'),
      ]
      break
    case 'dynasty_deck':
      effects = [{ type: 'shuffle_dynasty_deck', label: 'Shuffle', payload: target }, ...inspect('dynasty_deck')]
      break
    // the token of the first player goes to the other player, e.g. kept by an effect when it would be passed
    case 'first_player':
      effects = [
        other && {
          type: 'take_first_player_token',
          label: other.id === game.viewer_id ? 'Take it' : 'Give it to Opp.',
          payload: { target_player_id: other.id },
        },
      ]
      break
    // the Imperial Favor of the player: discarded, set to its other side, or claimed by the other player; else claimed
    case 'imperial_favor':
      effects =
        game.imperial_favor_holder_id === player.id
          ? [
              { type: 'discard_imperial_favor', label: 'Discard it', payload: {} },
              ...SIDES.filter((side) => side !== game.imperial_favor_side).map((side): Effect => ({
                type: 'take_imperial_favor',
                label: `Turn it ${side} side up`,
                payload: { ...target, side },
              })),
              ...SIDES.map(
                (side) =>
                  other && {
                    type: 'take_imperial_favor' as const,
                    label: `${other.id === game.viewer_id ? 'Take it' : 'Give it to Opp.'}, ${side} side up`,
                    payload: { target_player_id: other.id, side },
                  },
              ),
            ]
          : SIDES.map((side): Effect => ({
              type: 'take_imperial_favor',
              label: `Claim it, ${side} side up`,
              payload: { ...target, side },
            }))
      break
  }
  const available = new Set(game.available_actions)

  return effects.filter((effect): effect is Effect => !!effect && available.has(effect.type))
}
