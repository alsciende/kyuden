# HANDOFF

Points to address after `PlayCharacterFromProvince` and `TriggerActionAbility` (step 1.4, play cards from provinces).

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

## Next steps

### Step 1.4 (play cards from provinces)

- **Discard a duplicate** (`DiscardDuplicate`): discard a copy of a unique character from hand or province to place 1 fate on the copy in play; the province is then refilled.

### Action abilities

- **Costs and effects of abilities**: no action exists yet to bow or ready a card, spend fate, lose honor, move fate, sacrifice or dishonor a card. `TriggerActionAbility` is of little use until some of them exist.
- **Event cards** (`PlayEvent`): an event with an action trigger is played from hand and goes to the discard pile; the current `conflict_card.play_card` transition leads to `in_play`.

### Other steps

- **Action windows** (conflict phase): passing does not forfeit the window; it closes when every player has passed in sequence. `passed` will need to be reset when a player acts, and a generic pass message (without the +1 fate of `PassDynasty`) will be needed.

### Game rules not enforced yet

- **Uniqueness**: a player cannot have two unique characters with the same title in play.
- **Empty dynasty deck**: refilling from an empty deck currently throws `DrawingFromEmptyDeckException`; the real rule (lose honor, shuffle the discard pile into a new deck) is not implemented.
- **Automatic refill**: per the glossary, a province emptied of its dynasty card refills automatically. Kept manual for now (`FillProvince` by the player).
- **FillProvince checks**: neither the owner of the province nor the presence of a dynasty card already on it is checked.

### Model

- **Card state when leaving play**: `CardState` (fate on the card) is not reset when a card leaves play; it must be before the card can enter play again (discard, return to hand…). Personal honor status will go there too.
- **Holdings**: a faceup holding in a province is in play; discarding it during the fate phase (`discard_from_provinces`) is not possible with the current `dynasty_card.discard` transition, which starts from `in_play`.
