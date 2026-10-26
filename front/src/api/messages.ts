/**
 * Generated from the JSON schemas of the messages (schemas/messages) by `npm run messages`: do not edit.
 */

/**
 * The player secretly selects a number on their honor dial (honor bid of the draw phase or of a duel).
 */
export interface BidHonor {
  /**
   * Number selected on the honor dial.
   */
  bid: number
}

/**
 * Bows a card in play (character, attachment or stronghold), e.g. to pay a cost or resolve an effect.
 *
 * The card can belong to any player: effects often target an opponent's card.
 */
export interface BowCard {
  /**
   * Card to bow.
   */
  card_id: string
}

/**
 * The player chooses, during setup, the province put under their stronghold.
 */
export interface ChooseStrongholdProvince {
  /**
   * Province of the player put under their stronghold.
   */
  card_id: string
}

/**
 * The player who won the glory count claims the Imperial Favor and sets it to its military or political side (3.4.2).
 */
export interface ClaimImperialFavor {
  /**
   * Side the Imperial Favor is set to.
   */
  side: 'military' | 'political'
}

/**
 * The player says how much fate they collect: card effects may modify the fate value of their stronghold.
 */
export interface CollectFate {
  /**
   * Fate collected.
   */
  amount: number
}

/**
 * The player concedes the game, which ends at once: their opponent wins. Accepted at any time once the game has started, whoever has the opportunity to act. The reason is read by the developers only, never shown to the other players: report there a bug that prevents the player from going on.
 */
export interface ConcedeGame {
  /**
   * Why the player concedes, e.g. a bug of the engine; read by the developers only.
   */
  reason?: string | null
}

/**
 * The active player declares a conflict (3.2): the contested ring, taken from the unclaimed ring pool, the type of the conflict, the attacked province of an opponent and the ready characters declared as attackers. `evaded_ids` are the characters of the defending player chosen with covert: the engine does not check the covert keyword.
 */
export interface DeclareConflict {
  /**
   * Contested ring, from the unclaimed ring pool.
   */
  ring: 'air' | 'earth' | 'fire' | 'void' | 'water'
  /**
   * Type of the conflict.
   */
  type: 'military' | 'political'
  /**
   * Attacked province of an opponent.
   */
  province_id: string
  /**
   * Ready characters of the player declared as attackers.
   */
  attacker_ids: string[]
  /**
   * Characters of the defending player chosen with covert.
   */
  evaded_ids?: string[]
}

/**
 * The attacking or defending player declares the total skill of their side, matching the conflict type, with every modifier applied (3.2.3): the engine does not apply card effects.
 */
export interface DeclareConflictSkill {
  /**
   * Total skill of the side of the player, with every modifier applied.
   */
  skill: number
}

/**
 * The defending player declares the ready characters defending the attacked province (3.2.1), at least one: declaring no defender is declare_no_defenders.
 */
export interface DeclareDefenders {
  /**
   * Ready characters of the player declared as defenders, at least one.
   *
   * @minItems 1
   */
  defender_ids: string[]
}

/**
 * A player involved in the duel declares the dueling statistic of their side (D.6), matching the duel type, with every modifier applied: the engine does not apply card effects. The honor bid is added by the engine (D.7).
 */
export interface DeclareDuelStatistic {
  /**
   * Dueling statistic of the side of the player, with every modifier applied, without the honor bid.
   */
  statistic: number
}

/**
 * Each player declares the total glory of the ready characters they control, with every modifier applied (3.4.1): the engine adds 1 for each ring in their claimed ring pool.
 */
export interface DeclareGloryCount {
  /**
   * Total glory of the ready characters the player controls, with every modifier applied, without the claimed rings.
   */
  glory: number
}

/**
 * The defending player declares no defender for the attacked province (3.2.1).
 */
export type DeclareNoDefenders = Record<string, never>

/**
 * The defending player declares the strength of an attacked province, with every modifier applied, when it differs from its printed strength (3.2.5). To be sent before the skill totals, which determine the result.
 */
export interface DeclareProvinceStrength {
  /**
   * Attacked province.
   */
  province_id: string
  /**
   * Strength of the province, with every modifier applied.
   */
  strength: number
}

/**
 * Discards a card from hand, from play or from a province to its owner's discard pile, e.g. a dynasty card from a broken province or during the fate phase, a card discarded by an effect, a character with no fate.
 *
 * The card can belong to any player: effects often target an opponent's card.
 */
export interface DiscardCard {
  /**
   * Card to discard, from hand, from play or from a province.
   */
  card_id: string
}

/**
 * The active player discards, during the play cards from provinces step, a duplicate of a unique character they have in play (a copy with the same title, from their hand or face up in one of their provinces) to place 1 fate on the character in play. The province is then refilled; ending the action (`end_resolution`), which ends the opportunity to act, is a separate action of the player.
 */
export interface DiscardDuplicate {
  /**
   * Duplicate discarded: a character in the hand of the player or face up in one of their provinces.
   */
  card_id: string
  /**
   * Unique character in play of the player, with the same title, on which 1 fate is placed.
   */
  copy_id: string
}

/**
 * Discards cards chosen at random by the engine from the hand of a player (e.g. the earth ring effect), one after the other; the whole hand when it holds fewer cards.
 */
export interface DiscardRandomCardFromHand {
  /**
   * Player who discards cards from their hand.
   */
  target_player_id: string
  /**
   * Number of cards to discard.
   */
  quantity?: number
}

/**
 * Dishonors a character in play (e.g. the fire ring effect): an honored character becomes ordinary, an ordinary one dishonored.
 *
 * The character can belong to any player: effects often target an opponent's character.
 */
export interface DishonorCharacter {
  /**
   * Character in play to dishonor.
   */
  card_id: string
}

/**
 * The target player draws conflict cards, e.g. during the draw phase or to resolve an effect. An empty deck is replaced by the shuffled discard pile.
 */
export interface DrawConflictCards {
  /**
   * Player who draws.
   */
  target_player_id: string
  /**
   * Number of conflict cards to draw.
   */
  quantity: number
}

/**
 * A player involved in the duel ends it once its results are applied (D.9): the action window in which it was initiated goes on.
 */
export type EndDuel = Record<string, never>

/**
 * The player has resolved what they played, once its effects are applied with other messages. An interrupt or a reaction ends first, the last one initiated first, without changing the opportunity to act. Otherwise the active player ends the action they have taken (a card played, an action ability triggered): their opportunity to act ends, and the next player who has not passed gets one. A player who has played nothing passes (PassOpportunity).
 */
export type EndResolution = Record<string, never>

/**
 * Puts the top card of the dynasty deck of its owner face down on a province, for the effects that fill a province: a province a dynasty card has left (played, discarded) is refilled by the engine.
 */
export interface FillProvince {
  /**
   * Province to fill.
   */
  province_id: string
}

/**
 * The target player gains fate from the general token pool, e.g. to resolve an effect.
 */
export interface GainFate {
  /**
   * Player who gains fate.
   */
  target_player_id: string
  /**
   * Fate gained.
   */
  fate_gain: number
}

/**
 * The target player gains honor from the general token pool, e.g. to resolve an effect.
 */
export interface GainHonor {
  /**
   * Player who gains honor.
   */
  target_player_id: string
  /**
   * Honor gained.
   */
  honor_gain: number
}

/**
 * Honors a character in play (e.g. the fire ring effect): a dishonored character becomes ordinary, an ordinary one honored.
 *
 * The character can belong to any player: effects often target an opponent's character.
 */
export interface HonorCharacter {
  /**
   * Character in play to honor.
   */
  card_id: string
}

/**
 * The player resolving a card ability initiates a duel (D.1) between characters they control (`challenger_ids`) and characters controlled by an opponent (`challenged_ids`). The players involved then bid (`bid_honor`).
 */
export interface InitiateDuel {
  /**
   * Type of the duel.
   */
  type: 'military' | 'political' | 'glory'
  /**
   * Characters controlled by the player who initiates the duel.
   *
   * @minItems 1
   */
  challenger_ids: string[]
  /**
   * Characters controlled by the challenged opponent.
   *
   * @minItems 1
   */
  challenged_ids: string[]
}

/**
 * The target player loses fate from their fate pool, which returns to the general token pool, e.g. to pay a cost or resolve an effect. Rejected when their fate pool does not hold that much fate.
 */
export interface LoseFate {
  /**
   * Player who loses fate.
   */
  target_player_id: string
  /**
   * Fate lost.
   */
  fate_loss: number
}

/**
 * The target player loses honor, which returns to the general token pool, e.g. to pay a cost or resolve an effect.
 */
export interface LoseHonor {
  /**
   * Player who loses honor.
   */
  target_player_id: string
  /**
   * Honor lost.
   */
  honor_loss: number
}

/**
 * Moves a participating character home: it no longer participates in the conflict, e.g. to resolve an effect.
 */
export interface MoveHome {
  /**
   * Participating character to move home.
   */
  card_id: string
}

/**
 * Moves a character of a player of the conflict into the conflict, on its controller's side, e.g. to resolve an effect.
 */
export interface MoveToConflict {
  /**
   * Character to move into the conflict.
   */
  card_id: string
}

/**
 * The active player passes a conflict opportunity (3.2): they forfeit it, whatever its type.
 */
export type PassConflict = Record<string, never>

/**
 * The active player passes their opportunity to act: they cannot act anymore until the end of the step.
 */
export type PassOpportunity = Record<string, never>

/**
 * Places fate from the general token pool on a character in play (e.g. "place 1 fate on that character").
 *
 * The character can belong to any player: effects often target an opponent's character. Fate taken from a fate pool is not placed this way: the fate spent from the pool is not moved yet.
 */
export interface PlaceFate {
  /**
   * Character in play the fate is placed on.
   */
  card_id: string
  /**
   * Fate placed.
   */
  amount: number
}

/**
 * The active player plays an action event from hand during an action window and pays its cost (`card_cost`); the event goes to the discard pile. The player resolves its effects with other actions, then ends their action (`end_resolution`), which ends their opportunity to act. Interrupt and reaction events are played with `play_interrupt_event` and `play_reaction_event`.
 */
export interface PlayActionEvent {
  /**
   * Action event in the hand of the player.
   */
  card_id: string
  /**
   * Fate paid for the card.
   */
  card_cost: number
}

/**
 * The active player plays an attachment from hand during an action window and attaches it to a card in play (a character of any player, or a province). The player says what they pay (`card_cost`), since card effects may modify costs. The player resolves what follows with other actions, then ends their action (`end_resolution`), which ends their opportunity to act.
 */
export interface PlayAttachment {
  /**
   * Attachment in the hand of the player.
   */
  card_id: string
  /**
   * Character in play or province the attachment goes on.
   */
  target_card_id: string
  /**
   * Fate paid for the card.
   */
  card_cost: number
}

/**
 * The active player plays a character from hand during an action window, at home or, during a conflict in which they take part, into the conflict on their side.
 *
 * The player says what they pay, since card effects may modify costs: `card_cost` for the card, `fate_cost` for the additional fate, and `fate` is the number of fate placed on the character. The player resolves what follows with other actions, then ends their action (`end_resolution`), which ends their opportunity to act.
 */
export interface PlayCharacterFromHand {
  /**
   * Character in the hand of the player.
   */
  card_id: string
  /**
   * Fate paid for the card.
   */
  card_cost: number
  /**
   * Fate paid for the additional fate placed on the character.
   */
  fate_cost: number
  /**
   * Fate placed on the character.
   */
  fate: number
  /**
   * Whether the character enters play into the conflict, on the side of the player.
   */
  into_conflict: boolean
}

/**
 * The active player plays a character from one of their provinces during the play cards from provinces step.
 *
 * The player says what they pay, since card effects may modify costs: `card_cost` for the card, `fate_cost` for the additional fate, and `fate` is the number of fate placed on the character. Refilling the province and ending the action (`end_resolution`), which ends the opportunity to act, are separate actions of the player.
 */
export interface PlayCharacterFromProvince {
  /**
   * Face-up character in a province of the player.
   */
  card_id: string
  /**
   * Fate paid for the card.
   */
  card_cost: number
  /**
   * Fate paid for the additional fate placed on the character.
   */
  fate_cost: number
  /**
   * Fate placed on the character.
   */
  fate: number
}

/**
 * A player plays an interrupt event from hand before its triggering condition resolves, whether or not they have the opportunity to act (e.g. Stay Your Hand during a duel initiated by an opponent), and pays its cost (`card_cost`); the event goes to the discard pile. The player resolves its effects with other actions, then ends it (`end_resolution`): the opportunities to act do not change. Action and reaction events are played with `play_action_event` and `play_reaction_event`.
 */
export interface PlayInterruptEvent {
  /**
   * Interrupt event in the hand of the player.
   */
  card_id: string
  /**
   * Fate paid for the card.
   */
  card_cost: number
}

/**
 * A player plays a reaction event from hand after its triggering condition has resolved, whether or not they have the opportunity to act, and pays its cost (`card_cost`); the event goes to the discard pile. The player resolves its effects with other actions, then ends it (`end_resolution`): the opportunities to act do not change. Action and interrupt events are played with `play_action_event` and `play_interrupt_event`.
 */
export interface PlayReactionEvent {
  /**
   * Reaction event in the hand of the player.
   */
  card_id: string
  /**
   * Fate paid for the card.
   */
  card_cost: number
}

/**
 * Points at a card to show it to the opponent, e.g. a card in a discard pile or the target of an ability: it changes nothing in the game, only writes an entry in its log.
 *
 * Any card of a stack every player sees (not a deck nor a hand), of any player; a face-down card is pointed at without showing its face.
 */
export interface PointAtCard {
  /**
   * Card to point at.
   */
  card_id: string
}

/**
 * Readies a bowed card in play (character, attachment or stronghold), e.g. to resolve an effect.
 *
 * The card can belong to any player: effects often target an opponent's card.
 */
export interface ReadyCard {
  /**
   * Bowed card to ready.
   */
  card_id: string
}

/**
 * Removes fate from a card in play and returns it to the general token pool (e.g. the void ring effect).
 *
 * The card can belong to any player: effects often target an opponent's card.
 */
export interface RemoveFate {
  /**
   * Card in play the fate is removed from.
   */
  card_id: string
  /**
   * Fate removed.
   */
  amount: number
}

/**
 * Clears the skills set on a character in play (`set_character_skill`): the character is back to its printed military and political skills, e.g. once the effects modifying them have expired.
 *
 * The character can belong to any player.
 */
export interface ResetCharacterSkill {
  /**
   * Character in play.
   */
  card_id: string
}

/**
 * Returns a card in play (a character, an attachment) to its owner's hand. A dynasty card is put into its owner's dynasty discard pile instead.
 *
 * The card can belong to any player: effects often target an opponent's card.
 */
export interface ReturnCardToHand {
  /**
   * Card in play to return to its owner's hand.
   */
  card_id: string
}

/**
 * Turns a face-down dynasty card in a province face up, e.g. during the dynasty phase.
 */
export interface RevealDynastyCard {
  /**
   * Face-down dynasty card in a province.
   */
  card_id: string
}

/**
 * Turns a face-down province face up, of any player: the defending player reveals the attacked province once a conflict is declared (3.2), and card effects reveal provinces.
 */
export interface RevealProvince {
  /**
   * Face-down province, of any player.
   */
  card_id: string
}

/**
 * Sacrifices a card in play (a character, an attachment, a holding in a province) to its owner's discard pile, usually as the cost of an ability. Not a discard: effects reacting to a discarded card do not apply.
 *
 * The card can belong to any player: an effect can make an opponent sacrifice a card.
 */
export interface SacrificeCard {
  /**
   * Card in play to sacrifice.
   */
  card_id: string
}

/**
 * Records the military or political skill of a character in play, every modifier applied (an event, an attachment, another effect), so that the players see its current skill: the engine does not apply card effects. It replaces the skill set before, until `reset_character_skill` or the character leaves play.
 *
 * The engine does not compute the totals of a conflict from it: they are declared by the players (`declare_conflict_skill`). The character can belong to any player.
 */
export interface SetCharacterSkill {
  /**
   * Character in play.
   */
  card_id: string
  /**
   * Skill to set.
   */
  type: 'military' | 'political'
  /**
   * Skill of the character, every modifier applied.
   */
  skill: number
}

/**
 * The bid on the honor dial of the target player changes, e.g. to resolve a reaction to the honor dials being revealed.
 */
export interface SetHonorBid {
  /**
   * Player whose honor dial changes.
   */
  target_player_id: string
  /**
   * New number on the honor dial.
   */
  bid: number
}

/**
 * Shuffles the conflict deck of the target player, e.g. to resolve an effect.
 */
export interface ShuffleConflictDeck {
  /**
   * Player whose conflict deck is shuffled.
   */
  target_player_id: string
}

/**
 * Shuffles the dynasty deck of the target player, e.g. to resolve an effect.
 */
export interface ShuffleDynastyDeck {
  /**
   * Player whose dynasty deck is shuffled.
   */
  target_player_id: string
}

/**
 * Switches the type of the conflict being resolved, military to political or political to military, e.g. to resolve an effect (Rally to the Cause). The conflict opportunity used by its declaration does not change.
 */
export type SwitchConflictType = Record<string, never>

/**
 * Switches the contested ring of the conflict being resolved with a ring of the unclaimed or claimed ring pool of the attacking player, e.g. to resolve an effect (Elemental Fury): the contested ring takes its place in that pool, and the attacking player takes the fate on the new contested ring.
 */
export interface SwitchContestedRing {
  /**
   * Ring that becomes the contested ring, unclaimed or claimed by the attacking player.
   */
  ring: 'air' | 'earth' | 'fire' | 'void' | 'water'
}

/**
 * The giver gives honor to the recipient: "give your opponent 1 honor" is a transfer from the player, "take 1 honor from that opponent" a transfer from that opponent.
 */
export interface TransferHonor {
  /**
   * Player who gives honor.
   */
  giver_id: string
  /**
   * Player who receives honor.
   */
  recipient_id: string
  /**
   * Honor transferred.
   */
  amount: number
}

/**
 * The active player triggers an action ability of one of their cards during an action window. Interrupt and reaction abilities are triggered with `trigger_interrupt_ability` and `trigger_reaction_ability`.
 *
 * The engine does not apply card effects: the message only records which card is used. The player pays the costs and resolves the effects with other actions, then ends it (`end_resolution`), which ends their opportunity to act.
 */
export interface TriggerActionAbility {
  /**
   * Card whose action ability is triggered.
   */
  card_id: string
}

/**
 * A player triggers an interrupt ability of one of their cards before its triggering condition resolves, whether or not they have the opportunity to act. Action and reaction abilities are triggered with `trigger_action_ability` and `trigger_reaction_ability`.
 *
 * The engine does not apply card effects: the message only records which card is used. The player pays the costs and resolves the effects with other actions, then ends it (`end_resolution`): the opportunities to act do not change.
 */
export interface TriggerInterruptAbility {
  /**
   * Card whose interrupt ability is triggered.
   */
  card_id: string
}

/**
 * A player triggers a reaction ability of one of their cards after its triggering condition has resolved, whether or not they have the opportunity to act. Action and interrupt abilities are triggered with `trigger_action_ability` and `trigger_interrupt_ability`.
 *
 * The engine does not apply card effects: the message only records which card is used. The player pays the costs and resolves the effects with other actions, then ends it (`end_resolution`): the opportunities to act do not change.
 */
export interface TriggerReactionAbility {
  /**
   * Card whose reaction ability is triggered.
   */
  card_id: string
}

/** the payload of each type of message */
export interface MessagePayloads {
  bid_honor: BidHonor
  bow_card: BowCard
  choose_stronghold_province: ChooseStrongholdProvince
  claim_imperial_favor: ClaimImperialFavor
  collect_fate: CollectFate
  concede_game: ConcedeGame
  declare_conflict: DeclareConflict
  declare_conflict_skill: DeclareConflictSkill
  declare_defenders: DeclareDefenders
  declare_duel_statistic: DeclareDuelStatistic
  declare_glory_count: DeclareGloryCount
  declare_no_defenders: DeclareNoDefenders
  declare_province_strength: DeclareProvinceStrength
  discard_card: DiscardCard
  discard_duplicate: DiscardDuplicate
  discard_random_card_from_hand: DiscardRandomCardFromHand
  dishonor_character: DishonorCharacter
  draw_conflict_cards: DrawConflictCards
  end_duel: EndDuel
  end_resolution: EndResolution
  fill_province: FillProvince
  gain_fate: GainFate
  gain_honor: GainHonor
  honor_character: HonorCharacter
  initiate_duel: InitiateDuel
  lose_fate: LoseFate
  lose_honor: LoseHonor
  move_home: MoveHome
  move_to_conflict: MoveToConflict
  pass_conflict: PassConflict
  pass_opportunity: PassOpportunity
  place_fate: PlaceFate
  play_action_event: PlayActionEvent
  play_attachment: PlayAttachment
  play_character_from_hand: PlayCharacterFromHand
  play_character_from_province: PlayCharacterFromProvince
  play_interrupt_event: PlayInterruptEvent
  play_reaction_event: PlayReactionEvent
  point_at_card: PointAtCard
  ready_card: ReadyCard
  remove_fate: RemoveFate
  reset_character_skill: ResetCharacterSkill
  return_card_to_hand: ReturnCardToHand
  reveal_dynasty_card: RevealDynastyCard
  reveal_province: RevealProvince
  sacrifice_card: SacrificeCard
  set_character_skill: SetCharacterSkill
  set_honor_bid: SetHonorBid
  shuffle_conflict_deck: ShuffleConflictDeck
  shuffle_dynasty_deck: ShuffleDynastyDeck
  switch_conflict_type: SwitchConflictType
  switch_contested_ring: SwitchContestedRing
  transfer_honor: TransferHonor
  trigger_action_ability: TriggerActionAbility
  trigger_interrupt_ability: TriggerInterruptAbility
  trigger_reaction_ability: TriggerReactionAbility
}

export type MessageType = keyof MessagePayloads

/** a message the player sends: its type and its payload */
export type Message = { [T in MessageType]: { type: T; payload: MessagePayloads[T] } }[MessageType]
