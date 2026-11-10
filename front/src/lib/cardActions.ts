import type { AbilityTiming, Card } from '@/api/client'
import type { GameView, PlayerView } from '@/api/game'
import type { Message, MessagePayloads, MessageType } from '@/api/messages'
import { charactersInPlay, locate, provinces, stacksOf, type Cards, type Located } from '@/lib/board'

/**
 * What a player can do with a card, from where it is and from the actions the engine makes available now. Board
 * manipulations apply to the cards of every player: the engine does not know which ability is being resolved.
 */

export type SimpleAction = Message & { label: string }

/** the messages playing a card, by the way it is played */
interface PlayTypes {
  from_province: 'play_character_from_province'
  character: 'play_character'
  attachment: 'play_attachment'
  /** a character played as an attachment (Tattooed Wanderer, Voice of the Ancestors): a special action */
  as_attachment: 'play_character_as_attachment'
  event: 'play_action_event' | 'play_interrupt_event' | 'play_reaction_event'
}

export type PlayKind = keyof PlayTypes

export type PlayAction = {
  [K in PlayKind]: {
    type: PlayTypes[K]
    kind: K
    label: string
    /** the printed cost, prefilled */
    cost: number
  }
}[PlayKind]

export interface CardMenu {
  located: Located
  card: Card | undefined
  /** whether the viewer controls it */
  mine: boolean
  abilities: SimpleAction[]
  plays: PlayAction[]
  /** a character played as an attachment, from where it is played (a special action, with the form of `plays`) */
  playAsAttachment: PlayAction | null
  state: SimpleAction[]
  /** the skills of a character in play can be recorded */
  skill: boolean
  /** a character in play may challenge a character of the opponent */
  duel: boolean
  /** the ways it leaves where it is: returned to hand, sacrificed, discarded */
  removals: SimpleAction[]
  /** pointing at it to show it to the opponent: a card every player sees (not in a hand nor a deck) */
  point: SimpleAction | null
  /** taking control of a card in play controlled by the opponent, by an effect (Blackmail, Calling in Favors) */
  control: SimpleAction | null
  /** the special actions (`lib/specialActions.ts`) that need nothing but a click: put on its deck, return it to hand,
   * put a character into play by an effect (Shosuro Actress)… offered apart, with the forms below */
  more: SimpleAction[]
  /** the provinces of its owner a holding of the discard pile can be put into, by an effect (Rebuild) */
  intoProvince: ProvinceChoice[]
  /** an attachment in play can be moved to another character, by an effect */
  moveAttachment: boolean
  /** an attachment out of play can be put into play attached to a character, by an effect (Time for War, Niten) */
  attachIntoPlay: boolean
  /** the status token of a character in play can be moved to another character, by an effect */
  moveStatusToken: boolean
  /** the dynasty cards of the other provinces of its owner a dynasty card can be switched with, by an effect */
  switchWith: ProvinceChoice[]
  /** the provinces of its owner a dynasty card can be put into, by an effect (Logistics, The Receding Tide) */
  putIntoProvince: ProvinceChoice[]
  /** the cards it can be placed under, by an effect (Back-Alley Hideaway, Under Siege) */
  placeUnder: HostChoice[]
}

/** a card another card can be placed under: in play, a holding face up in a province, a province, a stronghold */
export interface HostChoice {
  id: string
  name: string
  /** where it is: in play, attached to…, in a province… */
  where: string
  /** the cards of the viewer but their provinces, offered first; then their provinces, then those of the opponent */
  group: 'yours' | 'provinces' | 'opponent'
}

export interface ProvinceChoice {
  /** the province card, or the card in the province */
  id: string
  name: string
}

const ABILITY_TYPES: Record<
  AbilityTiming,
  'trigger_action_ability' | 'trigger_interrupt_ability' | 'trigger_reaction_ability'
> = {
  action: 'trigger_action_ability',
  interrupt: 'trigger_interrupt_ability',
  reaction: 'trigger_reaction_ability',
}

/** the stacks whose cards some players do not see: nobody can point at them */
const HIDDEN_STACKS = new Set(['hand', 'conflict_draw_deck', 'dynasty_draw_deck'])

const EVENT_TYPES: Record<AbilityTiming, PlayTypes['event']> = {
  action: 'play_action_event',
  interrupt: 'play_interrupt_event',
  reaction: 'play_reaction_event',
}

function cardOf(cards: Cards, code: string | null): Card | undefined {
  return code === null ? undefined : cards[code]
}

/**
 * The ways a card is played in an action window, from its hand or from elsewhere: a deck, a discard pile, a province
 * (Kunshu, Artisan Academy, Prepared Ambush…: the engine does not check the effect allowing it), whoever owns it. A
 * character in a province is played with `play_character_from_province` during the dynasty phase, with
 * `play_character` in the other action windows; an event with the message of each of its timings.
 */
export function playActions(card: Card | undefined, available: Set<string>, inProvince = false): PlayAction[] {
  const cost = card?.cost ?? 0
  switch (card?.type) {
    case 'character':
      if (inProvince && available.has('play_character_from_province')) {
        return [{ type: 'play_character_from_province', kind: 'from_province', label: 'Play this character', cost }]
      }

      return available.has('play_character')
        ? [{ type: 'play_character', kind: 'character', label: 'Play this character', cost }]
        : []
    case 'attachment':
      return available.has('play_attachment')
        ? [{ type: 'play_attachment', kind: 'attachment', label: 'Attach it', cost }]
        : []
    case 'event': {
      const timings = card.ability_timings ?? []

      return timings
        .filter((timing) => available.has(EVENT_TYPES[timing]))
        .map((timing) => ({
          type: EVENT_TYPES[timing],
          kind: 'event',
          label: timings.length === 1 ? 'Play this event' : `Play it as ${timing === 'action' ? 'an' : 'a'} ${timing}`,
          cost,
        }))
    }
    default:
      return []
  }
}

/**
 * The provinces of a player a dynasty card can be put into by an effect (`put_card_into_province`), the stronghold
 * province included unless `regularOnly` (a holding: `put_holding_into_province`), but the one it is in: by their
 * province card.
 */
export function provinceChoices(
  player: PlayerView,
  cards: Cards,
  exceptStackId: string | null = null,
  regularOnly = false,
): ProvinceChoice[] {
  return provinces(player).flatMap((province, index) => {
    if (
      province.province === null ||
      province.stack.id === exceptStackId ||
      (regularOnly && province.stronghold !== null)
    ) {
      return []
    }
    const name = cardOf(cards, province.province.code)?.name

    return [
      {
        id: province.province.id,
        name:
          province.stronghold === null
            ? (name ?? `Province ${index + 1}`)
            : `${name ?? 'Stronghold province'} (stronghold)`,
      },
    ]
  })
}

/**
 * The timings of the abilities used through a card: its own, and those its attachments grant to it; none for an
 * attachment whose abilities belong to the card it is attached to.
 */
export function abilityTimings(located: Located, cards: Cards): AbilityTiming[] {
  const card = cardOf(cards, located.card.code)
  if (located.stack.type === 'in_play' && located.under !== null && card?.ability_granted_to_attached) {
    return []
  }
  const timings = new Set(card?.ability_timings ?? [])
  if (located.stack.type === 'in_play' && located.under === null) {
    for (const attachment of (located.stack.cards ?? []).slice(1)) {
      const attached = cardOf(cards, attachment.code)
      if (attached?.ability_granted_to_attached) {
        attached.ability_timings?.forEach((timing) => timings.add(timing))
      }
    }
  }

  return (['action', 'interrupt', 'reaction'] as const).filter((timing) => timings.has(timing))
}

/**
 * A unique character in play of the player with the title of the card: the card is its duplicate, discarded to place 1
 * fate on it (step 1.4).
 */
function copyInPlay(located: Located, cards: Cards, card: Card | undefined): string | undefined {
  if (card?.type !== 'character') {
    return undefined
  }

  return located.player.stacks
    .filter((stack) => stack.type === 'in_play')
    .map((stack) => (stack.cards ?? [])[0])
    .find((character) => {
      const printed = character === undefined ? undefined : cardOf(cards, character.code)

      return printed?.uniqueness === true && printed.name === card.name
    })?.id
}

/**
 * The cards a card can be placed under (`place_card_under`), of both players: a character or an attachment in play, a
 * dynasty card face up in a province, a province, a stronghold; not the card itself nor the card it already lies under.
 */
export function hostChoices(
  game: GameView,
  cards: Cards,
  cardId: string,
  currentHostId: string | null = null,
): HostChoice[] {
  const nameOf = (code: string | null, fallback: string) => cardOf(cards, code)?.name ?? fallback
  const excluded = new Set([cardId, currentHostId])

  return game.players
    .flatMap((player): HostChoice[] => {
      const mine = player.id === game.viewer_id
      const group = mine ? 'yours' : 'opponent'
      const inPlay = charactersInPlay(player).flatMap((character) => {
        const name = nameOf(character.card.code, 'Character')

        return [
          { id: character.card.id, name, where: 'in play', group },
          ...character.attachments.map((attachment) => ({
            id: attachment.id,
            name: nameOf(attachment.code, 'Attachment'),
            where: `attached to ${name}`,
            group,
          })),
        ]
      })
      const onProvinces = provinces(player).flatMap((province) => {
        const provinceName =
          province.province === null
            ? 'Province'
            : nameOf(province.province.code, province.province.code === null ? 'Face-down province' : 'Province')
        const faceUp = province.cards
          .filter((card) => card.side === 'dynasty' && card.orientation === 'face_up')
          .map((card) => ({ id: card.id, name: nameOf(card.code, 'Card'), where: `in ${provinceName}`, group }))

        return [
          ...(province.stronghold === null
            ? []
            : [
                {
                  id: province.stronghold.id,
                  name: nameOf(province.stronghold.code, 'Stronghold'),
                  where: 'stronghold',
                  group,
                },
              ]),
          ...faceUp,
          ...(province.province === null
            ? []
            : [
                {
                  id: province.province.id,
                  name: provinceName,
                  where: province.stronghold === null ? 'province' : 'stronghold province',
                  group: mine ? 'provinces' : 'opponent',
                },
              ]),
        ]
      })

      return [...inPlay, ...onProvinces] as HostChoice[]
    })
    .filter((choice) => !excluded.has(choice.id))
    .sort((a, b) => GROUPS.indexOf(a.group) - GROUPS.indexOf(b.group))
}

const GROUPS: HostChoice['group'][] = ['yours', 'provinces', 'opponent']

function abilityLabel(timing: AbilityTiming, count: number): string {
  return count === 1 ? 'Activate its ability' : `Activate its ${timing}`
}

export function cardMenu(game: GameView, cards: Cards, cardId: string): CardMenu | undefined {
  const located = locate(game, cardId)
  if (located === undefined) {
    return undefined
  }
  const available = new Set(game.available_actions)
  const { stack, card: view, under } = located
  const card = cardOf(cards, view.code)
  const faceUp = view.orientation !== 'face_down'
  const id = view.id

  const menu: CardMenu = {
    located,
    card,
    mine: view.controller_id === game.viewer_id,
    abilities: [],
    plays: [],
    playAsAttachment: null,
    state: [],
    skill: false,
    duel: false,
    removals: [],
    point:
      available.has('point_at_card') && !HIDDEN_STACKS.has(stack.type)
        ? { type: 'point_at_card', label: 'Point at it', payload: { card_id: id } }
        : null,
    control: null,
    more: [],
    intoProvince: [],
    moveAttachment: false,
    attachIntoPlay: false,
    moveStatusToken: false,
    switchWith: [],
    putIntoProvince: [],
    placeUnder: [],
  }
  const special = new Set(game.special_actions.map((action) => action.type))
  // the payload is the card by default, for the messages whose payload is a card only; a special action goes apart
  const add = <T extends MessageType>(
    list: SimpleAction[],
    type: T,
    label: string,
    ...[payload]: { card_id: string } extends MessagePayloads[T] ? [MessagePayloads[T]?] : [MessagePayloads[T]]
  ) => {
    if (available.has(type)) {
      ;(special.has(type) ? menu.more : list).push({ type, label, payload: payload ?? { card_id: id } } as SimpleAction)
    }
  }
  const discard = () => add(menu.removals, 'discard_card', 'Discard')
  const discardDuplicate = () => {
    const copyId = menu.mine ? copyInPlay(located, cards, card) : undefined
    if (copyId !== undefined) {
      add(menu.removals, 'discard_duplicate', 'Discard as a duplicate', { card_id: id, copy_id: copyId })
    }
  }
  const sacrifice = () => add(menu.removals, 'sacrifice_card', 'Sacrifice')
  // into the deck of its side, e.g. an effect putting a character back on top of its owner's deck
  const toDeck = () => {
    add(menu.removals, 'put_card_on_deck', 'Put on top of its deck', { card_id: id, position: 'top' })
    add(menu.removals, 'put_card_on_deck', 'Put at the bottom of its deck', { card_id: id, position: 'bottom' })
    add(menu.removals, 'shuffle_into_deck', 'Shuffle into its deck')
  }
  // a card the viewer does not know: a facedown province of the opponent, a facedown card in a province
  const lookAt = () => {
    if (view.code === null) {
      add(menu.state, 'look_at_card', 'Look at it')
    }
  }
  // a character put into play by an effect, at home or into the conflict on the side of its controller: its owner, or
  // the viewer for a character of the opponent
  const putIntoPlay = () => {
    if (card?.type !== 'character') {
      return
    }
    const conflict = game.conflict
    const inConflict = (playerId: string) =>
      conflict !== null && [conflict.attacking_player_id, conflict.defending_player_id].includes(playerId)
    add(menu.more, 'put_character_into_play', 'Put into play', { card_id: id, into_conflict: false })
    if (inConflict(located.player.id)) {
      add(menu.more, 'put_character_into_play', 'Put into play in the conflict', {
        card_id: id,
        into_conflict: true,
      })
    }
    if (located.player.id !== game.viewer_id) {
      const controller_id = game.viewer_id
      add(menu.more, 'put_character_into_play', 'Put into play under your control', {
        card_id: id,
        into_conflict: false,
        controller_id,
      })
      if (inConflict(controller_id)) {
        add(menu.more, 'put_character_into_play', 'Put into play in the conflict under your control', {
          card_id: id,
          into_conflict: true,
          controller_id,
        })
      }
    }
  }
  // an attachment put into play by an effect, attached to a character chosen in a form
  const attachIntoPlay = () => {
    menu.attachIntoPlay = card?.type === 'attachment' && available.has('put_attachment_into_play')
  }
  // set aside by an effect: removed from the game (Shadow Step, Stolen Secrets), face up or face down
  const removeFromGame = () => {
    add(menu.more, 'remove_from_game', 'Remove it from the game', { card_id: id, face_down: false })
    add(menu.more, 'remove_from_game', 'Remove it from the game face down', { card_id: id, face_down: true })
  }
  // placed under another card by an effect (Back-Alley Hideaway, Under Siege): the card is chosen in a form
  const placeUnder = () => {
    if (available.has('place_card_under')) {
      menu.placeUnder = hostChoices(game, cards, id, located.host?.id ?? null)
    }
  }
  const setAside = () => {
    if (stack.type !== 'removed_from_game') {
      removeFromGame()
    }
    placeUnder()
  }
  // a holding into a province of its owner, by an effect (Rebuild, Kaiu Shihobu)
  const holdingIntoProvince = () => {
    if (card?.type === 'holding' && available.has('put_holding_into_province')) {
      menu.intoProvince = stacksOf(located.player, 'regular_province').flatMap((province, index) => {
        const provinceCard = (province.cards ?? [])[0]

        return provinceCard === undefined
          ? []
          : [{ id: provinceCard.id, name: cardOf(cards, provinceCard.code)?.name ?? `Province ${index + 1}` }]
      })
    }
  }
  // an attachment, or a character played as one (to a character only, as the form offers)
  const moveAttachment = () => {
    menu.moveAttachment =
      (card?.type === 'attachment' || (stack.type === 'in_play' && under !== null)) && available.has('move_attachment')
  }
  // a character played where it is played from, as an attachment (Tattooed Wanderer, Togashi Kazue)
  const playAsAttachment = () => {
    if (card?.type === 'character' && menu.plays.length > 0 && available.has('play_character_as_attachment')) {
      menu.playAsAttachment = {
        type: 'play_character_as_attachment',
        kind: 'as_attachment',
        label: 'Play it as an attachment',
        cost: card.cost ?? 0,
      }
    }
  }
  // a face-up province that is not broken yet, by an effect (Endless Plains); a broken one restored (Ethereal
  // Alignment)
  const breakProvince = () => {
    if (view.orientation === 'face_up') {
      add(menu.state, 'break_province', 'Break it', { province_id: id })
      // unbroken (Kitsuki Yaruma, Untamed Steppe); the engine does not check Eminent
      add(menu.state, 'turn_province_facedown', 'Turn it facedown')
    } else if (view.orientation === 'upside_down') {
      add(menu.state, 'restore_province', 'Restore it', { province_id: id })
    }
    conflictProvince()
  }
  // an unbroken province of the defending player the conflict is not at: the conflict moved to it (Chasing the Sun,
  // Golden Plains) or also at it (A War on Two Fronts); a face-down one is then revealed (`provinceToReveal()`)
  const conflictProvince = () => {
    const conflict = game.conflict
    if (
      conflict !== null &&
      located.player.id === conflict.defending_player_id &&
      view.orientation !== 'upside_down' &&
      !conflict.attacked_province_ids.includes(id)
    ) {
      add(menu.state, 'move_conflict', 'Move the conflict here', { province_id: id })
      add(menu.state, 'add_attacked_province', 'Make the conflict also here', { province_id: id })
    }
  }
  // the dynasty cards of the other provinces of its owner (Borderlands Fortifications)
  const switchWith = () => {
    if (!available.has('switch_province_cards')) {
      return
    }
    menu.switchWith = stacksOf(located.player, 'regular_province').flatMap((province, index) => {
      const [provinceCard, ...others] = province.cards ?? []
      const where = cardOf(cards, provinceCard?.code ?? null)?.name ?? `province ${index + 1}`

      return province.id === stack.id
        ? []
        : others
            .filter((other) => other.side === 'dynasty')
            .map((other) => ({
              id: other.id,
              name: `${(other.orientation !== 'face_down' && cardOf(cards, other.code)?.name) || 'Facedown card'} (${where})`,
            }))
    })
  }
  // a dynasty card put into another province of its owner, by an effect: from a province (Logistics), the discard
  // pile (Miya Satoshi) or play (The Receding Tide)
  const intoProvince = () => {
    if (view.side === 'dynasty' && available.has('put_card_into_province')) {
      menu.putIntoProvince = provinceChoices(located.player, cards, stack.id)
    }
  }
  // placing or discarding a status token by an effect, without honoring nor dishonoring the card: special actions,
  // offered apart; on a character in play or a province (an honored token on a province is the players' business)
  const statusTokens = () => {
    for (const token of ['honored', 'dishonored'] as const) {
      if (view.personal_honor !== token) {
        add(menu.state, 'place_status_token', `Place ${token === 'honored' ? 'an' : 'a'} ${token} status token`, {
          card_id: id,
          token,
        })
      }
    }
    if (view.personal_honor !== 'ordinary') {
      add(menu.state, 'discard_status_token', `Discard its ${view.personal_honor} status token`)
    }
  }
  // honor tokens on a province or a holding: counters, not status tokens, placed and removed by an effect (Fortified
  // Assembly, Endless Archives); special actions, offered apart
  const honorTokens = () => {
    add(menu.state, 'place_honor_tokens', 'Place 1 honor token', { card_id: id, amount: 1 })
    if (view.honor_tokens > 0) {
      add(menu.state, 'remove_honor_tokens', 'Remove 1 honor token', { card_id: id, amount: 1 })
    }
  }
  const bowOrReady = () =>
    view.orientation === 'sideways' ? add(menu.state, 'ready_card', 'Ready') : add(menu.state, 'bow_card', 'Bow')
  const abilities = () => {
    const timings = abilityTimings(located, cards)
    timings.forEach((timing) => add(menu.abilities, ABILITY_TYPES[timing], abilityLabel(timing, timings.length)))
  }

  switch (stack.type) {
    case 'hand':
      menu.plays = playActions(card, available)
      playAsAttachment()
      if (menu.mine) {
        add(menu.state, 'reveal_card', 'Reveal it')
      }
      putIntoPlay()
      attachIntoPlay()
      setAside()
      discardDuplicate()
      toDeck()
      discard()
      break

    case 'regular_province':
      if (under === null) {
        // the province itself
        if (faceUp) {
          abilities()
          breakProvince()
        } else {
          add(menu.state, 'reveal_province', 'Reveal it')
          lookAt()
          conflictProvince()
        }
        statusTokens()
        honorTokens()
        // whatever lies on it already (Rally, City of the Rich Frog)
        add(menu.state, 'fill_province', 'Fill this province', { province_id: id, face_up: false })
        add(menu.state, 'fill_province', 'Fill this province faceup', { province_id: id, face_up: true })
      } else if (!faceUp) {
        add(menu.state, 'reveal_dynasty_card', 'Turn it face up')
        lookAt()
        switchWith()
        intoProvince()
        setAside()
        toDeck()
        discard()
      } else {
        if (card?.type === 'character') {
          // during a conflict too (Prepared Ambush, Wind's Path)
          menu.plays = playActions(card, available, true)
          playAsAttachment()
        }
        // a character has abilities once in play; a holding has them in its province
        if (card?.type !== 'character') {
          abilities()
        }
        putIntoPlay()
        if (view.side === 'dynasty') {
          switchWith()
          intoProvince()
        } else {
          // an attachment on the province
          moveAttachment()
        }
        if (card?.type === 'holding') {
          honorTokens()
          sacrifice()
        }
        setAside()
        discardDuplicate()
        toDeck()
        discard()
      }
      break

    case 'stronghold_province':
      if ((stack.cards ?? [])[0]?.id === id) {
        // the stronghold
        abilities()
        bowOrReady()
      } else if (game.place === 'choose_stronghold') {
        add(menu.state, 'choose_stronghold_province', 'Put it under your stronghold')
      } else if (view.side === 'dynasty') {
        // a dynasty card moved onto the stronghold province (Recalled Defenses)
        intoProvince()
        setAside()
        toDeck()
        discard()
      } else if (faceUp) {
        abilities()
        breakProvince()
        statusTokens()
        honorTokens()
      } else {
        add(menu.state, 'reveal_province', 'Reveal it')
        lookAt()
        conflictProvince()
        statusTokens()
        honorTokens()
      }
      break

    case 'role':
      abilities()
      break

    case 'in_play':
      abilities()
      bowOrReady()
      if (!menu.mine && available.has('take_control')) {
        menu.control = { type: 'take_control', label: 'Take control of it', payload: { card_id: id } }
      }
      if (under === null) {
        if (view.personal_honor !== 'honored') {
          add(menu.state, 'honor_character', 'Honor')
        }
        if (view.personal_honor !== 'dishonored') {
          add(menu.state, 'dishonor_character', 'Dishonor')
        }
        statusTokens()
        menu.moveStatusToken = view.personal_honor !== 'ordinary' && available.has('move_status_token')
        add(menu.state, 'place_fate', 'Place 1 fate', { card_id: id, amount: 1 })
        if (view.fate > 0) {
          add(menu.state, 'remove_fate', 'Remove 1 fate', { card_id: id, amount: 1 })
          add(menu.state, 'move_fate', 'Return 1 fate to its owner', {
            source: 'card',
            source_id: id,
            target: 'player',
            target_id: located.player.id,
            amount: 1,
          })
        }
        if (game.conflict !== null) {
          const participating = [...game.conflict.attacker_ids, ...game.conflict.defender_ids].includes(id)
          if (participating) {
            add(menu.state, 'move_home', 'Send home')
          } else {
            add(menu.state, 'move_to_conflict', 'Move to the conflict')
          }
          menu.duel = participating && available.has('initiate_duel')
        }
        menu.skill = available.has('set_character_skill')
      }
      if (under !== null) {
        moveAttachment()
        // detached and turned into a character (Promising Youth, Togashi Hoshi)
        add(menu.more, 'detach_as_character', 'Turn it into a character')
      }
      if (under === null) {
        intoProvince()
      }
      setAside()
      add(menu.removals, 'return_card_to_hand', 'Return to hand')
      sacrifice()
      toDeck()
      discard()
      break

    case 'conflict_discard_pile':
      // Kunshu, Guidance of the Ancestors, Exposed Courtyard
      menu.plays = playActions(card, available)
      playAsAttachment()
      putIntoPlay()
      attachIntoPlay()
      add(menu.removals, 'add_card_to_hand', menu.mine ? 'Take it back into your hand' : "Put it back into Opp.'s hand")
      setAside()
      toDeck()
      break

    case 'dynasty_discard_pile':
      // Gateway to Meido
      if (card?.type === 'character') {
        menu.plays = playActions(card, available)
        playAsAttachment()
      }
      putIntoPlay()
      holdingIntoProvince()
      intoProvince()
      setAside()
      toDeck()
      break

    case 'removed_from_game':
    case 'underneath':
      // set aside by an effect, then played (Back-Alley Hideaway, Stolen Secrets), put into play (Shadow Step, an
      // attachment too), into a province (Kaiu Shihobu) or into a hand (Under Siege): whoever owns it, the engine does
      // not check the effect
      lookAt()
      menu.plays = playActions(card, available)
      playAsAttachment()
      putIntoPlay()
      attachIntoPlay()
      holdingIntoProvince()
      intoProvince()
      setAside()
      if (view.side === 'conflict') {
        add(menu.removals, 'add_card_to_hand', menu.mine ? 'Add it to your hand' : "Put it into Opp.'s hand")
      }
      toDeck()
      discard()
      break

    default:
      break
  }

  return menu
}
