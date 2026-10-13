# HANDOFF

## Current status

Early stage. Implemented: matchmaking, game creation/setup, stronghold province choice, deck shuffling, drawing, filling provinces, the dynasty phase (discarding duplicates is not implemented yet) the honor bid, the reveal of the honor dials, the honor transfer and the card draw of the draw phase (`cards_drawn`), the action window of the draw phase, the model and workflow skeleton of the conflict phase (latest), the move to the conflict phase (`draw_phase_ended`), running out of cards and the end of the game by honor.

The conflict phase has its places and transitions (skeleton, see Conflict phase below), but no conflict can be declared yet: the game stops at `declare_conflict`. From `fate_phase_begins` onward, `game` transitions are placeholders (`t28`, `t29`, …) with no logic and no `auto` flag.

## Decisions taken for the dynasty phase

- `collect_fate` and `play_cards_from_provinces` are resolved in player order (active player of `GameState`).
- In `play_cards_from_provinces`, `PassOpportunity` passes: the first to pass gains 1 fate (`PlayCardsFromProvincesOpportunityPassedListener`), the last to pass ends the step.

## Decisions taken for `PassOpportunity`

- Generic pass, allowed in any `game` place with the `opportunities` metadata (`play_cards_from_provinces`, `reveal_honor_dials`, `draw_phase_action_window`). The rules of a step go in listeners of `OpportunityPassedEvent`, which check the game place.
- Passing is final (`PassingRule::Final`, except in action windows): the player is no longer active and is skipped until the end of the step, which ends once every player has passed (`AllPlayersPassedGuardListener`, one `AsGuardListener` attribute per transition).
- Several players can be active at once (`GameState::activePlayerIds`): players acting simultaneously keep their opportunity until they pass, and `EndOpportunity` changes nothing for them. The opportunity goes to the next player only when no player is active anymore.

## Decisions taken for the reveal of the honor dials

- `reveal_honor_dials` is a step where every player is active (`RevealHonorDialsEnterListener`), to react to the honor dials being revealed (e.g. Kitsuki Jusai, cards changing a bid by 1), then pass. `honor_dials_revealed` moves on to `transfer_honor` once every player has passed. Passing is final, although a player could react to an opponent's reaction: acceptable since each of these cards reacts once.
- `SetHonorBid` changes the bid on the dial of a player. Like the other effect messages, it is accepted in any place, from any player of the game, on any player. The player must have bid this round (`NoHonorBidException`); the bid can be 0 (cards decrease it "to a minimum of 0"), not negative.
- The bids are not revealed by the engine: their secrecy is not enforced.

## Decisions taken for the honor transfer

- `honor_transferred` is automatic: the engine computes the transfer from the recorded bids (`HonorTransferredTransitionListener`), after the changes made with `SetHonorBid` during `reveal_honor_dials`. No player action: cards modifying the transfer itself are handled with the effect messages (`TransferHonor`, `GainHonor`…) in a later window.
- The highest bidder gives the difference to the lowest bidder; nothing happens on equal bids. Every player must have bid (`NoHonorBidException`).
- Reaching 0 or 25 honor during the transfer ends the game before the draw (see the end of the game by honor).

## Decisions taken for the card draw

- `cards_drawn` is automatic: each player draws as many conflict cards as their honor bid (`CardsDrawnTransitionListener`, through `DrawConflictCards` on `transition.bus`), after the changes made with `SetHonorBid`. No player action: cards drawing more are handled with `DrawConflictCards` in a later window. Every player must have bid (`NoHonorBidException`); a bid of 0 draws nothing.
- `cards_drawn` leads to the action window of the draw phase (`draw_phase_action_window`, between 2.5 and 2.6).

## Decisions taken for action windows

- The `opportunities` place metadata is an `Enum/PassingRule`: `Final` for the steps where passing is final (`play_cards_from_provinces`, the special action window 1.4, and `reveal_honor_dials`), `Consecutive` for action windows (`draw_phase_action_window` for now).
- In an action window, `EndOpportunity` means the player has acted: it resets the passes of every player (`OpportunityService::passToNextPlayer()`), then gives the opportunity to the next player. `PassOpportunity` is unchanged: the window closes once every player has passed since the last action (`AllPlayersPassedGuardListener`). An `EndOpportunity` with no action before it counts as an action.
- `FirstPlayerOpportunityEnterListener` activates the first player and resets the passes (also used by `play_cards_from_provinces`); `AllPlayersOpportunityEnterListener` does the same with every player (`reveal_honor_dials`, reaction windows). The conflict action window (3.2.2) will activate the defender instead.

## Decisions taken for running out of cards

- `Service/DrawDeckService::getTopCard()` applies the rule ("Running Out of Cards" in the glossary) to both decks: when a player attempts to draw a conflict card (`DrawConflictCards`) or to fill a province (`FillProvince`) from an empty deck, they lose 5 honor, the discard pile goes back into the deck (`put_back` transition of the conflict and dynasty card workflows), then the deck is shuffled. The rule applies on the attempt, not when the deck becomes empty.
- `DrawConflictCards` draws the cards one by one, so a draw that empties the deck goes on in the new deck.
- When the discard pile is empty too, the player still loses 5 honor and draws as many cards as possible (the province stays empty); no exception.
- Reaching 0 honor this way ends the game (see the end of the game by honor).

## Decisions taken for the end of the game by honor

- Honor stops at 0 (a player cannot lose honor they do not have) and can exceed 25. No exception: the action that reaches a limit is kept and ends the game.
- The victory conditions are read from the state (`Service/VictoryService`), not detected where honor changes: `end_game` is an automatic transition from every place of a started game (not `join_game` nor `choose_stronghold`) to `game_ended`, guarded on a victory condition being met. It is checked after every message and between automatic transitions.
- Automatic transitions have a `priority` metadata (0 by default); `end_game` has 100, so it wins over the next step (e.g. `cards_drawn` after a transfer leaving a player with no honor). Two enabled automatic transitions with the same highest priority throw a `LogicException`, so a conflict shows up instead of being settled by the declaration order.
- The game ends at the end of the message, not in the middle of it: a draw emptying the deck goes on after the loss of 5 honor.
- Simultaneous conditions: the first player wins if they meet one, then the next one in player order. A single message only changes the honor of two players through a transfer, which favors the receiver either way; an effect hitting each player is several messages, and the first one meeting a condition ends the game.
- The result (`winnerId`, `victoryCondition`) is stored in `GameState`; no player is active anymore.
- `GameEndedMiddleware` rejects every action once the game has ended (`GameEndedException`), since effect messages are accepted in any place.
- Not done: conceding, notifying the players (Mercure), the stronghold province victory condition (to add to `VictoryService` once provinces can break).

## Decisions taken for `PlayCharacterFromProvince`

- The message carries `cardCost` (fate paid for the card), `fateCost` (fate paid for the additional fate) and `fate` (number of fate placed on the character). No default value: the player always gives them, since cards can modify costs.
- The handler does not refill the province: the player dispatches `FillProvince` themselves after their action. `FillProvince` is accepted during `play_cards_from_provinces` without checking the active player.
- The handler does not give the opportunity to act to the next player: entering play can require other messages from the player (reactions, effects…) beyond `FillProvince`. The player ends their opportunity with `EndOpportunity`, which is not tied to a step.
- The engine does not prevent a player from playing several characters before ending their opportunity.
- No uniqueness check for now.
- `PlayerState::passed` is generic: it is reset when a step in which players can pass begins (enter listener), not at the end of the round.

## Decisions taken for `TriggerActionAbility`

- The message only carries the card: no cost. The player pays costs and resolves effects with other actions, then ends their opportunity with `EndOpportunity`.
- Allowed in any `game` place with the `action_window` place metadata (only `play_cards_from_provinces` for now).
- The card must be where its abilities can be used, as listed by the `trigger_action` transition of its workflow: in play (dynasty, conflict, stronghold), unbroken province, or faceup holding in a province (`Workflow/DynastyCard/TriggerActionGuardListener`). Role cards are not covered.
- Not checked: the presence of an action ability on the card, the once-per-round limit ("Limit X per", "Max X per").
- The owner of the card is checked instead of its controller (control is not modeled).

## Decisions taken for `BowCard` / `ReadyCard`

- Accepted in any game place, from any player of the game (not only the active one), on any card (including an opponent's): costs, effects and framework steps decide who bows or readies what, and the engine does not apply them. This follows architecture points 1 and 2 below, without tying the messages to an ability being resolved.
- Only a ready card can be bowed and only a bowed card can be readied (`Workflow/BowAndReadyGuardListener`, generic for every card workflow, since `bow` / `ready` stay in the same place).
- Cards without `bow` / `ready` transitions (provinces, roles) are rejected by the workflow (`UndefinedTransitionException`).

## Decisions taken for `LoseHonor` / `GainHonor` / `TransferHonor`

- Accepted in any game place, like `BowCard` / `ReadyCard`. The `playerId` of the message is the player whose honor changes (the giver for `TransferHonor`, whose `recipientId` is the receiver): "take 1 honor from that opponent" is a transfer from that opponent.
- The amount cannot be negative (`\InvalidArgumentException`); a player cannot transfer honor to themselves.

## Decisions taken for `BidHonor`

- Players bid simultaneously (any order, once per round): the bid is a turn record of `PlayerState` (`honorBid`), and `honor_bids_confirmed` moves on to `reveal_honor_dials` once every player has bid.
- The secrecy of the bid is not enforced (the `Event` log stores it).

## Next steps

### Step 1.4 (play cards from provinces)

- **Discard a duplicate** (`DiscardDuplicate`): discard a copy of a unique character from hand or province to place 1 fate on the copy in play; the province is then refilled.

### Action abilities

- **Costs and effects of abilities**: `BowCard` / `ReadyCard` exist; no action exists yet to spend fate, lose honor, move fate, sacrifice or dishonor a card. `TriggerActionAbility` is of little use until some of them exist. See the analysis below.
- **Event cards** (`PlayEvent`): an event with an action trigger is played from hand and goes to the discard pile; the current `conflict_card.play_card` transition leads to `in_play`.

#### Messages needed for costs and effects

Analysis of the 488 cards of the fixtures with an `Action:` ability (200 characters, 174 events, 44 attachments, 32 holdings, 25 provinces, 13 strongholds), grouped as generic board manipulations. Counts are approximate (keyword matches).

**Physical status of a card in play**

- `BowCard` / `ReadyCard` (81 / 36): any card type (character, attachment, stronghold). Done.
- `HonorCharacter` / `DishonorCharacter` (50 / 50): one step at a time ("honor it again" = two messages). Status goes in `CardState`.
- `DiscardStatusToken` (18; Soshi Illusionist, Liar's Mask), `MoveStatusToken` (Origami Master, Bayushi Dairu).

**Fate**

Fate moves between many holders: player pool → ring ("spend 1 fate to an unclaimed ring") or → supply ("spend 1 fate"), character → supply ("remove") or → owner's pool (Jade Tetsubō, Yogo Junzo), supply → character ("place"), character → character (Karmic Twist), player → player ("give your opponent 1 fate", Levy), supply → player ("gain 1 fate"), ring → ring or character (Jade Masterpiece, Being and Becoming).

Recommendation: a single `MoveFate(source, target, amount)` with typed holders (player pool, card, ring, supply). Rings are not modeled yet: start with pools, cards and supply.

**Player honor**

- `LoseHonor` (~30): the most common cost; honor goes to the supply. Done.
- `GainHonor`: done.
- `TransferHonor(from, to)` (~9): "give your opponent 1 honor", "take 1 honor from that opponent". Done.

**Moving cards between stacks**

- `SacrificeCard` / `DiscardCard` (29 / ~30): same physical effect (in play → discard pile), kept apart for a readable event log (sacrifice is a cost).
- `ReturnCardToHand` (7; Adept of Shadows, Court Mask, Niten).
- `DiscardFromHand` (chosen card) and `DiscardRandomFromHand` (the engine picks; Kuni Yori, Thunder Guard Elite).
- `DrawConflictCards` exists (19).
- `PutCardIntoPlay` (20): enters play without paying costs, from discard pile, hand, province or deck. Different from `PlayCharacterFromProvince`, which pays.
- `PutCardOnDeck(top|bottom)` (Chrysanthemum Steward, Overhear, Kaiu Siege Force, "put the rest on the bottom"); shuffling uses the existing `Shuffle*Deck`.
- `RemoveFromGame` (rare: Isawa Tadaka, Kyūden Isawa).
- `MoveAttachment(attachment, character)` (Giver of Gifts, Invocation of Ash, Iuchi Rimei, Calling in Favors).

**Provinces and dynasty cards**

- Turn a card in a province faceup / facedown (~48 mentions): check that `RevealDynastyCard` fits; `TurnDynastyCardFacedown` is missing (Governor's Spy).
- `RevealProvince` / `TurnProvinceFacedown` (Border Fortress, Shoshi ni Kie, Untamed Steppe, Saadiyah al-Mozedu).
- `DiscardFromProvince` (Asahina Augur, Bustling Academy, Crippling Taxes), then `FillProvince`, which needs an option to refill faceup (Windswept Yurt, Isawa Eju, A Season of War).
- `MoveProvinceCard` / `SwitchProvinceCards` (Borderlands Fortifications, Asahina Takako, Logistics, Soshi Diviner, Recalled Defenses).

**Information: look, search, reveal**

Search ×14, look at ×21, reveal ×35. The engine does not change the state here but controls what each player sees:

- `LookAtTopCards(stack, n)`: private notification (Shrewd Yasuki, Magnificent Lighthouse, including an opponent's deck).
- `SearchStack(stack)`: the player sees the whole stack (Shosuro Hametsu, Agasha Swordsmith).
- `RevealCard`: public notification.
- `LookAtHand(player)` (Kitsuki Investigator, Perceptive Kitsuki, Bayushi's Whisperers).

The player then sorts the cards with generic messages: `TakeCardIntoHand`, `PutCardOnDeck`, discard.

**Postponed until the conflict phase is modeled**

- `MoveToConflict` / `MoveHome` (32 / 37).
- Duels (34): initiate, honor bid, result.
- Skill and glory modifiers "until the end of the conflict" (the most common effect: ~150 mil/pol mentions, 92 "until the end of the conflict"). Either a generic `AddSkillModifier(card, mil, pol, glory, duration)`, or (preferred, consistent with `CollectFate`) players declare the totals when the conflict is resolved.
- Rings (claim, return, switch, move the contested ring), discarding the Imperial Favor, taking control of a card, switching the conflict type, province strength.

**Architecture points raised by these cards**

1. **Who may send effect messages**: about 30 cards make the opponent act during the player's action ("your opponent selects one", "must choose", "each player chooses"). `NotActivePlayerException` would block them. Effect messages should be accepted from any player while an ability is being resolved (opened by `TriggerActionAbility`, closed by `EndOpportunity`), not only from the active player.
2. **Targets**: most effects target an opponent's card, so messages take any `cardId`, not only the sender's cards.
3. **Randomness and visibility** are the only places where the engine does more than move pieces.
4. **Resetting `CardState` when a card leaves play** (see Model below) becomes mandatory as soon as `SacrificeCard`, `DiscardCard` or `ReturnCardToHand` exist.

**Suggested priorities** for the dynasty phase action window:

1. ~~`BowCard` / `ReadyCard`~~ (done)
2. ~~`LoseHonor`, `TransferHonor`~~ (done)
3. `MoveFate`
4. `HonorCharacter` / `DishonorCharacter` / `DiscardStatusToken`
5. `SacrificeCard` / `DiscardCard`, `DiscardFromHand`
6. Province messages (turn, discard, `FillProvince` faceup)
7. Information messages (look, search, reveal) with `PutCardOnDeck` and `TakeCardIntoHand`

### Conflict phase

Plan, in this order:

1. ~~Action windows with consecutive passes~~ (done, with the draw phase action window).
2. ~~Model and workflow skeleton~~ (done): rings (unclaimed, contested, claimed by a player; fate on them) and the Imperial Favor (holder, side) in `GameState`; the current conflict in `GameState` (attacker, defender, contested ring, type, the province with the ring and the list of attacked provinces, attackers and defenders by card id, characters evaded by covert), cleared in `return_home`; the conflict opportunities in the turn records of `PlayerState`. The `game` places replace the placeholders `t11`…`t27`, with the loop of 3.3 back to the action window following 3.1.
3. `DeclareConflict` / `PassConflict`, with their checks (unclaimed ring, opponent's unbroken province, stronghold province only once 3 provinces are broken, ready attackers of the player, at least one attacker, remaining opportunity of that type), revealing the province and moving the fate of the ring to the attacker.
4. `DeclareDefenders` (possibly none), then the conflict action window (defender first).
5. Resolution: result, unopposed, broken province (and the stronghold province victory condition in `VictoryService`), ring effect, ring claimed, return home.
6. Imperial Favor: glory count, `ClaimImperialFavor(side)`.

State of the skeleton:

- Places: `conflict_phase_action_window` (first player first) leads to `declare_conflict` while a player has a conflict opportunity left, otherwise to `determine_imperial_favor` (`ConflictOpportunitiesGuardListener`). A conflict goes through `react_to_conflict_declaration`, `declare_defenders`, `conflict_action_window`, `determine_result`, `react_to_conflict_result`, `apply_unopposed`, `break_province`, `react_to_broken_province` (where the attacker will also discard from the broken province), `resolve_ring_effects`, `claim_ring`, `react_to_ring_claim`, `return_home`, `conflict_ends`, then back to `conflict_phase_action_window`.
- Transitions applied by a handler, not automatic: `conflict_passed`, `conflict_declared`, `defenders_declared`, `imperial_favor_claimed`. Not automatic yet (TODO in the config): `result_determined` and `glory_counted`, until the players declare their totals.
- Missing enter listeners: `declare_conflict` (next player in player order with a conflict opportunity left), `declare_defenders` (defending player), `conflict_action_window` (defending player first), `resolve_ring_effects` (attacking player if they won, otherwise nobody).
- The automatic steps (`unopposed_applied`, `provinces_broken`, `ring_claimed`, `returned_home`) have no listener yet.
- Rings are set by `start_game`; placing fate on them and returning them belong to the fate phase.

Decisions already taken:

- **Participation** in the conflict is stored in `GameState` (ids of the participating cards), not in `CardState`: a card leaving play will have to be removed from the conflict.
- **Values from players**: each player declares the total skill of their side; an automatic transition waits for both, then the engine computes the winner (ties go to the attacker, a total of 1 or more is needed to win, no winner when both count 0), unopposed and the excess skill. Same for the province strength and the glory count (the engine can add 1 per claimed ring).
- **Conflict opportunities**: two per player and per round. Declaring uses the opportunity of that type; passing (`PassConflict`) uses one opportunity with no type. A conflict can be declared while an opportunity remains and its type has not been declared this round. Turn records: remaining opportunities (a counter, for cards granting additional conflicts) and declared types.
- **Conflicts at multiple provinces** (A War on Two Fronts): the conflict keeps the province with the contested ring and the list of attacked provinces; `DeclareConflict` targets one province, a later effect message adds the other one. The province strength is declared for each province.
- **Discarding from a broken province**: a step with an opportunity for the attacker.
- **Reactions**: players must be able to react often for now; it will be locked once the game has been tested. Reaction windows (every player active, passing final, like `reveal_honor_dials`) after the declaration of the conflict, the result, the broken province and the ring being claimed.

### Game rules not enforced yet

- **Uniqueness**: a player cannot have two unique characters with the same title in play.
- **Automatic refill**: per the glossary, a province emptied of its dynasty card refills automatically. Kept manual for now (`FillProvince` by the player).
- **FillProvince checks**: neither the owner of the province nor the presence of a dynasty card already on it is checked.

### Model

- **Honor not set**: the honor of a player is not checked to be set (`null` before `SetHonor`).

- **Card state when leaving play**: `CardState` (fate on the card) is not reset when a card leaves play; it must be before the card can enter play again (discard, return to hand…). Personal honor status will go there too.
- **Holdings**: a faceup holding in a province is in play; discarding it during the fate phase (`discard_from_provinces`) is not possible with the current `dynasty_card.discard` transition, which starts from `in_play`.
