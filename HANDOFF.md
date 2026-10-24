# HANDOFF

## Current status

Early stage. Implemented: matchmaking, game creation/setup, stronghold province choice, deck shuffling, drawing, filling provinces, the dynasty phase (discarding duplicates is not implemented yet) the honor bid, the reveal of the honor dials, the honor transfer and the card draw of the draw phase (`cards_drawn`), the action window of the draw phase, the conflict phase, the fate phase, the move to the conflict phase (`draw_phase_ended`), running out of cards, the end of the game by honor and the API to send messages (`POST /api/games/{id}/messages`), the secret honor bids and the duels, new ids for the cards of a shuffled stack, the reading API (`GET /api/games/{id}`), the validation and the JSON schemas of the messages (`GET /api/actions`), the actions available to the player (`available_actions`), the interrupts and reactions of players without the opportunity to act, the ability timings of the cards, the version of the state (ETags, optimistic check of the messages, Mercure notification after each change), the log of the game (`GET /api/games/{id}/log`, `app:game:log`), the user accounts and their access tokens, the API of the cards (`GET /api/cards`, `GET /api/cards/{code}`), the import of decks (`POST /api/decks`, EmeraldDB format, deckbuilding rules) the matchmaking queue (`/api/queue`) and the games of a user (`GET /api/self/games`), conceding a game (`ConcedeGame`), the private Mercure topics with a subscriber token per user (`GET /api/self/subscription`, `game_started`), the screen of the queue, the long-poll on the log of a game (`wait`, for `wait_for_update`) the nginx + PHP-FPM development stack, the MCP server with the `wait_for_update` tool and one tool per public message, the MCP tools to find a game and read it (`get_game`, `get_cards`, the queue and the games of the bot) and the prompt of an agent playing through them (`agent/`), the development console in the browser (now `/console`), the skills of a character set by the players (`SetCharacterSkill` / `ResetCharacterSkill`), the web client (`front/`, Vue 3 + Vite + TypeScript) with the way of newcomers to a first game, the guest accounts (`POST /api/guests`), the games against the house bot (`POST /api/bot-games`) and the first version of the bot (`Service/Bot`), the distinction between passing and ending what a player has played (`EndResolution`), the game board of the web client, the player accounts with a username and a password (the API: `POST /api/accounts`, `/api/sessions`, `PATCH /api/self`, and the screens of the web client: sign in, create an account, the account), the preferred clan of an account and its copy of the starter deck of that clan, the automatic refill of the provinces, placing fate on a character (`PlaceFate`) and the settings of a user: the cards drawn or shown as images, the theme of the site (day, night or as the system), sacrificing a card and returning it to hand (`SacrificeCard`, `ReturnCardToHand`) the checks of `FillProvince` and pointing at a card (`PointAtCard`, latest).

Every phase of the round is modeled: the `game` workflow has no placeholder transition left, and a game loops from round to round until a victory condition is met.

## Decisions taken for the dynasty phase

- `collect_fate` and `play_cards_from_provinces` are resolved in player order (active player of `GameState`).
- In `play_cards_from_provinces`, `PassOpportunity` passes: the first to pass gains 1 fate (`PlayCardsFromProvincesOpportunityPassedListener`), the last to pass ends the step.

## Decisions taken for `PassOpportunity`

- Generic pass, allowed in any `game` place with the `opportunities` metadata (`play_cards_from_provinces`, `reveal_honor_dials`, `draw_phase_action_window`). The rules of a step go in listeners of `OpportunityPassedEvent`, which check the game place.
- Passing is final (`PassingRule::Final`, except in action windows): the player is no longer active and is skipped until the end of the step, which ends once every player has passed (`AllPlayersPassedGuardListener`, one `AsGuardListener` attribute per transition).
- Several players can be active at once (`GameState::activePlayerIds`): players acting simultaneously keep their opportunity until they pass. The opportunity goes to the next player only when no player is active anymore.

## Decisions taken for `EndResolution`

- **Problem**: `EndOpportunity` and `PassOpportunity` were available together, and an `EndOpportunity` with no action counted as one: nothing told whether a player was resolving something, nor whether they had passed or acted.
- **Two exclusive messages**: an active player who has done nothing can only pass (`PassOpportunity`); a player who has played something applies its effects with other messages, then ends it (`EndResolution`, renamed from `EndOpportunity`: events recorded before this change cannot be replayed), and cannot pass meanwhile (`ResolutionInProgressException`). `EndResolution` with nothing to end is rejected (`NothingToResolveException`). Both checks are in the `assertAvailable()` of the handlers, so `available_actions` lists one or the other.
- **Actions** (`PlayerState::acted`, set by `OpportunityService::takeAction()`): `PlayActionEvent`, `TriggerActionAbility`, `PlayCharacterFromHand`, `PlayAttachment`, `PlayCharacterFromProvince`. Ending the action ends the opportunity to act (`passToNextPlayer()`: next player, passes reset in action windows), once no duel is being resolved. Several actions before ending are still allowed.
- **Interrupts and reactions** (`PlayerState::resolvingInterruptsAndReactions`, a count: `OpportunityService::startInterruptOrReaction()`): `PlayInterruptEvent`, `PlayReactionEvent`, `TriggerInterruptAbility`, `TriggerReactionAbility`, from any player, active or not. They use no opportunity to act: `EndResolution` ends the last one initiated first (before the action of the player, if any), in any place and during a duel, and changes no opportunity. A player resolving one cannot pass until they end it.
- **Not actions to end**: board manipulations (a `LoseHonor` alone leaves the player free to pass); the phase actions that move the game on themselves (`DeclareConflict`, `PassConflict`, `DeclareDefenders`, bids, totals…); `InitiateDuel`, an effect of the ability that initiates the duel (which is the action or the reaction to end).
- **Not reset when a step begins**: an action cannot outlast its step (the player cannot pass before ending it), and an interrupt or a reaction ends whatever the step.
- **Where the players are**: the view of each player shows `passed`, `acted` and `resolving_interrupts_and_reactions`, public; the console shows them in the player summary, and its `end` command is `end_resolution`.
- **Name**: `EndResolution` rather than `EndEffect`, since the rules call "effects" what an ability does (lasting and delayed effects), not the ability being resolved.

## Decisions taken for the reveal of the honor dials

- `reveal_honor_dials` is a step where every player is active (`RevealHonorDialsEnterListener`), to react to the honor dials being revealed (e.g. Kitsuki Jusai, cards changing a bid by 1), then pass. `honor_dials_revealed` moves on to `transfer_honor` once every player has passed. Passing is final, although a player could react to an opponent's reaction: acceptable since each of these cards reacts once.
- `SetHonorBid` changes the revealed bid on the dial of a player. Like the other effect messages, it is accepted in any place, from any player of the game, on any player. The dial of the player must have been revealed (`NoHonorBidException`); the bid can be 0 (cards decrease it "to a minimum of 0"), not negative.

## Decisions taken for the honor transfer

- `honor_transferred` is automatic: the engine computes the transfer from the recorded bids (`HonorTransferredTransitionListener`), after the changes made with `SetHonorBid` during `reveal_honor_dials`. No player action: cards modifying the transfer itself are handled with the effect messages (`TransferHonor`, `GainHonor`…) in a later window.
- The highest bidder gives the difference to the lowest bidder; nothing happens on equal bids. Every player must have bid (`NoHonorBidException`).
- Reaching 0 or 25 honor during the transfer ends the game before the draw (see the end of the game by honor).

## Decisions taken for the card draw

- `cards_drawn` is automatic: each player draws as many conflict cards as their honor bid (`CardsDrawnTransitionListener`, through `DrawConflictCards` on `transition.bus`), after the changes made with `SetHonorBid`. No player action: cards drawing more are handled with `DrawConflictCards` in a later window. Every player must have bid (`NoHonorBidException`); a bid of 0 draws nothing.
- `cards_drawn` leads to the action window of the draw phase (`draw_phase_action_window`, between 2.5 and 2.6).

## Decisions taken for action windows

- The `opportunities` place metadata is an `Enum/PassingRule`: `Final` for the steps where passing is final (`play_cards_from_provinces`, the special action window 1.4, and `reveal_honor_dials`), `Consecutive` for action windows (`draw_phase_action_window` for now).
- In an action window, `EndResolution` ends the action of the player: it resets the passes of every player (`OpportunityService::passToNextPlayer()`), then gives the opportunity to the next player. `PassOpportunity` is unchanged: the window closes once every player has passed since the last action (`AllPlayersPassedGuardListener`).
- `FirstPlayerOpportunityEnterListener` activates the first player and resets the passes (also used by `play_cards_from_provinces`); `AllPlayersOpportunityEnterListener` does the same with every player (`reveal_honor_dials`, reaction windows). The conflict action window (3.2.2) will activate the defender instead.

## Decisions taken for running out of cards

- `Service/DrawDeckService::getTopCard()` applies the rule ("Running Out of Cards" in the glossary) to both decks: when a player attempts to draw a conflict card (`DrawConflictCards`) or to fill a province (`FillProvince`) from an empty deck, they lose 5 honor, the discard pile goes back into the deck (`put_back` transition of the conflict and dynasty card workflows), then the deck is shuffled. The rule applies on the attempt, not when the deck becomes empty.
- `DrawConflictCards` draws the cards one by one, so a draw that empties the deck goes on in the new deck.
- When the discard pile is empty too, the player still loses 5 honor and draws as many cards as possible (the province stays empty); no exception.
- Reaching 0 honor this way ends the game (see the end of the game by honor).

## Decisions taken for the end of the game by honor

- Honor stops at 0 (a player cannot lose honor they do not have) and can exceed 25. No exception: the action that reaches a limit is kept and ends the game.
- The victory conditions are read from the state (`Service/VictoryService`), not detected where honor changes: `end_game` is an automatic transition from every place of a started game (not `join_game`) to `game_ended`, guarded on a victory condition being met. It is checked after every message and between automatic transitions.
- Automatic transitions have a `priority` metadata (0 by default); `end_game` has 100, so it wins over the next step (e.g. `cards_drawn` after a transfer leaving a player with no honor). Two enabled automatic transitions with the same highest priority throw a `LogicException`, so a conflict shows up instead of being settled by the declaration order.
- The game ends at the end of the message, not in the middle of it: a draw emptying the deck goes on after the loss of 5 honor.
- Simultaneous conditions: the first player wins if they meet one, then the next one in player order. A single message only changes the honor of two players through a transfer, which favors the receiver either way; an effect hitting each player is several messages, and the first one meeting a condition ends the game.
- The result (`winnerId`, `victoryCondition`) is stored in `GameState`; no player is active anymore.
- `GameEndedMiddleware` rejects every action once the game has ended (`GameEndedException`), since effect messages are accepted in any place.
- Breaking the stronghold province of a player eliminates them (`VictoryCondition::StrongholdProvinceBroken`), like having no honor: the game ends right after `provinces_broken`.

## Decisions taken for the conflict phase

- **Places**: `conflict_phase_action_window` (first player first) leads to `declare_conflict` while a player has a conflict opportunity left, otherwise to `determine_imperial_favor` (`ConflictOpportunitiesGuardListener`). A conflict goes through `react_to_conflict_declaration`, `declare_defenders`, `conflict_action_window`, `determine_result`, `react_to_conflict_result`, `apply_unopposed`, `break_province`, `react_to_broken_province`, `resolve_ring_effects`, `claim_ring`, `react_to_ring_claim`, `return_home`, `conflict_ends`, then back to `conflict_phase_action_window` (3.3). Transitions applied by a handler: `conflict_passed`, `conflict_declared`, `defenders_declared`, `imperial_favor_claimed`; every other one is automatic.
- **Reactions**: players must be able to react often for now; it will be locked once the game has been tested. Reaction windows (`react_to_*`: every player active, passing final, like `reveal_honor_dials`) after the declaration of the conflict, the result, the broken province and the ring being claimed.
- **Participation** in the conflict is stored in `GameState::conflict` (ids of the participating cards), not in `CardState`.
- **Values from players**: the skill totals, the province strength and the glory count are declared by the players (see below); the engine applies the rules to them.
- **Conflict opportunities**: two per player and per round (turn records of `PlayerState`: a counter, for cards granting additional conflicts, and the declared types). Declaring uses the opportunity of that type; passing (`PassConflict`) uses one opportunity with no type. A conflict can be declared while an opportunity remains and its type has not been declared this round.
- **Conflicts at multiple provinces** (A War on Two Fronts): the conflict keeps the province with the contested ring (`provinceId`) and the list of attacked provinces; each attacked province has its own strength and is broken separately.
- **Rings** are set by `start_game` (unclaimed, no fate).

- `declare_conflict` gives the opportunity to the next player in player order with a conflict opportunity left (`DeclareConflictEnterListener`): the first player at the beginning of the phase, then the player after the one who used the last conflict opportunity (`GameState::lastConflictPlayerId`, reset by `conflict_phase_began`).
- `DeclareConflict` carries the ring, the type, the province, the attackers and the characters evaded by covert (`evadedIds`). Checked: active player, unclaimed ring, province of an opponent (who becomes the defending player), not broken, the stronghold province only once 3 other provinces of its owner are broken (`Service/ConflictService`), at least one attacker, attackers are ready characters in play of the player, evaded characters are characters in play of the defending player, and an opportunity of that type is left (`PlayerState::useConflictOpportunity()`). Not checked: the covert keyword and the number of evaded characters, other eligibility restrictions from cards.
- The fate on the ring goes to the attacking player's pool; a facedown province is revealed (`province_card.reveal`) before the reactions to the declaration (`react_to_conflict_declaration`).
- `PassConflict` uses one opportunity with no type, then the conflict phase action window opens again through `conflict_ends`.
- The conflict (`GameState::conflict`) is cleared by `conflict_ended` (3.3), so the contested ring stays contested until then.
- The controller of a card is its owner everywhere (control is not modeled).
- `DeclareDefenders` is sent by the defending player, the only active player in `declare_defenders` (`DefendingPlayerOpportunityEnterListener`, which also gives the first opportunity of `conflict_action_window` to the defending player). Defenders are ready characters in play of the defending player, not evaded by covert; the list holds at least one (`Count(min: 1)`): declaring no defender is a message of its own, `DeclareNoDefenders` (log: `{player} declares no defenders`). Both go through `Service/DefendersService` (the defending player is active, the defenders are recorded, the conflict action window opens).
- **Result** (3.2.3): each player of the conflict declares the total skill of their side (`DeclareConflictSkill`, once each, in any order: no longer available once declared); `result_determined` is automatic once both are declared (`ResultDeterminedGuardListener`), and `ConflictState::determineWinner()` applies the rules (ties to the attacker, 1 skill needed to win, no winner at 0 / 0).
- **Province strength**: when the game enters `determine_result`, each attacked province gets its printed strength, plus the strength bonus of the stronghold for the stronghold province (`DetermineResultEnterListener`). The defending player replaces it with `DeclareProvinceStrength`, once per province (`ConflictState::declaredStrengthProvinceIds`: no longer available once every attacked province is declared), which must be sent before the skill totals (the result is determined as soon as both are known).
- **Unopposed** (3.2.4): the defending player loses 1 honor when the attacking player wins with no defender left in the conflict (`UnopposedAppliedTransitionListener`).
- **Breaking provinces** (3.2.5): each attacked unbroken province whose strength is at most the excess skill of the winning attacker breaks (`province_card.break`); the broken ones are recorded in `ConflictState::brokenProvinceIds`. When none is broken, `react_to_broken_province` is left at once for `resolve_ring_effects` (`no_province_broken`, automatic, `NoProvinceBrokenGuardListener`): there is nothing to react to, and the step would show "Province broken" to the players.
- **Discarding from a broken province**: `DiscardCard` (any card, any player, any place: `discard_card` transition of the conflict and dynasty card workflows; the dynasty one now also starts from the provinces, so a faceup holding can be discarded), then `FillProvince`, during `react_to_broken_province`.
- **Ring effects** (3.2.6): in `resolve_ring_effects`, the attacking player who won is the only active player and the others count as having passed (`ResolveRingEffectsEnterListener`); when the attacking player did not win, every player has passed and the step ends at once. The attacking player resolves the effect with effect messages, then passes: Air `TransferHonor` / `GainHonor`; Earth `DrawConflictCards` + `DiscardRandomCardFromHand` (the engine picks the card); Fire `HonorCharacter` / `DishonorCharacter`; Water `ReadyCard` / `BowCard`; Void `RemoveFate`. The engine does not check that the effect matches the ring.
- **Personal honor** is `CardState::personalHonor` (`Enum/PersonalHonor`); honoring an honored character or dishonoring a dishonored one is rejected (`PersonalHonorException`). Its effect on skill and glory is part of the totals declared by the players.
- **Ring claimed** (3.2.7): the winner claims the contested ring (`RingClaimedTransitionListener`); with no winner the ring is back in the unclaimed pool once the conflict is cleared (`conflict_ended`).
- **Return home** (3.2.8): ready participants bow, then the participant lists are emptied (`ReturnedHomeTransitionListener`).
- **Leaving play** (`Workflow/LeavePlayListener`, on `workflow.leave` for any card transition from `in_play` to another place): the controller of an honored / dishonored character gains / loses 1 honor, the `CardState` is reset (fate back to the token pool, ordinary status), the attachments on a character are discarded (ancestral ones too: the keyword is not modeled) and the character is removed from the conflict. This closes the "card state when leaving play" point of Model.
- **Glory count** (3.4.1): each player declares the glory of their ready characters with every modifier (`DeclareGloryCount`, turn record `PlayerState::gloryCount`, once each, in any order: no longer available once declared); `glory_counted` is automatic once every player has declared, and adds 1 per claimed ring. The highest total wins (`GameState::gloryCountWinnerId`), nobody on a tie.
- **Imperial Favor** (3.4.2): the winner of the glory count is the only active player in `claim_imperial_favor` and sends `ClaimImperialFavor(side)` (`imperialFavorHolderId`, `imperialFavorSide` in `GameState`); on a tie, `imperial_favor_unclaimed` (automatic) leaves the favor as it is. The effect of the favor on the next round (+1 skill) is part of the totals declared by the players.
- `GameState::currentConflict()` returns the conflict or throws: it is not named `getConflict()`, which the serializer would call as an accessor.

## Decisions taken for the fate phase

- The framework steps are automatic transitions whose listener does the work: `characters_discarded` (4.2, in player order from the first player, the engine picks the order of the characters), `fate_removed` (4.3), `fate_placed_on_rings` (4.4), `cards_readied` (4.5, every sideways card that can be readied, strongholds included), `rings_returned` (4.7), `first_player_token_passed` (4.8), `fate_phase_ended` (4.9, resets the turn records; formerly `t36`).
- `fate_phase_action_window` is the action window between 4.4 and 4.5 (first player first, consecutive passes).
- `discard_from_provinces` (4.6): the faceup cards of broken provinces are discarded on entering the step (`DiscardFromProvincesEnterListener`), and the provinces refilled; then every player is active to discard faceup dynasty cards from their unbroken provinces (`DiscardCard`, which refills them), and passes. The engine does not check that a player only discards their own faceup cards.
- Characters discarded with no fate go through `LeavePlayListener` (honor of honored / dishonored characters, attachments discarded).

## Decisions taken for playing cards from hand

- `OpportunityService::assertCanTakeAction()` checks that the place is an action window and that the player is active (`TriggerActionAbility` uses it too). Characters and attachments cannot be played from hand in places marked `play_from_hand: false` (`play_cards_from_provinces`, the 1.4 special action window; `CannotPlayFromHandException`).
- `PlayCharacterFromHand` carries the costs and fate like `PlayCharacterFromProvince`, and `intoConflict`: the character then participates on its controller's side, which requires a conflict in which the player takes part (only `conflict_action_window` is an action window during a conflict).
- `PlayAttachment` carries the target: a character in play of any player, or a province. Attachment restrictions printed on cards are not checked.
- `PlayActionEvent` plays an action event: like `TriggerActionAbility`, it requires an action window, the active player and no duel (`assertCanTakeAction()`). Interrupt and reaction events are played with `PlayInterruptEvent` and `PlayReactionEvent` (see the decisions taken for interrupts and reactions). The event goes to the discard pile once its cost is paid; its effects are resolved with other messages.
- In every case the player keeps the opportunity to act and ends their action with `EndResolution`.
- `MoveToConflict` / `MoveHome` are effect messages, accepted in any place during a conflict from any player: a character in play joins the side of its controller (bowed or ready), or leaves the conflict.
- Not done: the Limited keyword, uniqueness, "max X per conflict" restrictions.

## Decisions taken for interrupts and reactions

- **Problem**: `PlayEvent` (now `PlayActionEvent`) required the active player, but interrupts and reactions are initiated by any player when their triggering condition occurs (glossary, Interrupts and Reactions), not during an opportunity to act. During a duel only the player who initiated it is active: the opponent could not play Stay Your Hand against a duel targeting their character.
- **One message per timing** (action, interrupt, reaction), for events and for the abilities of cards: `PlayActionEvent` / `TriggerActionAbility` (action window, active player, no duel: `assertCanTakeAction()`), `PlayInterruptEvent` / `TriggerInterruptAbility`, `PlayReactionEvent` / `TriggerReactionAbility`. The timing is in the type of the message so that the event log and `available_actions` show it. The order of the opportunities to act stays enforced for actions: an opponent cannot play an action event during another player's opportunity.
- The interrupt and reaction messages behave the same way for now: accepted in any game place, during a duel too, from any player of the game, like the board manipulations: the engine does not know the triggering conditions. They change no opportunity to act (no pass is reset, no player is activated); the player ends each one with `EndResolution` (see the decisions taken for `EndResolution`).
- Checked: the event is in the hand of the player and is an event (`HandService::playEvent()`, shared by the three event messages); the card of the ability belongs to the player and is where its abilities can be used (`Service/AbilityService::trigger()`, shared by the three ability messages: `trigger_ability` transition, renamed from `trigger_action`). The timing of an event is checked (see the decisions taken for the ability timings), not the one of the abilities of cards. Not checked: the triggering condition, the limit per round.
- `PlayEvent` is renamed `PlayActionEvent` (API type `play_action_event`): events recorded before this change cannot be replayed.
- Not modeled: the interrupt and reaction windows of the rules (first player first, consecutive passes). The `react_to_*` places of the conflict stay as windows where the game waits for the players to react.

## Decisions taken for the ability timings

- **Data**: `LogicalCard::abilityTimings` (`text[]` of `Enum/AbilityTiming`: action, interrupt, reaction, forced or not) and `abilityGrantedToAttached` (bool), stored in every card fixture (`ability_timings`, `ability_granted_to_attached`; the fixtures are no longer imported from an external source). Derived once from the bold timing commands of the text (`<b>Action:</b>`, `<b>Forced Reaction:</b>`…); a card can have several timings. Persistent abilities are not tagged.
- **Granted abilities**: an ability quoted in the text is a timing of the card even when the card grants it to the character it is attached to. The 16 attachments whose text says "Attached character gains" (Duelist Training, Ofushikai, Sturdy Tetsubo…) are flagged `abilityGrantedToAttached`: the player of the card they are attached to triggers these abilities (`AbilityService::trigger()`, through the stack the attachment has joined), not the owner of the attachment. The other quoted abilities belong to the card itself and are not flagged: holdings ("it gains"), Dire characters, and characters that can be played as an attachment (Togashi Kazue, Promising Youth…), treated as if they always had the ability.
- **Events**: every event has exactly one timing. `PlayActionEvent`, `PlayInterruptEvent` and `PlayReactionEvent` reject an event without an ability of their timing (`WrongAbilityTimingException`, 409), and are available only when the player has such an event in hand (`HandService::assertEventInHand()`, `NoEventInHandException`).
- **Abilities of cards**: `Trigger*Ability` does not check the timings: cards gain abilities (attachments, "gains" effects) and lose them (blanking), which the engine does not apply. The tags are information for the clients.

## Decisions taken for `PlayCharacterFromProvince`

- The message carries `cardCost` (fate paid for the card), `fateCost` (fate paid for the additional fate) and `fate` (number of fate placed on the character). No default value: the player always gives them, since cards can modify costs.
- The handler refills the province once the fate is placed on the character (see the decisions taken for the automatic refill).
- The handler does not give the opportunity to act to the next player: entering play can require other messages from the player (reactions, effects…) beyond `FillProvince`. The player ends their action with `EndResolution`, which is not tied to a step.
- The engine does not prevent a player from playing several characters before ending their opportunity.
- No uniqueness check for now.
- `PlayerState::passed` is generic: it is reset when a step in which players can pass begins (enter listener), not at the end of the round.

## Decisions taken for the automatic refill of the provinces

- **Problem**: the players refilled their provinces themselves (`FillProvince`) and a human forgot it: in a game against the agent, two provinces stayed empty, which nothing showed.
- Learn to play, "Draw and Refill": "If a player's province is empty, they must immediately refill it"; glossary, Holding: "While a holding remains on a province, that province is not refilled". The engine refills a regular province, face down from the dynasty deck of its owner, once a dynasty card has left it and no dynasty card remains on it (`Service/ProvinceRefiller::refillIfEmpty()`). A conflict card on a province (an attachment) does not keep it from being refilled, and its discard does not refill it.
- **Explicit calls, not a workflow listener**: the stack a card has left is lost once the transition is applied, and checking every empty province after any dynasty transition would make a player whose deck and discard pile are empty lose 5 honor again and again (running out of cards). The callers keep the stack before the transition: `PlayCharacterFromProvinceHandler` (after the fate is placed on the character, as in 1.4: the log reads in the order of the rules), `DiscardCardHandler` (dynasty cards only), `DiscardFromProvincesEnterListener` (once every faceup card of a broken province is discarded). A new message moving a dynasty card out of a province (`DiscardDuplicate`, `MoveProvinceCard`…) calls it too.
- `FillProvince` stays, for setup (`SetupGameTransitionListener`) and the effects that fill a province; its handler shares `ProvinceRefiller::fill()`. The web client still offers "Fill this province" on an empty province (deck and discard pile empty, then refilled later); its hints no longer ask to refill.
- Running out of cards applies as before: refilling from an empty deck loses 5 honor and shuffles the discard pile, the card just discarded included.

## Decisions taken for `PlaceFate`

- **Problem**: no message added fate to a card in play, so an effect placing fate on a character could not be applied (seen while playing against the agent).
- `PlaceFate(card, amount)` places fate from the general token pool (the supply) on a character in play (`ConflictService::assertCharacterInPlay()`), `amount` ≥ 1. Accepted in any game place, from any player of the game, on any character, like `RemoveFate`; logged by `FatePlacedOnCardEvent`, the event of the fate placed when a character is played.
- Narrow on purpose: fate moving from a fate pool, a ring or another card waits for `MoveFate` (see the analysis of fate below). Meanwhile a player paying fate from their pool onto a character sends nothing for the pool (the engine has no `SpendFate` message).
- Web client: "Place 1 fate" on every character in play (`cardActions.ts`); console: `/place-fate <card> <amount>`.

## Decisions taken for `TriggerActionAbility`

- The message only carries the card: no cost. The player pays costs and resolves effects with other actions, then ends their action with `EndResolution`.
- Allowed in any `game` place with the `action_window` place metadata (only `play_cards_from_provinces` for now).
- The card must be where its abilities can be used, as listed by the `trigger_ability` transition of its workflow (shared with `TriggerInterruptAbility` and `TriggerReactionAbility`, through `Service/AbilityService`): in play (dynasty, conflict, stronghold), unbroken province, or faceup holding in a province (`Workflow/DynastyCard/TriggerAbilityGuardListener`). Role cards are not covered.
- Not checked: the presence of an action ability on the card, the once-per-round limit ("Limit X per", "Max X per").
- The owner of the card is checked instead of its controller (control is not modeled).

## Decisions taken for `SacrificeCard` / `ReturnCardToHand`

- Accepted in any game place, from any player of the game, on any card, like `DiscardCard`: the engine does not know which ability is being resolved, nor who controls a card.
- `SacrificeCard` is apart from `DiscardCard` although the card goes to the same discard pile: a sacrifice is not a discard (glossary), and the log says which it is. Only a card in play can be sacrificed: a character or an attachment in play, a faceup holding in a province (`Workflow/DynastyCard/SacrificeGuardListener`); the province is then refilled.
- `ReturnCardToHand` returns a conflict card in play to the hand of its owner; a dynasty card goes to the dynasty discard pile instead ("If a dynasty card would enter a player's hand of conflict cards, it is put into its owner's dynasty discard pile instead"), through a `return_to_hand` transition of its own, so that the log says why.
- No `DiscardFromHand`: `DiscardCard` already discards a chosen card from hand.
- The card dialog of the web client offers them beside "Discard" (`CardMenu::removals`): return to hand and sacrifice for a card in play, sacrifice for a holding in a province.

## Decisions taken for the checks of `FillProvince`

- Only a regular province without a dynasty card on it can be filled (`CannotFillProvinceException`; a card that is not a province: `WrongCardTypeException`): no dynasty card goes on the stronghold province, and a holding keeps a province from being refilled.
- The owner of the province is not checked: any player may fill any province, like the other effects, and the card comes from the deck of the owner of the province.

## Decisions taken for `BowCard` / `ReadyCard`

- Accepted in any game place, from any player of the game (not only the active one), on any card (including an opponent's): costs, effects and framework steps decide who bows or readies what, and the engine does not apply them. This follows architecture points 1 and 2 below, without tying the messages to an ability being resolved.
- Only a ready card can be bowed and only a bowed card can be readied (`Workflow/BowAndReadyGuardListener`, generic for every card workflow, since `bow` / `ready` stay in the same place).
- Cards without `bow` / `ready` transitions (provinces, roles) are rejected by the workflow (`UndefinedTransitionException`).

## Decisions taken for `PointAtCard`

- A message that changes nothing in the game: it only writes a log entry, "{player} points at {card} in {stack}" (`CardPointedAtEvent`), so that a player can show a card to the opponent (a card of a discard pile, the target of an ability).
- Accepted in any game place, from any player of the game, on a card of any player, provided its stack is seen by every player (`View/Visibility::isStackVisibleTo()`): not a deck nor a hand (`HiddenCardException`, 409), since the opponent could not tell which card is meant.
- A face-down card (a dynasty card or a province) can be pointed at: its id is public, its code is masked in the log as anywhere else (the default visibility of a card param), so the opponent reads "a facedown card in john's province" and the client can find it on the board by its id.

## Decisions taken for `SetCharacterSkill` / `ResetCharacterSkill`

- The players record the current military or political skill of a character, every modifier applied (an event, an attachment, another effect), so that every player sees it: the engine still does not compute skills, and the totals of a conflict stay declared (`DeclareConflictSkill`).
- `SetCharacterSkill` (card id, `type`: `ConflictType`, `skill` ≥ 0) replaces the value set before for that skill; `ResetCharacterSkill` (card id) clears both, back to the printed skills. Accepted in any game place, from any player of the game, on any character in play (`ConflictService::assertCharacterInPlay()`), like `HonorCharacter`.
- The values are absolute, not modifiers: the players apply the effects, as for the declared totals. Not a physical position: `CardState::militarySkill` / `politicalSkill` (null: printed skill), cleared when the character leaves play (`LeavePlayListener` resets the `CardState`). The view of a card has `military_skill` / `political_skill` (null when not set), the log `CharacterSkillSetEvent` / `CharacterSkillResetEvent`.
- Console: `/set-skill <card> <military|political> <value>` and `/reset-skill <card>`; a card with a set skill shows `mil 5 (set)` among its marks.

## Decisions taken for `LoseHonor` / `GainHonor` / `TransferHonor`

- Accepted in any game place, like `BowCard` / `ReadyCard`. The `targetPlayerId` of the message is the player whose honor changes (`TransferHonor` has a `giverId` and a `recipientId`): "take 1 honor from that opponent" is a transfer from that opponent.
- The amount cannot be negative (`\InvalidArgumentException`); a player cannot transfer honor to themselves.

## Decisions taken for `BidHonor`

- Players bid simultaneously (any order, once per round): the bid is secret (`PlayerState::pendingHonorBid`), and `honor_bids_confirmed` moves on to `reveal_honor_dials` once every player has bid.
- Two fields, so that the secrecy only depends on the state (the reading API shows the pending bid to its owner only): `honor_bids_confirmed` reveals the dials by copying the pending bid into `honorBid` (`HonorBidsConfirmedTransitionListener`). `honorBid` is the honor bid of the rules (the current setting of the dial): `SetHonorBid`, the honor transfer and the card draw use it.
- The revealed bid stays as a reference until the next bid (D.4): it is not a turn record, and entering `honor_bid` clears both fields (`HonorBidEnterListener`). Duels will use the same fields.
- The `Event` log stores the bid: the secrecy is enforced by the reading API, not in the database.

## Decisions taken for the duels

- **Not a place of the `game` workflow**: a duel is initiated from an action window (or a reaction window) and returns to it (D.9), which a state machine cannot express without duplicating the duel places for every window. It is `GameState::duel` (`DuelState`), from `InitiateDuel` to `EndDuel`; its steps are read from the state (`atBidStep()`, `atStatisticStep()`, `resolved`).
- `InitiateDuel` (type, `challengerIds`, `challengedIds`): allowed in any place with opportunities, from the active player, when no duel is being resolved. Checked: characters in play, the challengers controlled by the player, the challenged characters by a single opponent; during a conflict, every character participates. Not checked: the dash skill, the "this character initiates" ability.
- **Honor bid** (D.3, D.4): `BidHonor` is the same message as in the draw phase, accepted from the players involved while the duel is at its bid step. A new bid begins for them (`startHonorBid()`); the dials are revealed once both have bid (`DuelHonorBidPlacedListener`, on `HonorBidPlacedEvent`), so the duel bid is the honor bid of reference afterwards.
- **Statistic and result** (D.5 to D.7): each player involved declares the dueling statistic of their side with every modifier (`DeclareDuelStatistic`, once each, in any order: no longer available once declared). Once both are known, the honor is transferred (`Service/HonorBidService`, shared with the draw phase) and the engine adds the bids: the higher total wins, nobody on a tie (`winnerId` / `loserId`, players). The transfer happens after the declarations rather than right after the reveal, so the bids changed by cards since the reveal (`SetHonorBid`) count.
- **End** (D.8, D.9): the players apply the consequences with effect messages, then a player involved ends the duel (`EndDuel`). Until then, no action window is open (`assertCanTakeAction()`), and `PassOpportunity` / `EndResolution` of an action are rejected (`DuelInProgressException`); interrupts and reactions stay allowed, from any player (`PlayInterruptEvent`, `PlayReactionEvent`, `TriggerInterruptAbility`, `TriggerReactionAbility`).

## Decisions taken for the API to send messages

- A single endpoint for every message (`POST /api/games/{id}/messages`, `{"type", "payload"}`), built on `ActionMessageRegistry` and the serializer, like the replay of events: a new message is exposed with no API code.
- `#[AppAction(public: false)]` hides the platform messages (`CreateGame`, `JoinGame`) and those the engine sends itself (`SetHonor`, the starting honor, during setup: a player has no reason to set their honor afterwards); every other action message is public by default.
- `playerId` is always the player sending the message, which the API sets to the authenticated player: a player cannot act for another one (e.g. pass for the active player). The payload cannot hold `game_id` nor `player_id` (400), even with the right values. The player an effect applies to is a separate field: `targetPlayerId` (`LoseHonor`, `GainHonor`, `GainFate`, `LoseFate`, `SetHonor`, `SetHonorBid`, `DrawConflictCards`, `ShuffleConflictDeck`, `ShuffleDynastyDeck`, `DiscardRandomCardFromHand`), `giverId` / `recipientId` (`TransferHonor`); `FillProvince` uses the deck of the owner of the province. The engine sends the messages of the setup and of the draw with the same player as sender and target. Events stored before this change cannot be replayed.
- `GameLockMiddleware` serializes the actions on a game with a pessimistic lock on its row, held until the end of the transaction.
- The response is empty (204): the client will read the new state with the reading API. No optimistic check of the version of the state seen by the client yet.
- Errors of the payload carry `violations` (`property_path` in snake case, `message`): 400 `invalid_payload` for its structure (missing, unknown or reserved field, wrong type: the serializer collects every error), 422 `validation_failed` for its values (constraints of the Validator).
- The user is matched to a player through `Player::user` (see the decisions taken for the user accounts).

## Decisions taken for the user accounts

- Humans (web client, OAuth2 later) and AI agents both play. The engine cannot prove who plays behind a token: the honest way is made the easiest, and the label "bot" depends on the type of the token, not on goodwill. A human never gets an API key; to let an agent play, they create a bot account they own, which alone gets API keys (`AccessToken` refuses an API key to a human, `User` requires a human owner for a bot). A human helped by an AI while playing is a matter of the terms of use, not of authentication.
- The `playerId` is not a credential: it is public (view, log), so an opponent could act for the player.
- `User` (`users`): id (the user identifier), name, type (human or bot), owner (the human responsible for a bot). Roles: `ROLE_USER`, plus `ROLE_BOT` for a bot.
- `AccessToken` (`access_tokens`): user, SHA-256 hash of the token (unique; a random token of high entropy needs no slow hash, and the token is found by its hash), type (session or API key). No expiry nor revocation date yet: revoking is deleting.
- `AccessTokenHandler` loads the token with its user in a single query and gives the user to the badge, so the user provider (Doctrine entity, `property: id`) does not query again; the firewall is stateless. The view of a game is read with two queries (token and user, then the game).
- Fixtures (`UserFixtures`): humans `john` / `jane` with a session token, bots `akodo-bot` / `hida-bot` owned by john with an API key; the tokens are in clear in `UserFixtures::TOKENS` (used by the tests of the controllers). `app:candidate:create` checks that the user exists.
- `Player::user` (many to one) and `Candidate::user` (one to one: a user waits in the queue once) are associations to `User`. `JoinGame` keeps the id of the user (messages hold ids), its handler loads the user (`UserNotFoundException`). `Player::getUserId()` reads the id through the proxy, so the view of a game does not load the users; the view and the log still name a player by the id of its user (`user_id`).
- Console commands: `app:user:create <id> <name> [--bot --owner=<id>]`, `app:token:create <user> [--api-key]` (shows the token once), `app:token:revoke <user>` (deletes every token of the user). `Security/AccessTokenIssuer` generates the token (`l5r_` + 64 hex characters: 256 random bits from `random_bytes()`, not from the random generator of a game; the prefix lets secret scanners recognize a leaked token) and persists it; the caller flushes. It will be shared with the endpoints issuing tokens.
- The test scenario (`GameBuilder`) gives each player the user of the fixtures named after its alias (john, jane…), else a new human it persists with the game.

**Rejected**
- The `playerId` received when a game is created as the bearer token: public, and no account to link a later login to.
- API keys for humans: an agent would play under a human account without anyone knowing.

## Decisions taken for the validation and the schemas of the messages

- **The message is the single source of truth.** The values of a message are checked by constraints of the Validator on the arguments of its constructor (`#[Assert\PositiveOrZero]`, `#[Assert\Range]`, `#[Assert\Count]`), checked by the `validation` middleware of the bus, first in the chain (before the lock and the transaction), whatever the origin of the message (API, console, replay). The handlers no longer check them; they keep the checks that need the state, or several fields (`TransferHonor` to themselves).
- **The JSON schema of the payload is built from the message** (`Service/Api/MessageSchemaFactory`, draft 2020-12): the types of the constructor (TypeInfo, with the PHPDoc), the constraints, the `#[Description]` attribute of each field and the class doc comment of the message (the fields quoted with backticks are converted to snake case). `game_id` and `player_id` are left out, `additionalProperties` is false. A type or a constraint the factory cannot translate throws a `LogicException`, so that the schema never accepts more than the engine.
- **Snapshot of the contract**: `bin/console app:message-schemas:dump` writes one file per public message in `schemas/messages`, committed; a test fails when they differ from the built schemas, so that every change of the contract shows in the diff. Every public message and every field must be described (test), for the clients of the API, AI agents included.
- **Catalogue**: `GET /api/actions` (`ApiActionsController`) returns the schemas of the public messages by type. It depends on no game: public (no token) and cacheable.
- The schema does not validate the payloads (no JSON schema validator): the serializer and the Validator do, and the schema is derived from the same rules.

## Decisions taken for the random generator

- Each game has its own `Random\Engine\Xoshiro256StarStar` (`Game::getRandomizer()`), seeded by the `seed` of `CreateGame` (32 bytes, hex encoded; `CreateGame::withRandomSeed()` draws it where the game is created: `AutomaticGameLauncher`, `app:game:create`). The seed is in the recorded event, so replaying the event log gives the same draws.
- The state of the engine is serialized in the `random_engine` column of the game, written back before each flush (`Game::saveRandomEngine()`, `PreFlush` lifecycle callback); the seed itself is not stored.
- Every draw goes through it: the shuffles (`StackShuffler`, from the ordered cards so that the result does not depend on the order of the collection), the first player, `DiscardRandomCardFromHand` (from the ordered hand) and the ids of the cards and stacks created during the game (`Game::generateId()`, UUID v4 drawn from the generator).
- Player order: `start_game` fixes it from the order of arrival, so that a replay draws the same first player. Each player has a `joinDate` (to the second: the players matched together join in the same second) and a `joinRank` (0, 1…, set by `Game::addPlayer()`), which orders the `players` collection of the game.
- Events recorded before this change (`CreateGame` with no seed) cannot be replayed.

## Decisions taken for the ids of shuffled cards

- Any card whose id has been seen (in play, in a discard pile, revealed) and that goes back into a deck which is then shuffled could be tracked through its id. Rule: a card gets a new id when the players physically lose track of it, that is when its stack is shuffled (later also secret rearrangements, e.g. Governor's Spy). Other moves keep the id: a character returned to hand or put on top of the deck without a shuffle is still known at a real table. Hidden stacks are sent as counts by the reading API (decks, opponent's hand).
- `Service/StackShuffler::shuffle(Stack)` (used by `ShuffleConflictDeck`, `ShuffleDynastyDeck` and `DrawDeckService`) draws the new order from the ordered cards, deletes the cards (removed from the collections of the stack, player and game, then `remove` through the ORM) and creates them again from the bottom to the top with `CardFactory::createCard()` (same logical card, player, stack and orientation, new id from `Game::generateId()`, fresh state). `Stack::shuffle()` is gone.
- Rejected: updating the primary key with DBAL (the unit of work keeps the old ids: the positions set by the shuffle would be silently lost at the flush, and the collections of `Stack` / `Player` would hold stale objects); a separate public id (a second notion of id to carry in messages, state and `CardRepository`).
- A card in a deck has no state worth keeping (`CardState` is reset when it leaves play); code holding a card of the deck across a shuffle must read it again from the stack (see `DrawDeckServiceTest`, `FillProvinceHandlerTest`).
- `GameBuilder::seed()` fixes the seed of the random generator in tests.

## Decisions taken for the reading API

`GET /api/games/{id}` (`ApiGamesController`, replacing the scaffold; the problem details helper is shared with the messages API through `ProblemResponseTrait`): the state of the game as the authenticated player sees it. Same errors as the messages API (problem details: 401, 403 not a player of the game, 404). A read goes neither through the bus nor through `GameLockMiddleware`.

**Scope**
- Cards are sent by code only: the client knows the cards.
- The messages available to the player: `available_actions` (see the decisions taken for the available actions).
- The game is loaded in a single query (`GameRepository::findWithCards()`, fetch joins of the players, stacks, cards and logical cards, read by the available actions), checked by a test counting the queries of a game well under way.
- Spectators: out of scope.

**Design**
- A view built for the viewing player: `View/GameViewFactory::create(Game, Player $viewer)` returning readonly view DTOs (`View/Model`: `GameView`, `PlayerView`, `StackView`, `CardView`). The rings, the conflict and the duel are the public `RingState` / `ConflictState` / `DuelState` themselves. A stack has its type (snake case name of `StackType`), its count and its cards from the bottom to the top, or `null` when hidden; a card has its id, its code (`null` when hidden), its orientation, its fate and personal honor. The stacks of a player are sorted by type, then id. The visibility rules are written once, in the factory, and tested with `$this->scenario()`.
- **Visibility only depends on the state**, never on the game place: what a player knows and their opponents do not is stored apart from what is public. This is why the honor bid was split into `pendingHonorBid` (secret, shown to its owner only; the opponents only see that the player has bid) and `honorBid` (revealed). New secret information must follow the same principle.
- Cards a player is shown once (mulligan, "look at", search, reveal) are not part of the game state: a `SeeCard` message (to come) shows the card to the player, and the client remembers it. Since the messages API answers 204, the card must be delivered by a private Mercure notification; replaying the `Event` would send it again. The engine cannot check that an ability allows it (board approach): the masking of the view protects the display, not the secret, so the opponent should at least see the action in the event log.

**Alternatives rejected**
- Serialization groups named after the place of the `game` workflow: visibility depends on the viewer and differs from one instance to the next (my hand / the opponent's, one card / another), which groups cannot express; and it would couple the API to the names of dozens of places.
- A `secret` metadata on the places of the workflow: too much coupling between the declaration of the workflow and the structure of the state.
- Decorators registered per place, masking the view during a step: the inventory left a single case (the honor bids, also during duels, which are not a place), solved by storing the secret bid apart.

**Inventory of hidden information** (Stronghold format)
- Opponent's hand: hidden, count visible.
- Conflict and dynasty decks: hidden from every player, count visible.
- Facedown province: visible to its owner only (glossary "Facedown Province").
- Facedown dynasty card in a province: hidden from every player, owner included (glossary, l. 426 and 1094).
- Honor bid before the reveal (draw phase and duels): `pendingHonorBid`, visible to its owner only; every player sees `honorBidSelected`.
- Stronghold province chosen during the setup (`strongholdProvinceId`): visible to its owner only (afterwards, the stronghold province stack shows it).
- A hidden card in a visible stack (facedown province or dynasty card) keeps its id: it was never seen face up, or the players saw it turned face down, as at a real table.
- Attackers: public; `DeclareConflict` is atomic, so nothing exists in the state before the declaration.
- Everything else (discard piles, cards in play, rings, honor, fate, conflict, duel) is public.

## Decisions taken for the API of the cards

- `GET /api/cards/{code}` (one card) and `GET /api/cards` (every card, sorted by code, for a client loading the whole catalogue at once), `ApiCardsController`: the view and the log send codes, the client reads the cards there.
- Public (`PUBLIC_ACCESS` in `security.yaml`) and cacheable (`public`, `max-age` one hour), like `GET /api/actions`: a printed card depends on no game nor reader. 404 `card_not_found` (problem details) for an unknown code.
- A dedicated DTO (`View/Model/LogicalCardView`, `View/LogicalCardViewFactory`) instead of serializing the entity: the card is named by its `code` as in the view of a game, and the contract of the API does not follow the mapping.
- Fields: the printed attributes, `ability_timings` and `ability_granted_to_attached`. The printings and the packs are left out.
- Toshi Ranbo, printed with every element, lists the five elements in its fixture (it had `all`, which is not an `Enum/Element`, so `getElements()` failed on it). A card with no element has `elements: null`.

## Decisions taken for the decks and the queue

- **Deck**: quantity by card code (`Deck::cards`, JSON), instead of a list repeating each code; `getCardCodes()` gives one code per copy, in the order of the deck. A deck has an owner (`Deck::owner`); the starter decks of the fixtures have none and can be used by every user. A user cannot read nor queue with the decks of another user (404 / 422 `deck_not_found`, without telling whether the deck exists).
- **Formats**: the API receives the cards of a deck as quantities by card id, in a declared format (`format` field of the body, not of the route): `codes` (the codes of the engine) or `emeralddb` (the `cards` of a deck of EmeraldDB). One tagged service per format (`Service/Deck/DeckFormatInterface`); a new format is a new class. Ids naming the same card are added up.
- **External cards** (`external_cards`: `source`, `external_id`, `card_id`, unique on source and external id): the link between the ids of a source and our codes, loaded from `fixtures/external_cards/<source>.json`. The EmeraldDB file was generated once by matching the printings (pack and position) of our cards with those of `https://www.emeralddb.org/api/cards`, plus the 10 Keeper and Seeker roles by name (the two sides of a printed card); it is now maintained as a fixture, the engine never calls EmeraldDB. Our code is the EmeraldDB id prefixed with the pack, except for the second versions of 20 characters (`16-akodo-toturi` is `akodo-toturi-2`), `hisu-mori-toride-lion` / `-unicorn` and `kuni-wasteland`. The card `30-disciple-of-shinsei` held the data of Obsidian Talisman: it is now `30-obsidian-talisman`, and the real Disciple of Shinsei (neutral character, Spreading Shadows 41) was added from EmeraldDB. EmeraldDB knows about 765 cards released after our packs: a deck using one is rejected, naming it.
- **Deckbuilding rules** (`Service/Deck/DeckConstructionValidator`, glossary "Deckbuilding"): 1 stronghold, at most 1 role, 5 provinces with different titles covering the 5 elements (a province of several elements stands for any of them), dynasty and conflict decks of 40 to 45 cards, at most 3 copies by title (or the deck limit of the card), the role restrictions, provinces and dynasty cards in-clan or neutral, out-of-clan conflict cards with an influence cost from a single clan within the influence of the stronghold. Roles: Keeper +3 influence, Seeker one additional province of its element instead of another, Support +8 influence spent on its clan only (read from the traits of the role). Copies are counted by title (glossary, "Copy"): EmeraldDB accepts 3 copies of each version of a character, the engine does not. The lists of banned and restricted cards are not checked (the `format` of EmeraldDB is the card pool: standard, emerald, obsidian…; our fixtures only have an `is_restricted` flag). The starter decks of the fixtures are not legal decks: they are not checked.
- **Queue**: `POST /api/queue` creates the candidate, then runs the matchmaking at once (no worker): the FIFO pairs the candidates in their order of arrival, under a PostgreSQL advisory lock of the session (`CandidateRepository::withQueueLock()`), so that two concurrent requests never put a candidate in two games. `AutomaticGameLauncher` now dispatches `CreateGame` and `JoinGame` through the bus (it called the handlers directly, so no event was recorded, the game never started and nothing was flushed), then removes the candidates. A user joining twice gets a 409 (`already_queued`, also on the unique constraint for concurrent requests).
- **Finding the game**: the queue answers 204 whether the user is matched or not; the matched user finds the game with `GET /api/self/games` (id, their player id, version, ended, the users in player order), the last joined first. A route of the user rather than a filter of `/api/games`, which would be the list of every game (spectators, to come).
- Tests: `tests/fixtures/emeralddb-crane-deck.json` is a legal deck of EmeraldDB (Keeper of Water, Crab influence, a province of two elements), the base of the tests of the rules.

**Rejected**
- A route per format (`POST /api/queue/emeralddb`): the format of a deck is not a concern of the queue, and each format would add routes.
- Importing a deck from its EmeraldDB URL: the engine would depend on EmeraldDB, and fetching a URL sent by a user exposes the server (SSRF).
- Mapping the ids by a naming rule (removing the pack prefix): 23 cards do not follow it.

## Decisions taken for conceding

- `ConcedeGame` (public): the sender concedes, with an optional `reason` (at most 2000 characters) meant to collect the feedback of the players when a bug blocks them. The reason is never shown to the players: it is not part of the log (`GameConcededEvent` logs `{player} concedes the game` and carries the reason for future listeners), it stays in the payload of the recorded `Event`, which the players never read.
- Accepted from any player of a started game, in any place and during a duel, whoever has the opportunity to act (rejected in `join_game`: `GameNotStartedException`, 409). `PlayerState::conceded` eliminates the player (`VictoryCondition::Concession`, checked before the other eliminations); a player who has conceded cannot win, even with 25 honor. The automatic transition `end_game` then ends the game, now also from `choose_stronghold`, where only a concession can meet a victory condition (honor is not set yet).
- The schema of the messages now supports nullable scalars (`["string", "null"]`) and the `Length` constraint (`minLength` / `maxLength`).

## Decisions taken for the available actions

`available_actions` in `GameView`: the flat, sorted list of the types of the public messages the viewer can send now.

**Design**
- Each handler of a public player action implements `MessageHandler/PlayerActionHandlerInterface::assertAvailable(Game, Player): void`: the checks that depend on the state and not on the payload (game place with `Game::assertPlace()`, `opportunities` / `action_window` metadata, active player, duel step, a bid or the fate already collected, a conflict being resolved). The handler calls it first; `Service/AvailableActionsResolver` calls it for every public message and lists the action unless it throws a `RulesException` or a `WorkflowException`. A single piece of code, so the list and the handling cannot diverge, and the 409 keeps its precise exception.
- The board manipulations accepted in any place (`BowCard`, `LoseHonor`…) have an empty `assertAvailable()`, still called by the handler: they are always listed, so the client has no special case.
- An action is listed when its requirements on the state are met: its payload can still be rejected (a card that cannot be played, a ring already claimed, a character played into a conflict the player is not part of).
- Once the game has ended, nothing is listed (`GameEndedMiddleware` rejects every action).
- `trigger_interrupt_ability` and `trigger_reaction_ability` are always listed, like the board manipulations, for every player: interrupts and reactions do not depend on the opportunity to act. `play_interrupt_event` and `play_reaction_event` are listed for every player with an event of that timing in hand; `play_action_event` also requires the opportunity to act in an action window, and is not listed during a duel.
- `App\Kernel` maps the handlers implementing the interface to their message, read from the type of the parameter of `__invoke()` as Messenger does, in a service locator (`.app.player_action_handlers`); the container fails to compile when such a handler does not handle a single `PlayerActionInterface`. A test checks that every public message has such a handler. `MakeAction` generates the method.

**Alternatives rejected**
- A `restrict_to` list of phases (or places) on the message: phases are too coarse (several places of the conflict phase, with different actions), places would have to be updated with every new place, and neither can express the active player or the duel step.
- `isAvailable(): bool` on the message: messages are serializable data without services, while the checks need the workflow metadata and the state managers; it would have needed a static method and a context object.
- `isAvailable(): bool` on the handler: the handler could only throw a generic exception, losing why the action is refused.
- Finding the handler at runtime through the Messenger handlers locator: it needs an envelope (a message instance) and only returns closures.

## Decisions taken for the version of the state

Prerequisite of `wait_for_update` (MCP server): an agent must know whether the game has changed, and must not act on a state its opponent changed in the meantime.

**Design**
- `Game::version`: the number of messages handled on the game, incremented by `EventRecordingMiddleware` with each `Event` (automatic transitions included in the message that led to them). `CreateGame` gives version 1. `Event` now references its game and carries its version (unique per game): the events of a game are numbered from 1, so "the events since version N" can be read.
- HTTP uses the standard headers, the ETag being the version (`"12"`): the view only depends on the state and the viewer (fixed by the token), so a version gives a single representation per viewer.
  - `GET /api/games/{id}`: `ETag`, `If-None-Match` → 304, `Cache-Control: private, no-cache`. The game is still loaded in a single query before checking the version: the 304 saves the view and the bandwidth, not the query. The view also has `version`, for clients without the headers (MCP tools).
  - `POST /api/games/{id}/messages`: still 204, with the `ETag` of the version once the message is handled. Optional `If-Match` (a single strong ETag, or `*`): an `ExpectedVersionStamp` checked by `GameVersionMiddleware` right after the lock; 412 `stale_version` if the game has changed. Anything else in `If-Match` is a 400 `invalid_if_match`.
- The expected version is a stamp, not a field of the message: replaying an event must never fail on it.
- `GameUpdatedNotificationMiddleware`, before the Doctrine transaction in the chain, publishes `game_updated` (id, version) on the Mercure topic `/games/{id}` once the transaction is committed: a client reading the game on notification sees the new version. No data of the game: the clients read their own masked view. The topics became private since (see the decisions taken for the private Mercure topics).

**Alternatives rejected**
- `#[ORM\Version]`: only incremented when the `games` row changes (not when an action only moves cards), and redundant with the pessimistic lock.
- The id of the last event as ETag: not ordered (no "since"), an extra query or a duplicated column, and a UUID would have to be drawn from the random generator of the game to be replayed.
- `X-Game-Version` headers (the `X-` prefix is deprecated, RFC 6648) or the version in the body of the POST: the standard headers are understood by HTTP clients.
- 200 with `{version}` in the POST response: the ETag already carries it.

## Decisions taken for the game log

The stored `Event`s cannot be shown to the players: their payloads hold secret information (above all the seed of `CreateGame`, from which the whole game can be replayed, but also the secret bids, the chosen stronghold province). The players read a log of the game instead, built for them.

**Design**
- One entry per change of the state: a template in English with placeholders and params (`{player} loses {amount} honor`), readable as is, translatable by the client (the template is the key). The params are typed (`player`, `card`, `stack`, `ring`, `value`, `list`) so that the client can show cards by code and players by name.
- A single entry for every reader, each param with its visibility (null: public, else the ids of the players who know it, empty: nobody), masked when the log is read, as the view is: the visibility rules are written once (`View/Visibility`, shared with `GameViewFactory`), and spectators are covered (only what is public). A hidden card loses its id too: otherwise a card drawn could be followed until it is played.
- The visibility is computed when the entry is written, from the state before and after the change: a card drawn is known to its owner, a dynasty card put facedown into a province to nobody (its id is public, as in the view), a card played from hand to everyone.
- Three sources, all declarative: the `log` metadata of every card transition (required, checked by a test; params `card`, `owner`, `from`, `to`) and of every game transition (no params), and the `#[GameLog]` attribute of the domain events dispatched for every other change (`#[VisibleTo]` for a secret value or card, `#[Revealed]` for a card shown wherever it is). The domain events are also the future hook of the cards reacting to what happens.
- The log tells what changed, not why: the engine does not know the card effects. Each message of the log has its type and its sender (never its payload), so the reader knows who did what (e.g. who made a player lose honor).
- Card transitions are logged when the card leaves its place, game transitions before their listeners, domain events before their other listeners: the entries are in the order of the changes (the move of a character before the consequences of leaving play).
- `EventRecordingMiddleware` now comes before `WorkflowTransitionMiddleware`: the version is incremented once the automatic transitions are applied, so their entries belong to the message that led to them.
- Bookkeeping that follows from a logged transition is not logged: the active players and the passes reset when a step begins (`It is X's turn`), the turn records, the reset of the honor dials, the cleanup of the conflict and the duel, `lastConflictPlayerId`.
- Reading: `GET /api/games/{id}/log?since={version}`, same errors and `ETag` as the view; `app:game:log <id> [--as=<user>]` renders it as text (`GameLog/GameLogRenderer`).

**Alternatives rejected**
- Two texts per message (one for the sender, one for the others): the secret is not always the sender's (`SetHonorBid` or `DiscardRandomCardFromHand` sent by the opponent), the automatic transitions are not messages, and the text depends on the result of the handling (random discard, cards drawn).
- A workflow for the player, whose transitions would carry the changes that are not card moves: many of them are not the player's (rings, Imperial Favor, conflict, duel, card tokens), a transition carries no value (a specific listener per transition would apply it), and the workflow would have a single place.
- Logging in the handlers directly: no single place to make sure every change is logged, and the same sentences repeated.
- Grouping the entries of a cause (filling four provinces): one line per change for now.
- Existing games have no log: no game is live.

**Not done**
- An empty `params` is serialized as `[]`, not `{}`.
- The console renderer names a province stack "john's province", without telling which one; the client resolves the stack id.

## Decisions taken for the long-poll (`wait_for_update`)

**Design**
- `GET /api/games/{id}/log?since={version}&wait={seconds}`: the log endpoint is the long-poll, no new route. The response waits until the version of the game is greater than `since`, then sends the log since that version (what changed, masked for the reader, with its ETag); once `wait` seconds have passed without change, it is sent as is: no message, or a 304 with `If-None-Match`. `wait` is 0 by default (no wait), at most `ApiGamesController::MAX_WAIT` (30 s, below the usual timeouts of clients and proxies; nginx waits 60 s for PHP-FPM); anything else is a 400 `invalid_wait`. A game that has ended answers at once.
- The future MCP tool `wait_for_update` wraps this endpoint.
- `Service/GameUpdateWaiter` reads the version from the database every 250 ms (`GameRepository::findVersion()`, a DBAL query that bypasses the identity map), without lock nor transaction, and refreshes the game once it has changed. The existence of the game and the player are checked before waiting. It sleeps through the Symfony clock: the tests mock the global clock (`ClockSensitiveTrait`), and run a message from the clock to simulate a change made during the wait.
- The development stack serves the application with nginx and PHP-FPM (`php:8.4-fpm-alpine`, up to 20 workers, `docker/dev/symfony/php-fpm.conf`) instead of the Symfony CLI, which ran a single `php-cgi`: a request waiting blocked every other request.

**Alternatives rejected**
- PostgreSQL `LISTEN` / `NOTIFY`: no polling, but a connection blocked on the notifications and a `NOTIFY` sent after the commit; polling one indexed row every 250 ms is cheap at the expected load.
- Waiting on Mercure in PHP: the server would subscribe to its own hub.
- A separate endpoint: the log since a version is exactly what the waiting client needs next.

**Not done**
- Each waiting client holds a PHP-FPM worker (up to 30 s): the number of workers bounds the number of agents waiting at once. To revisit with a non-blocking runtime (FrankenPHP worker mode, or Mercure for the clients that can subscribe).

## Decisions taken for the MCP server

**Design**
- A single server `l5r` (`config/packages/mcp.yaml`), HTTP transport only, at `/api/mcp`: under the firewall of the API, so the agents authenticate like any client (`Authorization: Bearer <API key>`), and `access_control` restricts it to the bots (`ROLE_BOT`): a human gets a 403. The tool reads the user through `Security`.
- `wait_for_update(game_id, since = 0, timeout = 30)` (`Mcp/WaitForUpdateTool`): the same waiter and the same masked log as `GET /api/games/{id}/log?since=…&wait=…`, called in process rather than through HTTP. The log is returned as structured content (snake case, as the API). An unknown game or a game the bot does not play is a tool error (`isError`), the bounds of the arguments are checked by the server against the schema (JSON-RPC error).
- Tools are registered from `src/Mcp` (`registry.tools`), described by their doc comments (read by the agents).
- Sessions: the default file store of the bundle, in `var/cache` (a tmpfs in development): they are lost when the container restarts, the clients initialize again.
- `nyholm/psr7`: the bundle converts the request to PSR-7 and needs a PSR-17 factory.

**Not done**
- `http.allowed_hosts`: the default of the SDK (localhost only) is kept for development; a public server must list its hosts.
- The rules as resources: an agent with a file system reads them from `doc/` (see the tools to play).

## Decisions taken for the tools of the messages

**Design**
- One tool per public message, named after its type (`pass_opportunity`): an agent sees the actions as tools, with the description of the message and the schema of its payload, rather than a single `send_message(type, payload)` whose payload it would have to look up.
- `Mcp/MessageToolLoader` (a `LoaderInterface` of the SDK, autoconfigured `mcp.loader`) registers them from `MessageSchemaFactory::createAll()` when the server is built, through the `ExplicitElementLoader` of the SDK with one `Mcp/MessageTool` (`ToolHandlerInterface`) per type: the attributes of the bundle need a method per tool. Nothing to write for a new message.
- Arguments: the payload, plus `game_id` (required) and `expected_version` (optional, the `If-Match` of the HTTP API: a 412 `stale_version` when the game has changed since). Neither is a field of a payload (`game_id` is reserved). The server checks them against the schema (`additionalProperties: false`, so `player_id` cannot be sent) before the call.
- Result: `{version}`, the version once the message is handled (output schema declared). A rejected message is a tool error (`isError`) whose text is the problem as the HTTP API sends it (`type`, `status`, `detail`, `violations`): the agent reads the same errors.
- `Service/Api/PlayerMessageSender` holds what the controller did (game and player checks, dispatch with the `ExpectedVersionStamp`, exceptions to problems, `ApiProblemException`), shared by the HTTP API and the tools. The controller now checks the body before the game: an invalid body on an unknown game is a 400, not a 404.
- Annotations: not read-only, not destructive (the engine does not apply card effects, every change can be undone by another message), not idempotent.

**Alternatives rejected**
- A single `send_message` tool: the agent would need the catalogue (`GET /api/actions`) and could not rely on the schema check of the client.
- The tools calling the HTTP API: a second request, and the bot's key to forward.

## Decisions taken for the tools to play (MCP)

Playing a first game with Claude Code through the HTTP API failed: its permissions block every command holding a variable (`curl -H "Authorization: Bearer $KYUDEN_API_KEY"`), and a headless agent cannot approve it. An agent now plays through the MCP server alone, its API key in the configuration of the client, never in its context.

**Design**
- What the HTTP API gives a player, as tools: `get_game` (`GameViewFactory`, the view masked for the bot: unknown game or not a player is a tool error), `list_my_games` (`View/UserGameViewFactory`, shared with `GET /api/self/games`), `list_decks` (`View/DeckViewFactory`, shared with `/api/decks`), `join_queue` / `leave_queue` (`Service/MatchMaking/MatchMakingQueue`, the logic of `ApiQueueController` moved to a service: `DeckNotFoundException`, `AlreadyQueuedException`, `NotQueuedException`), `get_cards` (several codes at once, at most 50: an agent reads a hand or a deck in one call; an unknown code is a tool error).
- `wait_for_game(timeout = 30)`: the long-poll of an agent waiting in the queue, so that it does not loop on `list_my_games` (`Service/GameStartWaiter`: the games of the user read every second through the Symfony clock, returns the games in progress, at once when there are some, none once the time is up).
- A list is returned inside an object (`{"games": [...]}`, `{"decks": [...]}`, `{"cards": [...]}`): the structured content of a tool is an object.
- `agent/`: `PLAYER.md` (the prompt: tools, finding the game, the loop on `wait_for_update` / `get_game` / one action with `expected_version`, reading the view, the engine as a board, fair play; any error but `stale_version`, or a blocking situation, ends the game: the agent concedes with the reason, the game being a test of the engine), `mcp.json` (the server, `KYUDEN_URL`, `KYUDEN_API_KEY`), `play.sh <API key> [deck]` (copies the prompt as `CLAUDE.md` and `doc/` as `rules/` into a temporary directory, so that the agent loads neither the instructions of the repository nor its code, and allows only the tools of `l5r`, `Read` and `Grep`).

**Not done**
- No game played by the agent yet.
- The view is sent whole on each `get_game`: a long game may fill the context of the agent; a compact view to consider once seen in play.
- An agent that stops without conceding blocks the game: no timeout.

## Decisions taken for the development console

- The console (`/console` since the web client took `/`; `ConsoleController`, `templates/console/index.html.twig`) is a command line in the browser, for the developers: the commands typed (`/help`) are interpreted by `assets/console` and call the HTTP API as any client would, nothing typed is run as code. Below the command line, the requests, the responses (problem details included) and the entries of the log; beside it, the actions the user can send now (from the last view; clicking one starts the command) and the image of the card under the pointer (or shown by `/card`). The Mercure notifications are only `game_updated`: the console does not show them, its status bar shows the state of the connection. No build step: AssetMapper serves the ES modules.
- The console only reads public endpoints (`/api/cards`, `/api/actions`) and those of the user: one command per public message, generated from its JSON schema (`/bow_card`, `/bow-card`) plus short aliases (`/bow`, `/bid`, `/pass`…, `MESSAGE_ALIASES`), so a new message is a new command with nothing to write. The arguments are positional in the order of the properties of the schema, named (`card_cost=2`) or the payload as JSON; a card is given by the shortest unique prefix of its id (4 characters at least, shown as `[0a19]`) or part of its name, a player by `me`, `opp` or user id, and a player property left out is the user.
- The token is kept by the tab (`sessionStorage`), so two tabs play the two players; `/as <user>` logs in with the development token of a user of the fixtures. On `game_updated` for the current game, the console reads the view again (`If-None-Match`) and writes the log since the last version it knows.
- The actions of the step are shown apart from the messages accepted anytime (board manipulations, interrupts, reactions: `ANYTIME_ACTIONS`), which `available_actions` always lists.
- Mercure in development: the `symfony` container publishes to the `mercure` service (`MERCURE_URL` in `docker-compose.yaml`), the browser subscribes to `MERCURE_PUBLIC_URL`; the hub accepts subscribers with a token (the topics are private) from `http://localhost:8080` and `http://127.0.0.1:8080`.
- The console follows `game_started` on the topic of the user: a user matched by the queue plays the new game (it read `GET /api/self/games` every 2 seconds before).

## Decisions taken for the web client

- **Vue 3 + Vite + TypeScript** (`front/`), rather than Preact without build or Symfony UX: the board will re-render the whole state on each `game_updated` (view, available actions, log), with hovers, selections, contextual help, later drag and drop and the deck editor; a reactive framework with single-file components fits, and the JSON API is already the contract (the same as the bots). One application for the whole site, the development console stays apart.
- **Same origin**: nginx sends `/api`, `/console`, `/assets`, `/bundles` and the profiler to Symfony, the rest to the Vite server of the `node` service (`node:24-alpine`, `npm install && npm run dev`; HMR through nginx, `clientPort: 8080`). No CORS, the token goes in `Authorization`. In production, the static build (`front/dist`) behind nginx with a fallback to `index.html` (not configured yet: the Docker stack is the development one).
- **Desktop only**: designed for 1536px, at least 1280px (`min-width` of the body; 1024px before the board).
- **Fonts** served with the site (`@fontsource/eb-garamond`, `@fontsource/shippori-mincho` for the kanji, `@fontsource/barlow`, split by unicode range), not loaded from Google Fonts.
- **Mons**: the SVGs of fiveringsdb-ui (the official mons), used as CSS masks, painted in ink at rest and in the color of the clan once chosen; the colors of the clans are ours (muted for the paper). The ensō of the neutral cards is the logo.
- **Newcomers** (the first impressions, for a player who knows the game but not the site): choose a clan (`/`, clicking it again deselects it), get a name at court (`/name`: a family of the clan and a given name, drawn again or written), meet the opponent (`/opponent`: the guest account then the game are created during the search, shown at least 1.8 s), begin (`/games/:id`, the board). The copy says little about the introductory game: the bottom of the home page presents the site (a game against a bot, then an account and one's own deck, then the community). "Sign in" leads to the sign in (see the player accounts).
- **State**: the choices of the way in `sessionStorage` (reloading a page loses nothing), the guest in `localStorage` (`kyuden.session`: user id, name, token). A guest cannot be renamed: another name creates another guest. A token refused (401) makes a new guest. Reading and writing the storages never throws (private windows).
- **Starter decks**: the client knows them by convention (`<clan>-starter-deck`), with the codes of the stronghold and the champion shown on the home page; it reads the cards from `GET /api/cards/{code}` (names and images).
- **Tests**: Vitest (jsdom) for the names, the clans, the session (`fetch` stubbed) and the home page (choosing and deselecting a clan); `make test` and `make lint` run the client too.

## Decisions taken for the game board

- **Mockup**: the design canvas made with the user (Claude artifact `GiPbGSPNcvLD2r9eFKKNp5`, private): five screens (dynasty, declaring a conflict, conflict, honor bid, card opened) and the hover of a card. The implementation follows it.
- **Layout**: the board on the left, a bar of 340px on the right (the step, what to do, the state of the game, the log). The board adapts to the step: what the player does not need now folds into a line (e.g. their provinces and hand while declaring a conflict), what they need comes forward. First delivery: a layout that works for every step (the available actions in the bar, the cards clicked on the board), plus the dedicated layouts of the setup (stronghold province), the declaration of a conflict and the conflict (with the defenders). Next: dedicated layouts step by step where the generic one is not enough.
- **Players**: named "You" and "Opponent" (or "Opp."), never by their name, which only appears small beside (cut when long): easier to identify, and safe with long names. The tint of the clan is behind these words (`Clan::tint`); never the only mark of a player, since both may be of the same clan (the rings claimed are ink for the viewer, dashed for the opponent).
- **Engine**: the view names the players (`PlayerView::name`, the user) and gives the clan of their stronghold (`PlayerView::clan`, read from the stacks loaded with the game: the read stays a single query, the users joined); it links the Mercure hub (`Mercure\Discovery`, `Link` header) instead of a Vite variable, so the client finds the hub of any environment.
- **Cards**: a click opens the card (image in the middle of the darkened screen, actions beside); hovering shows its image beside it, after 150ms, on the corner opposite to the opponent (above for the viewer's cards, below for the opponent's), never under the bar. The same manipulations on the cards of both players, as the engine accepts them. The abilities of a character are offered once it is in play (a holding in its province). Interrupts and reactions are only triggered from the card opened ("Activate its ability"; "Activate its action/interrupt/reaction" when it has several timings); `End resolution` is in the bar while something is being resolved.
- **Totals**: prefilled from the cards (printed skill + attachment bonuses + glory of an honored character − of a dishonored one, or the skill recorded with `SetCharacterSkill`; province strength + stronghold bonus; glory of the ready characters + claimed rings; fate of the stronghold; dueling statistic + bid); the player confirms or corrects. Playing a card prefills its printed cost and 0 fate placed (`fate_cost` = fate placed).
- **Updates**: Mercure only (no polling): on each notification the client reads the view and the new log entries again; a message carries the version seen (`If-Match`), a 412 reads the game again and asks the player to look before acting.
- **Steps**: a table in `lib/board.ts` gives each place its phase, title, help and hint (how to act with the cards); the places nobody acts in get their phase and a title from their name. "Passed" is shown only in the steps with opportunities (`passed` stays set until the next such step resets it), the bids only during the draw phase and the duels.
- **Folded lines**: a click on a line folded by the step (the viewer's provinces and hand while declaring a conflict; the characters at home of each player during a conflict) unfolds it until the next step, with a "Fold" link; a line holding cards unfolds on a click beside them (a click on a card opens it). The characters at home of a conflict unfold with the provinces of their player, which the conflict screen does not show otherwise.
- **Card names**: the attacked province in the heading of a conflict and the cards named in the log (the bar and the drawer) are underlined and show the image of the card on hover (`CardName.vue`, through `lib/preview`); `logText.entryParts()` cuts an entry into text and cards.
- **Skills on the cards**: a character shows its current skills, the one recorded by a player (vermilion) else the one computed by the client (printed, attachments, personal honor; dotted underline: nobody recorded it), with the same rule as the totals (`board.shownSkill()`); the dialog of the card says which. The "bug" of the printed skill shown after a recorded one was this: the card showed the printed skill while the total computed the bonus of the attachment (no `SetCharacterSkill` had been sent).
- **Result of a conflict**: from `react_to_conflict_result` to the end of the conflict (`board.conflictResolved()`), the conflict screen gives the winner (or no winner) and the broken province instead of the lead; in `resolve_ring_effects`, the effect of the ring, and who may resolve it.
- **Revealing dynasty cards**: in `reveal_facedown_dynasty_cards`, a click on a face-down card in a province of the viewer sends `reveal_dynasty_card` at once.
- **End of the game**: the board darkens under "Victory" or "Defeat", how the game was won (`board.victoryText()`) and "Leave the game", a link to the home page until the pages it should lead to exist (a guest: an invitation to create an account; a player with an account: the queue). The bar stays as is.
- **Tooltips**: `v-tip` (`lib/tooltip.ts`) shows a text under an element on hover or focus, drawn by `TooltipLayer.vue` above the board and the bar (no scrolling area cuts it); the rings recall their effect (`RING_EFFECTS` in `lib/icons.ts`) in the bar, while declaring a conflict and on the conflict screen.
- **Not done**: adding fate to a card (no message in the engine, left out on purpose); covert (`evaded_ids` sent empty); A War on Two Fronts and other multi-province conflicts (only the first attacked province is shown); the round number (not in the view); the house bot only passes, so the board was checked by playing a whole round in a browser (Chrome headless, 1536×900 and 1280×800), not against an opponent who declares conflicts.

## Decisions taken for the guest accounts and the games against the bot

- **Guests** (`POST /api/guests`, public): a human user with the name chosen and no username (a UUID as id: the prefix `guest-` is gone, a guest is told by `User::isGuest()`), and a session token. The guest keeps their user when they create their account (see the player accounts). No limit of rate yet.
- **Games against the bot** (`POST /api/bot-games`): no queue; the user joins first (player order = order of arrival), then the house bot with another starter deck drawn at random (outside the generator of the game: the `JoinGame` event of the bot records it, so the replay is unchanged). The response names the opponent (user, name, deck), so that the client shows it without reading the game.
- **House bot**: a bot user of the platform (`HOUSE_BOT_ID`, `kyuden-bot` owned by `kyuden` in the fixtures; in an environment without fixtures, create both with `app:user:create`). Only this user is played by the engine: the other bots are programs playing through the MCP server.
- **When it plays**: in the request that gave it something to do, after the response (`kernel.terminate`): the player is answered at once and sees the moves of the bot through Mercure. No worker. `HouseBotMiddleware` schedules the game of every handled message; `HouseBot` loops until the strategy has nothing to do (100 actions at most per request). Its messages go through the bus like those of any player, recorded as events.
- **Concurrency**: the bot clears the entity manager before each message, so that `GameLockMiddleware` loads and locks the game again, and expects the version it decided on (`ExpectedVersionStamp`); a stale version makes it decide again. A failure is logged and stops the bot in that game until the next message.
- **Strategy** (`BotStrategy`, first version): from the available actions, only what the framework steps require, with the printed values of its cards: the province with the first code under its stronghold, its facedown dynasty cards revealed one by one in `reveal_facedown_dynasty_cards` (`RevealDynastyCard` is accepted anytime: the place tells when it is due), the fate of its stronghold, an honor bid of 2, no defenders, the printed skill of its ready participants, the printed glory of its ready characters, the Imperial Favor on its military side; it passes every other opportunity and conflict. Checked by playing several whole rounds through the API against a passive player.

## Decisions taken for the player accounts

- **Password, no email**: a human signs in with a username and a password. No email nor personal data: an account will be recovered through the accounts it is linked to (Discord, Google…, to come), which the site will press the players to link.
- **Name and username**: `name` is the name at court, what the other players see, not unique, changed from the account (`PATCH /api/self`); `username` only signs in (`[A-Za-z0-9_]{3,32}`, unique whatever its case through `usernameCanonical`, a lowercased column with a unique index: the schema comes from the mapping, so no functional index). The username is chosen by the player, never derived from the name.
- **A guest becomes the account**: same user (id, name, games, decks, tokens); the token of the guest stays valid. Without a token, the client sends a name at court drawn at random, which the player changes from their account: the server trusts this name like the name of a guest.
- **Password**: 8 to 4096 characters, counted in characters (`Assert\Length`, UTF-8), no other rule; hashed by the `auto` hasher (cheap in the tests), rehashed on sign in when the algorithm changes.
- **Errors**: the structure of the body is a 400 `invalid_payload`, its values a 422 `validation_failed` with `violations`, a username taken included (a violation on `username`; also when the database rejects it, two players taking it at once). `invalid_credentials` (401) does not tell an unknown username from a wrong password.
- **Rate limiting** (`symfony/rate-limiter`): each attempt to sign in consumes one token from the limit of its IP (20 a minute) and of its username (5 a minute); a success resets the username, like the login throttling of Symfony. Counting only the failures would need a peek, which the sliding window does not have; the IP limit is not reset, so a NAT of many players gets 20 attempts a minute. Stored in the `cache.rate_limiter` pool (the filesystem); the tests clear it before each test of the sessions.
- **Signing out** revokes the token of the request only (the other sessions of the user go on); an API key is revoked with `app:token:revoke`.
- `GET /api/self` sends `user_id` (was `user`), `name` and `username`: the development console reads `user_id`.

- **Web client**: the screens follow the mockup made with the user (Claude artifact `LBUBwNBPdSopj5NMj5GLTL`, private): sign in (`/login`, from "Sign in" in the header), create an account (`/signup`), the end of a game of a guest (an invitation to create an account under the result: "Stay at court, <name>"; "Leave the game" leads home), the account (`/account`). Creating an account asks the preferred clan, a username and a password (see the decisions taken for the preferred clan): the name at court of a guest is kept and shown, not changed there; anyone else gets a name drawn by the client (`drawCourtName()` of their preferred clan) and finds it on their account, where the name is changed. "Linked accounts" shows Discord and Google disabled ("coming soon"), to announce how an account will be recovered. "Play a game" on the account leads to `/queue`, empty for now. A guest whose token is refused while creating their account gets a new user with the same name. The rules of the username and the password are checked by the client before sending (`lib/credentials.ts`: characters counted as code points, like the API); the violations of the API go to their field.

## Decisions taken for the preferred clan

- **Preferred clan** (`User::preferredClan`, one of the seven Great Clans: `Clan::greatClans()`, not `Neutral`): chosen when the account is created (`POST /api/accounts`, `clan`, required for a guest too), changed at any time (`PATCH /api/self`, `preferred_clan`, alone or with `name`), sent by `GET /api/self`, `POST /api/accounts` and `POST /api/sessions` (`preferred_clan`). Null for a guest and a bot. It is stored in the database since it will choose what the player is offered (the deck of the queue…).
- **A copy of the starter deck**: the account gets its own copy of the starter deck of its preferred clan (`Service/Deck/StarterDeckCopier`, called by `AccountRegistrar` in the transaction of the account: "<Clan> starter deck", a new UUID, the cards of the starter deck). The starter decks stay public (without owner), but the players play their own decks, which they will change; they never see the decks of the others. Only once, at the creation of the account: changing the preferred clan gives no deck. The id of the starter deck of a clan is `Clan::getStarterDeckId()` (`<clan>-starter-deck`, the convention of the fixtures and the client).
- **Web client**: on `/signup`, the clan comes first, above the username (`components/ClanPicker.vue`, a radio group of the seven mons, browsed with the arrows; the big mon beside the form follows it), with "You receive its starter deck to play with. You can change your preferred clan at any time." It is prefilled by `suggestedClan()` (`lib/session.ts`): for a guest, the clan of the stronghold of their last game (`GET /api/self/games`, then the view of that game), else the clan of their way as a newcomer (`newcomer.clanId`, while the tab remembers it), else a clan drawn at random, like for anyone else. A new user's name at court is drawn from the families of the clan chosen. On `/account`, the same picker saves at each choice ("Saved.").
- **Fixtures**: john prefers the Crab and jane the Crane, each with their copy (`UserFixtures::PREFERRED_CLANS`; `UserFixtures` now depends on `AppFixtures`). No migration: the schema comes from the mapping (`make db`).

## Decisions taken for the images of the cards

- The image of a card is printing data, like the illustrator and the flavor text: `Printing::imageUrl`, loaded from `fixtures/printings` and sent by `GET /api/cards` (`image_url`, the first printing by id that has one; each card has a single printing for now). The engine does not use it; `ExternalCard` stays the link to an external id (EmeraldDB attaches its images to each version of a card, mostly the same FFG URLs).
- The CDN of FFG (`lcg-cdn.fantasyflightgames.com`) is a CNAME of CloudFront, which publishes a DNS `HTTPS` record: browsers upgrade even an `http://` URL to `https://` (a 307 for the reason `DNS`), and the certificate does not match the name. The fixtures point at the CloudFront name (`https://d2qmv0gg5fizll.cloudfront.net/`, valid certificate) instead; it could change if FFG changes its distribution. The other images (`i.imgur.com`, `images-cdn.fantasyflightgames.com`) are untouched.

## Decisions taken for the settings of a user

- **Storage**: `User::settings`, a JSON column read through `Model/UserSettings` (a readonly model): a setting missing from what is stored takes its default value, so that a new setting needs neither a migration nor a column. The first one is `cardDisplay` (`Enum/CardDisplay`: `drawn`, the default, or `images`).
- **API**: `GET /api/self`, `POST /api/accounts` and `POST /api/sessions` send `settings` (`{"card_display": "drawn"}`); `PATCH /api/self` changes them with `{"settings": {"card_display": "images"}}`, alone or with `name` and `preferred_clan`; a setting left out is kept. Structure errors (not an object, unexpected setting, not a string) are 400 `invalid_payload`, an unknown value is 422 `validation_failed` on `settings.card_display` (the paths of the violations are now snake-cased segment by segment, so that the dot is kept).
- **Guests and bots** have the default settings; only an account changes them, from `/account` (a radio group "Cards on the board": Drawn / Card images, saved at each choice).
- **The back of a face-down card**: a card the viewer cannot see now has its `side` in the view (`CardView::side`: conflict, dynasty, province, role, stronghold), public since every player sees the back of a card; the client chooses the back from it.
- **Web client**: the session keeps `cardDisplay` (`lib/session.ts`, `chooseCardDisplay()`; a session kept before the settings is `drawn`). With `images`, `GameCard.vue` shows the image of the card (`image_url`), or the back of its side (`assets/images/card-back-*.webp`, moved from the `assets/` of Symfony to `front/src/assets/images`); a bowed card turns its image, a broken province is turned upside down and greyed, the viewer's own face-down province is faded with "Face down". Fate, personal honor and the tag stay over the image; the skills of a character are marked on it only when they differ from the printed ones (recorded, or changed by attachments and personal honor). A card without an image is drawn.
- **Theme** (`theme`, `Enum/Theme`: `auto`, the default, `light`, `dark`), the second setting, changed like the first (`{"settings": {"theme": "dark"}}`; changing one setting keeps the others). It applies to the whole site. Guests and bots follow the system. On `/account`, a radio group "Appearance": Day / Night / Auto.
- **Night in the client**: the session keeps `theme` (`chooseTheme()`); `lib/theme.ts` sets `data-theme` on `<html>` (`light`, `dark`, none for `auto`) from `main.ts`, before the first render, and again when the session changes. `styles/base.css` swaps the tokens under `:root[data-theme='dark']` and under `@media (prefers-color-scheme: dark)` for `:root:not([data-theme='light'])` (`--board` and `--card` too). The screens already dark (`.inverted`, the wait in the queue; the end of a game over the board) stay dark in the day. Two tokens replace hardcoded colors (`--ink-hover`, the primary button hovered; `--selected`, the deck chosen in the queue); the player chips keep the ink of the day on the light tints of the clans; the fate on a card is written in `--paper`. A theme forced against the system can flash for the time the script loads: nothing in `index.html` reads the session before it.

## Decisions taken for the private Mercure topics

- **Why now**: the queue screen must learn that the user has been matched, and the cards shown to one player (`SeeCard`) or spectators will need topics read by some users only. The hub no longer accepts anonymous subscribers.
- **Every update is private** (`MercureNotifier`); the topics are written in one place (`Notification/Topics`).
- **One token per user, not per game**: the `subscribe` claim is `/users/{id}` and the template `/users/{id}/games/{gameId}`; `GameUpdatedNotification` carries `/games/{id}` and `/users/{user}/games/{id}` for each player. The hub dispatches an update when one of its topics matches the subscription (`/games/{id}`) and one matches the claim: the token reads every game of the user, those joined after it was issued too, and no other (checked against the hub: an update of another user's game is not received). A token listing the games would have to be issued again whenever the user joins one.
- **Token in the query** (`authorization` parameter of the hub): `EventSource` cannot set headers, and a cookie would need the hub on the origin of the site (the hub is on another port in development, the API is stateless). The token lasts an hour (`SubscriptionFactory::LIFETIME`), so that a URL in a log is short-lived; `GET /api/self/subscription` issues it (`no-store`). The clients ask for a new one once the hub closes the connection, and the web client subscribes again from the last update received (`lastEventID`), so that nothing is missed.
- **`game_started`** on `/users/{id}` of every player (`GameStartedNotification`, from `GameUpdatedNotificationMiddleware` once the `JoinGame` of the last player is committed: the game has left `join_game`): the game and the player of each user (`player_ids`, by user id: one update for every topic). Not on each `JoinGame`: the first player matched by the queue was told before the second had joined, and the web client cannot show a game in `join_game` (no players yet). It replaces `GameCreatedNotification` (published by `CreateGame` before any player had joined, on a `/games` topic nobody read): the queue screen and the console go to the game they are told about.
- The view of a game still links the hub (`Link: rel="mercure"`), but the web client reads the hub with its token in the subscription.

**Alternatives rejected**
- Polling `GET /api/queue` from the queue screen: the next features need private topics anyway.
- A fetch-based client of server-sent events with an `Authorization` header: a dependency, and the console has no build.

## Decisions taken for the screen of the queue

- **Mockup**: the artboards "The queue" of the accounts canvas (Claude artifact `LBUBwNBPdSopj5NMj5GLTL`): choosing a deck, then waiting.
- **Accounts only**: `/queue` sends a guest to sign in (the route already did); "Play a game" on the account leads there.
- **Decks** (`lib/queue.ts`): the user's own decks, then the starter decks, seven tiles a row; the clan of a deck is the clan of its stronghold (the kanji in the color of the clan, the name of the stronghold read from `GET /api/cards/{code}`); a starter deck is named by its clan (the API names it by its id). Chosen at first: their own deck of their preferred clan, else its starter deck, else their first deck.
- **Waiting**: the page in reverse, header and footer included (`lib/page.ts`, `inverted`: the color tokens swap under `.inverted`, `styles/base.css`; the vermilion is lighter there, `#ec6a52`, for the contrast), the ensō turning, the time waited (from when the page learns it: the API does not tell when the user joined), the deck, "Leave the queue".
- **Matched**: the screen follows the topic of the user from the start and opens the game on `game_started`. After joining, it reads the queue again: not queued any more means matched before the hub could tell (the subscription may not be open yet), and the last game of the user opens. Already in the queue (another tab, 409) waits with that deck; leaving once matched (404) opens the game the same way.

## Decisions taken for rejoining a game in progress

- A banner under the header of every page (not the game, which has none, nor the wait in the queue) offers to rejoin the last game joined that has not ended, naming the opponent: a proposal, the player is not sent to the game.
- It is read again on every navigation and when the session changes, so that it disappears once the game has ended and appears once a player signs in to an account that plays one; nothing on the API side (`GET /api/self/games` already says `ended`).
- Only one game is offered (the last joined); the older games in progress (a guest who started a game then another) are left as they are: no list nor concession from the banner.

## Decisions taken for the databases

- The tests run on their own database, the `database_test` service (`DATABASE_URL` of `.env.test`), in memory (tmpfs): playing in development no longer changes what the tests find. `make db-test` creates its schema and loads the fixtures, after each start of the service.
- The fixtures hold what every environment needs: the cards, the starter decks (without owner) and the users with their tokens. No candidate nor game: a game starts through the queue.
- `RevealDynastyCard` on a card that is not a dynasty card (a province) is refused (`WrongCardSideException`, 409) instead of failing in the workflow (500).

## Next steps

### Web client and house bot

- **Board**: dedicated layouts for the remaining steps (defenders seen from the attacker, the draw and fate phases, the duels on the board, the reaction windows), the opponent declaring a conflict against the viewer (not seen yet: the house bot passes its conflicts), the round number, a menu for the cards of the opponent's hand and decks (shuffles are in the effects on a player), and a component test of `GameView`.
- **Choosing the stronghold province** (`choose_stronghold`): the board must show the five provinces larger and centered in the middle of the board, the only thing to see in this step.
- **Declaring defenders**: the defender cannot see their provinces nor the dynasty cards on them; let them unfold them (a folded line), as the attacker does while declaring the conflict.
- **Types of the messages** generated from `schemas/messages` (`json-schema-to-typescript`): the client writes its payloads by hand for now.
- **Mulligan**: setup has no mulligan yet (the copy of the opponent screen does not promise one).
- **Bot**: duels (statistic, end of the duel), playing characters, declaring conflicts and defenders; a bot stuck in a step waits for the next message (or `app:bot:play`).
- **Discard piles and pointing at a card**: let a player look at the discard piles (theirs and the opponent's: they are public), and point at the cards there (pointing at a card is done: `point_at_card`, "Point at it" in the card opened, for the cards on the board); show the card pointed at to the opponent (highlighted on the board from the last log entry, by its id).
- **Players with an account**: the list and the edition of their decks; "Leave the game" at the end of a game leads a player with an account to the queue (the home page for now).
- **Production**: the nginx configuration serving `front/dist`, HTTPS (required by the `.app` domain), the public URL of Mercure.
- `npm audit`: 4 high vulnerabilities in a dependency of the lint tooling (`braces` through `@vue/eslint-config-typescript`), not shipped with the site; the fix is a breaking downgrade: wait for an update.

### API

- **Fate from a fate pool**: `PlaceFate` places fate from the supply only; pool → character (and spending fate from a pool for an ability) waits for `MoveFate` (see the analysis of fate in the action abilities below).
- **Reading API**: `SeeCard` (see the decisions taken for the reading API).
- **AI agents**: play a game against the agent (`agent/play.sh`) and fix what it reports; a compact view of the game if the context fills; a timeout for an agent that stops; the rules as MCP resources for agents without a file system.
- **`SeeCard`**: it can write a log entry whose card is visible to the player only (`#[VisibleTo]`): the log delivers the card, without a private Mercure topic, and the opponent sees the action.
- **Feedback of the players**: read the reasons of the concessions (payload of the `ConcedeGame` events), e.g. a console command or a listener of `GameConcededEvent`.
- **Decks**: deleting a deck; the text export of EmeraldDB; the banned and restricted lists of a card pool.
- **Development console**: commands for what is not in the API yet (`SeeCard`, spectators).
- **Authentication**: guests and accounts with a password are done (`POST /api/guests`, `POST /api/accounts`, `POST /api/sessions`); a limit of rate on the creation of guests and accounts (by IP); linking an account to Discord or Google (OAuth2), pressed on the players since there is no email to recover an account; sessions expiring after a few hours, renewed by the web client. A human linked to a real identity creates bots and their API keys (revocable).
- **Mercure**: the subscriber token of a revoked session stays valid until it expires (an hour); `SeeCard` or spectators may need topics of their own (`/users/{id}` is there for what a player is shown alone).
- **Rewinding a game**: deleting the last events changes nothing (the state lives in the entities, the events are its record); rewind by replay instead, e.g. `app:game:rewind <game> <version>`: read the events up to the version, delete the game (events, log entries, players and cards cascade), replay them with `EventPlayer` (same seed, same player ids, so the same card ids and log), in a transaction. Caveats: a deck changed or deleted since `JoinGame` (read by id at replay) or an engine changed since makes the replay diverge or fail; the house bot must not play during the replay (`HouseBotMiddleware` schedules each message, it plays on `kernel.terminate`); the clients get a notification per message replayed.


### Step 1.4 (play cards from provinces)

- **Discard a duplicate** (`DiscardDuplicate`): discard a copy of a unique character from hand or province to place 1 fate on the copy in play; the province is then refilled (`ProvinceRefiller::refillIfEmpty()`).

### Action abilities

- **Costs and effects of abilities**: `BowCard` / `ReadyCard`, `LoseHonor` / `GainHonor` / `TransferHonor`, `GainFate` / `LoseFate` (fate pool of a player; losing more than the pool holds is rejected, like `RemoveFate`), `HonorCharacter` / `DishonorCharacter`, `SetCharacterSkill` / `ResetCharacterSkill`, `PlaceFate` / `RemoveFate`, `DiscardCard`, `DiscardRandomCardFromHand`, `SacrificeCard`, `ReturnCardToHand`, `MoveToConflict` / `MoveHome` exist. Missing: spending or moving fate (`MoveFate`), the province and information messages. See the analysis below.

#### Messages needed for costs and effects

Analysis of the 488 cards of the fixtures with an `Action:` ability (200 characters, 174 events, 44 attachments, 32 holdings, 25 provinces, 13 strongholds), grouped as generic board manipulations. Counts are approximate (keyword matches).

**Physical status of a card in play**

- `BowCard` / `ReadyCard` (81 / 36): any card type (character, attachment, stronghold). Done.
- `HonorCharacter` / `DishonorCharacter` (50 / 50): one step at a time ("honor it again" = two messages). Status goes in `CardState`. Done.
- `DiscardStatusToken` (18; Soshi Illusionist, Liar's Mask), `MoveStatusToken` (Origami Master, Bayushi Dairu).

**Fate**

Fate moves between many holders: player pool → ring ("spend 1 fate to an unclaimed ring") or → supply ("spend 1 fate"), character → supply ("remove") or → owner's pool (Jade Tetsubō, Yogo Junzo), supply → character ("place"), character → character (Karmic Twist), player → player ("give your opponent 1 fate", Levy), supply → player ("gain 1 fate"), ring → ring or character (Jade Masterpiece, Being and Becoming).

Recommendation: a single `MoveFate(source, target, amount)` with typed holders (player pool, card, ring, supply). Rings are not modeled yet: start with pools, cards and supply.

**Player honor**

- `LoseHonor` (~30): the most common cost; honor goes to the supply. Done.
- `GainHonor`: done.
- `TransferHonor(from, to)` (~9): "give your opponent 1 honor", "take 1 honor from that opponent". Done.

**Moving cards between stacks**

- `SacrificeCard` / `DiscardCard` (29 / ~30; done): same physical effect (in play → discard pile), kept apart for a readable event log (sacrifice is a cost).
- `ReturnCardToHand` (7; Adept of Shadows, Court Mask, Niten). Done.
- `DiscardFromHand` (chosen card: `DiscardCard`) and `DiscardRandomFromHand` (the engine picks; Kuni Yori, Thunder Guard Elite: `DiscardRandomCardFromHand`). Done.
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

- ~~`MoveToConflict` / `MoveHome` (32 / 37)~~ (done).
- ~~Duels (34): initiate, honor bid, result.~~ (done)
- Skill and glory modifiers "until the end of the conflict" (the most common effect: ~150 mil/pol mentions, 92 "until the end of the conflict"). Either a generic `AddSkillModifier(card, mil, pol, glory, duration)`, or (preferred, consistent with `CollectFate`) players declare the totals when the conflict is resolved.
- Rings (claim, return, switch, move the contested ring), discarding the Imperial Favor, taking control of a card, switching the conflict type, province strength.

**Architecture points raised by these cards**

1. **Who may send effect messages**: about 30 cards make the opponent act during the player's action ("your opponent selects one", "must choose", "each player chooses"). `NotActivePlayerException` would block them. Effect messages should be accepted from any player while an ability is being resolved (opened by `TriggerActionAbility`, closed by `EndResolution`), not only from the active player.
2. **Targets**: most effects target an opponent's card, so messages take any `cardId`, not only the sender's cards.
3. **Randomness and visibility** are the only places where the engine does more than move pieces.
4. ~~**Resetting `CardState` when a card leaves play**~~ (done, `LeavePlayListener`).

**Suggested priorities** for the dynasty phase action window:

1. ~~`BowCard` / `ReadyCard`~~ (done)
2. ~~`LoseHonor`, `TransferHonor`~~ (done)
3. `MoveFate`
4. `HonorCharacter` / `DishonorCharacter` / `DiscardStatusToken`
5. ~~`SacrificeCard` / `DiscardCard`, `DiscardFromHand`~~ (done)
6. Province messages (turn, discard, `FillProvince` faceup)
7. Information messages (look, search, reveal) with `PutCardOnDeck` and `TakeCardIntoHand`

### Conflict phase

- **A War on Two Fronts**: an effect message adding an attacked province to the conflict (`ConflictState::attackedProvinceIds` is ready for it).
- **Other conflict effects**: moving the contested ring to another province, switching the conflict type or the contested ring, discarding the Imperial Favor, taking control of a card.
- **Reactions**: reaction windows only exist where listed above; reactions to other framework steps (e.g. characters discarded during the fate phase) go through the next window. To be locked down once the game has been tested.
- **Skill announcements**: after each action of the conflict action window, a player should announce the current skill of both sides (3.2.2); not recorded.

### Game rules not enforced yet

- **Stronghold province chosen once**: a player can choose their stronghold province several times during setup (`choose_stronghold`); a second choice must be rejected.
- **Uniqueness**: a player cannot have two unique characters with the same title in play.
- **Mulligan**: players cannot replace cards of their starting hand or provinces during setup.
- **Limited** keyword and "max X per conflict / round" restrictions on playing cards.

### Model

- **Honor not set**: the honor of a player is not checked to be set (`null` before `SetHonor`).

