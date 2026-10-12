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
- `Stack`: ordered collection of physical cards (deck, hand, province, etc.; see `Enum/StackType`).
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

Six `state_machine` workflows in `config/packages/workflows/`: `game` plus one per card type (e.g. dynasty card: `draw_card`, `turn_faceup`, `turn_facedown`, `play`, `discard`). Audit trail is on (`workflow` log channel). Diagrams in `doc/workflows/`.

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
- Build the game state a test needs with `$this->scenario()` (`tests/Builder/GameBuilder.php`) instead of dispatching the actions leading to it. It writes workflow places directly, infers card places from their stack, and exposes players and cards by alias through `Scenario`. `build()` keeps everything in memory; `persist()` flushes, for handlers that load entities by id.
- Test at the narrowest level: guards with `Registry::get($game)->can()`, listeners and handlers by invoking them directly (no auto transitions), the message bus only to check automatic transitions.
- `tests/MessageHandler/MessageHandlerTest.php` is the single end-to-end setup scenario, kept as a safety net.

## Conventions

- `declare(strict_types=1);` in every file; `readonly` services with constructor promotion; `#[\Override]` on interface implementations.
- Code style is enforced by ECS, Rector and PHPStan (`ecs.php`, `rector.php`, `phpstan.neon`).
- Tests live in `tests/`, mirroring `src/`; kernel tests extend `AbstractKernelTestCase`.
