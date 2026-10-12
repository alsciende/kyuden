# CLAUDE.md

## Project

Server-side engine to play **Legend of the Five Rings LCG** (Fantasy Flight Games). PHP 8.4, Symfony 7.4, Doctrine ORM on PostgreSQL, Symfony Messenger, Symfony Workflow, Mercure.

MVP scope: the engine acts as a **board**. It enforces the game flow (rounds, phases, framework steps) but **does not enforce card rules**.

`README.md` holds the architecture reference; keep it in sync when the architecture changes.

## Commands

Everything runs in Docker (service `symfony`) through the Makefile:

- `make up` / `make down` / `make build` / `make sh`
- `make db`: drop and recreate the schema, then load fixtures (packs, cards, starter decks)
- `make test`: PHPUnit
- `make lint`: `lint:container` + ECS (`--fix`) + Rector + PHPStan; run it before considering a change done
- `make sql`: `psql` shell on the `database` service

Console commands (`src/Command`) are currently the main way to drive the engine: create decks/candidates, matchmaking, create a game, list/play events, apply a game transition, dump a game.

## Architecture

### Domain model (`src/Entity`)

- `LogicalCard`: the card as printed (title, cost, text), grouped in `Pack` via `Printing`.
- `PhysicalCard`: one copy of a card in a game, owned by a `Player`. Mapped superclass with one subclass per type in `src/Entity/CardTypes` (Conflict, Dynasty, Province, Role, Stronghold).
- `Stack`: ordered collection of physical cards (deck, hand, province, etc.; see `Enum/StackType`). The top card has the highest `position`. When order matters, use `getOrderedCards()` / `getTopCards()`: the order of `getCards()` is not kept in sync with positions (e.g. after a shuffle).
- `Enum/Orientation`: physical orientation of a card (`FaceDown`, `FaceUp`, `Sideways`, `UpsideDown`), stored on `PhysicalCard`. A new card takes the initial orientation of its class (`getInitialOrientation()`: face down, except stronghold and role); afterwards only workflow transitions change it.
- `Deck`: list of card codes; turned into physical cards by `Factory/CardFactory` when a game starts.
- `Game` and `Player` carry a JSON `state` field, decoded by the managers in `src/State/Manager` into `GameState` / `PlayerState`. Modify it through `withState(closure)`, which decodes, runs the callback and re-encodes.
- `Event`: persisted record of every handled action.
- `Candidate`: a player waiting in the matchmaking queue.

### Action pipeline

1. A player action is a Messenger message in `src/Message`, implementing `ActionInterface` (or `PlayerActionInterface` when it has a player) and tagged with `#[AppAction]`, which makes it discoverable by `Service/ActionMessageRegistry`. Messages hold ids (game, player, card) as strings, not entities.
2. Its handler in `src/MessageHandler` (`#[AsMessageHandler]`, `final readonly`) loads entities through repositories and changes the state, usually by applying a transition on the relevant workflow.
3. Middlewares (order in `config/packages/messenger.yaml`; each forwards down the chain first, then acts):
   - Doctrine transaction: commits everything at the end.
   - `WorkflowTransitionMiddleware`: repeatedly applies any enabled game transition marked `metadata.auto: true`.
   - `EventRecordingMiddleware`: saves the message as an `Event`.
4. `Service/EventPlayer` deserializes a stored `Event` and re-dispatches it, so a game can be replayed from its event log. Messages must therefore stay serializable.

Use the custom makers to scaffold new code: `MakeAction` (message + handler) and `MakeWorkflowListener` (`src/Maker`).

### Workflows

Six `state_machine` workflows defined in PHP in `config/packages/workflows.php` (Symfony 7.4 array-shape config, `App::config()`; keys are checked by PHPStan against `config/reference.php`): `game` plus one per card type (e.g. dynasty card: `draw_card`, `turn_faceup`, `turn_facedown`, `play`, `discard`). Use the place constants of the card classes and enums in metadata (`StackType::Hand`, `Orientation::FaceUp`), and the helpers of `Workflow/WorkflowConfig` (`transition()`, `cardWorkflow()`, `bowAndReady()`). Audit trail is on (`workflow` log channel). Diagrams in `doc/workflows/`; after changing a workflow, compare `bin/console workflow:dump <name>` before and after.

**Card place = stack type + orientation.** A card's workflow place is not stored: `PhysicalCard::getCurrentPlace()` computes it with the `getPlace(StackType, Orientation)` mapping of each card class (e.g. a province `UpsideDown` is broken, a dynasty card `FaceUp` in a province is `on_province_faceup`). A place stands for a set of available transitions, so orientations that do not change them share a place: a bowed card (`Sideways`) is still `in_play`, and `bow` / `ready` are transitions from `in_play` to itself. Only what is not a physical position (honor status, fate tokens…) goes in the card `state`. Card workflows use `Workflow/CardMarkingStore`, which reads that computed place and, after a transition, checks that the card really ended up in the target place.

Card transitions declare their effect in metadata, applied by the generic `Workflow/CardTransitionListener`: `stack` (StackType the card moves to) and `orientation` (Orientation it takes; required on a move, otherwise the card keeps its orientation). Target stack: a character entering play gets a new stack (`PhysicalCard::entersPlayInNewStack()`); otherwise the context `stack` (e.g. the character or province an attachment goes on, possibly an opponent's), else the player's unique stack. `CardMarkingStore` rejects a target stack the card cannot be in. Write a specific listener only for what metadata cannot express. Never move or turn a card outside a transition.

The `game` workflow models the round sequence; a new place exists when the phase **and** the available player actions differ. Behaviour lives in listeners under `src/Workflow/<WorkflowName>/`, named after the transition/place and the event type: `...GuardListener` (blocks a transition until conditions are met), `...TransitionListener`, `...EnterListener`.

### Other services

- Matchmaking: `Service/MatchMaking/FifoMatchMakingService`, then `Service/GameLauncher/AutomaticGameLauncher` creates the game.
- Notifications: `NotificationInterface` objects sent through `NotifierInterface`; `MercureNotifier` publishes server-sent events, `LoggerNotifier` logs them.
- Security: access-token authentication (`Security/AccessTokenHandler`).
- Exceptions: `Exception/Data` (missing/mismatched data) vs `Exception/Rules` (game-rule violations).

## Current status

Early stage. Implemented: matchmaking, game creation/setup, stronghold province choice, deck shuffling, drawing, filling provinces, and the start of the dynasty phase up to revealing facedown dynasty cards (latest: `RevealDynastyCard`).

From `collect_fate` onward, `game` transitions are placeholders (`t2`, `t3`, …) with no logic and no `auto` flag. The HTTP API is a stub (`ApiGamesController` returns the default scaffold; the event controller is still TODO).

## Game rules reference

`doc/` contains the official rules, which are the source of truth when implementing phases:

- `learn_to_play.md`: introductory rules
- `timing-and-gameplay.md`: framework steps and action windows of a full round; follow it for the `game` workflow
- `glossary.md`: rules glossary
- `card-clarifications.md`: per-card rulings
- `symbols.md`: card symbols
- `game.md`: queue and game creation from the app's point of view

## Tests

- `dama/doctrine-test-bundle` wraps each test in a rolled-back transaction: tests cannot share data, so never chain them with `#[Depends]`. Logical cards and starter decks come from the fixtures loaded by `make db`.
- Build the game state a test needs with `$this->scenario()` (`tests/Builder/GameBuilder.php`) instead of dispatching the actions leading to it. It writes the game place directly, puts cards directly in their stacks (unless stated: face down in decks and provinces except the stronghold, face up elsewhere), and exposes players and cards by alias through `Scenario`. `build()` keeps everything in memory; `persist()` flushes, for handlers that load entities by id.
- Test at the narrowest level: guards with `Registry::get($game)->can()`, listeners and handlers by invoking them directly (no auto transitions), the message bus only to check automatic transitions.
- `tests/MessageHandler/MessageHandlerTest.php` is the single end-to-end setup scenario, kept as a safety net.

## Conventions

- `declare(strict_types=1);` in every file; `readonly` services with constructor promotion; `#[\Override]` on interface implementations.
- Code style is enforced by ECS, Rector and PHPStan (`ecs.php`, `rector.php`, `phpstan.neon`).
- Tests live in `tests/`, mirroring `src/`; kernel tests extend `AbstractKernelTestCase`.
