# HANDOFF

## Current status

Early stage. Implemented: matchmaking, game creation/setup, stronghold province choice, deck shuffling, drawing, filling provinces, the dynasty phase (discarding duplicates is not implemented yet) the honor bid and the reveal of the honor dials of the draw phase (latest: `honor_dials_revealed` and `SetHonorBid`).

From `transfer_honor` onward, `game` transitions are placeholders (`t8`, `t9`, …) with no logic and no `auto` flag.

## Decisions taken for the dynasty phase

- `collect_fate` and `play_cards_from_provinces` are resolved in player order (active player of `GameState`).
- In `play_cards_from_provinces`, `PassOpportunity` passes: the first to pass gains 1 fate (`PlayCardsFromProvincesOpportunityPassedListener`), the last to pass ends the step.

## Decisions taken for `PassOpportunity`

- Generic pass, allowed in any `game` place with the `opportunities` metadata (`play_cards_from_provinces`, `reveal_honor_dials`). The rules of a step go in listeners of `OpportunityPassedEvent`, which check the game place.
- Passing is final: the player is no longer active and is skipped until the end of the step, which ends once every player has passed (`AllPlayersPassedGuardListener`, one `AsGuardListener` attribute per transition).
- Several players can be active at once (`GameState::activePlayerIds`): players acting simultaneously keep their opportunity until they pass, and `EndOpportunity` changes nothing for them. The opportunity goes to the next player only when no player is active anymore.

## Decisions taken for the reveal of the honor dials

- `reveal_honor_dials` is a step where every player is active (`RevealHonorDialsEnterListener`), to react to the honor dials being revealed (e.g. Kitsuki Jusai, cards changing a bid by 1), then pass. `honor_dials_revealed` moves on to `transfer_honor` once every player has passed. Passing is final, although a player could react to an opponent's reaction: acceptable since each of these cards reacts once.
- `SetHonorBid` changes the bid on the dial of a player. Like the other effect messages, it is accepted in any place, from any player of the game, on any player. The player must have bid this round (`NoHonorBidException`); the bid can be 0 (cards decrease it "to a minimum of 0"), not negative.
- The bids are not revealed by the engine: their secrecy is not enforced.

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

### Other steps

- **Action windows** (conflict phase): passing does not forfeit the window; it closes when every player has passed in sequence. `passed` will need to be reset when a player acts, and `PassOpportunity` will need to support this mode (e.g. with a place metadata).

### Game rules not enforced yet

- **Uniqueness**: a player cannot have two unique characters with the same title in play.
- **Empty dynasty deck**: refilling from an empty deck currently throws `DrawingFromEmptyDeckException`; the real rule (lose honor, shuffle the discard pile into a new deck) is not implemented.
- **Automatic refill**: per the glossary, a province emptied of its dynasty card refills automatically. Kept manual for now (`FillProvince` by the player).
- **FillProvince checks**: neither the owner of the province nor the presence of a dynasty card already on it is checked.

### Model

- **Honor limits**: `PlayerState::setHonor()` throws `MinimumHonorReachedException` / `MaximumHonorReachedException` when honor reaches 0 or 25, which rolls the action back. Per the rules, the player then loses / wins the game: the end of the game is not modeled yet. The honor of a player is not checked to be set (`null` before `SetHonor`).

- **Card state when leaving play**: `CardState` (fate on the card) is not reset when a card leaves play; it must be before the card can enter play again (discard, return to hand…). Personal honor status will go there too.
- **Holdings**: a faceup holding in a province is in play; discarding it during the fate phase (`discard_from_provinces`) is not possible with the current `dynasty_card.discard` transition, which starts from `in_play`.
