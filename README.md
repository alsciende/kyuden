# L5R Game Engine

Server-side engine to play **Legend of the Five Rings LCG** (Fantasy Flight Games). PHP 8.4, Symfony 7.4, Doctrine ORM on PostgreSQL, Symfony Messenger, Symfony Workflow, Mercure.

## MVP scope

- The engine acts as a **board**: it enforces the game flow (rounds, phases, framework steps) but **does not enforce card rules**.
- Only the **Stronghold format** (two players) is in scope.

The state of the implementation and the open points are listed in `HANDOFF.md`.

## Architecture

### Domain model (`src/Entity`)

- `LogicalCard`: the card as printed (title, cost, text), grouped in `Pack` via `Printing`.
- `PhysicalCard`: one copy of a card in a game, owned by a `Player`. Mapped superclass with one subclass per type in `src/Entity/CardTypes` (Conflict, Dynasty, Province, Role, Stronghold).
- `Stack`: ordered collection of physical cards (deck, hand, province, etc.; see `Enum/StackType`). The top card has the highest `position`. When order matters, use `getOrderedCards()` / `getTopCards()`: the order of `getCards()` is not kept in sync with positions (e.g. after a shuffle).
- `Enum/Orientation`: physical orientation of a card (`FaceDown`, `FaceUp`, `Sideways`, `UpsideDown`), stored on `PhysicalCard`. A new card takes the initial orientation of its class (`getInitialOrientation()`: face down, except stronghold and role); afterwards only workflow transitions change it.
- `Deck`: list of card codes; turned into physical cards by `Factory/CardFactory` when a game starts.
- `Event`: persisted record of every handled action.
- `Candidate`: a player waiting in the matchmaking queue.

### State

`Game`, `Player` and `PhysicalCard` implement `State/Model/StatefulInterface`: they carry a JSON `state` field, decoded by the managers in `src/State/Manager` (`GameStateManager`, `PlayerStateManager`, `CardStateManager`) into `GameState` / `PlayerState` / `CardState` (fate on the card). Modify it through `withState(closure)`, which decodes, runs the callback and re-encodes.

### Action pipeline

1. A player action is a Messenger message in `src/Message`, implementing `ActionInterface` (or `PlayerActionInterface` when it has a player) and tagged with `#[AppAction]`, which makes it discoverable by `Service/ActionMessageRegistry`. Messages hold ids (game, player, card) as strings, not entities.
2. Its handler in `src/MessageHandler` (`#[AsMessageHandler]`, `final readonly`) loads entities through repositories and changes the state, usually by applying a transition on the relevant workflow.
3. Middlewares (order in `config/packages/messenger.yaml`; each forwards down the chain first, then acts):
   - Doctrine transaction: commits everything at the end.
   - `WorkflowTransitionMiddleware`: repeatedly applies any enabled game transition marked `metadata.auto: true`.
   - `EventRecordingMiddleware`: saves the message as an `Event`.
4. `Service/EventPlayer` deserializes a stored `Event` and re-dispatches it, so a game can be replayed from its event log. Messages must therefore stay serializable.

The HTTP API is a stub: `ApiGamesController` returns the default scaffold, and the event controller (receiving player events, converting them to messages and responding) is still TODO. Console commands (`src/Command`) are currently the main way to drive the engine.

### Workflows

Six `state_machine` workflows defined in PHP in `config/packages/workflows.php` (Symfony 7.4 array-shape config, `App::config()`; keys are checked by PHPStan against `config/reference.php`): `game` plus one per card type (e.g. dynasty card: `draw_card`, `turn_faceup`, `turn_facedown`, `play`, `discard`). Use the place constants of the card classes and enums in metadata (`StackType::Hand`, `Orientation::FaceUp`), and the helpers of `Workflow/WorkflowConfig` (`transition()`, `cardWorkflow()`, `bowAndReady()`, `triggerAction()`). Only the `game` places carrying metadata are declared (`places`); the others come from the transitions. Audit trail is on (`workflow` log channel).

**Card place = stack type + orientation.** A card's workflow place is not stored: `PhysicalCard::getCurrentPlace()` computes it with the `getPlace(StackType, Orientation)` mapping of each card class (e.g. a province `UpsideDown` is broken, a dynasty card `FaceUp` in a province is `on_province_faceup`). A place stands for a set of available transitions, so orientations that do not change them share a place: a bowed card (`Sideways`) is still `in_play`, and `bow` / `ready` are transitions from `in_play` to itself. Only what is not a physical position (honor status, fate tokens…) goes in the card `state`. Card workflows use `Workflow/CardMarkingStore`, which reads that computed place and, after a transition, checks that the card really ended up in the target place.

Card transitions declare their effect in metadata, applied by the generic `Workflow/CardTransitionListener`: `stack` (StackType the card moves to) and `orientation` (Orientation it takes; required on a move, otherwise the card keeps its orientation). Target stack: a character entering play gets a new stack (`PhysicalCard::entersPlayInNewStack()`); otherwise the context `stack` (e.g. the character or province an attachment goes on, possibly an opponent's), else the player's unique stack. `CardMarkingStore` rejects a target stack the card cannot be in. Write a specific listener only for what metadata cannot express. Never move or turn a card outside a transition.

The `game` workflow models the round sequence; a new place exists when the phase **and** the available player actions differ. Place metadata:

- `action_window`: players can trigger action abilities (`TriggerActionAbility`); the `trigger_action` transition of a card workflow (from a place to itself) lists where the abilities of a card can be used.

Behaviour lives in listeners under `src/Workflow/<WorkflowName>/`, named after the transition/place and the event type: `...GuardListener` (blocks a transition until conditions are met), `...TransitionListener`, `...EnterListener`.

### Game flow

- **Values from players**: since the engine does not apply card effects, a step whose outcome cards can modify (e.g. the amount of fate collected) is a player action carrying the value; an `auto` transition guarded on every player having acted then moves the game on.
- **Turn records**: what a player did during the round goes in the turn records of `PlayerState`, reset at the end of the round (`t36`).
- **Player order**: stored in `GameState` (`playerIds`, fixed by `start_game`; the first player changes, not the order).
- **Opportunities to act**: steps resolved in player order use the active player of `GameState` (`activePlayerId`). The enter listener of the step activates the first player; the handler of each action rejects a player who is not active (`NotActivePlayerException`) and gives the opportunity to act to the next player (`GameState::getNextPlayerId()` / `getPlayerIdsAfter()`, resolved with `Game::getPlayer()`). A player who passes (`PlayerState::passed`, reset by the enter listener of the step) is skipped by `Service/OpportunityService`. An action that requires follow-up actions keeps the opportunity with the player, who ends it with `EndOpportunity`.
- **Costs and effects**: board manipulations (`BowCard`, `LoseHonor`…) are accepted in any place, from any player of the game, on any card or player, since the engine does not know which ability is being resolved.

### Other services

- Matchmaking: `Service/MatchMaking/FifoMatchMakingService`, then `Service/GameLauncher/AutomaticGameLauncher` creates the game.
- Notifications: `NotificationInterface` objects sent through `NotifierInterface`; `MercureNotifier` publishes server-sent events, `LoggerNotifier` logs them.
- Security: access-token authentication (`Security/AccessTokenHandler`).
- Exceptions: `Exception/Data` (missing/mismatched data) vs `Exception/Rules` (game-rule violations).
