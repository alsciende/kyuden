# CLAUDE.md

@README.md

## Documentation

- `README.md` (imported above) is the single architecture reference: update it when the architecture changes, and only there.
- `HANDOFF.md` holds the current status, the decisions taken for each feature and the next steps: update it when a feature is done.

## Commands

Everything runs in Docker (service `symfony`: PHP-FPM, served by the `nginx` service on port 8080) through the Makefile:

- `make up` / `make down` / `make build` / `make sh`
- `make db`: drop and recreate the schema, then load fixtures (packs, cards, starter decks)
- `make test`: PHPUnit
- `make lint`: `lint:container` + Rector + PHPStan; run it before considering a change done
- `make sql`: `psql` shell on the `database` service

Console commands (`src/Command`): create decks/candidates, matchmaking, create a game, list/play events, apply a game transition, dump a game, dump the JSON schemas of the messages (`app:message-schemas:dump`, after changing a public message).

Use the custom makers to scaffold new code: `MakeAction` (message + handler) and `MakeWorkflowListener` (`src/Maker`).

After changing a workflow, compare `bin/console workflow:dump <name>` before and after.

## Game rules reference

`doc/` contains the official rules, which are the source of truth when implementing phases. Only the Stronghold format is in scope: ignore the rules specific to other formats (skirmish, enlightenment, team conquest…).

- `learn_to_play.md`: introductory rules
- `timing-and-gameplay.md`: framework steps and action windows of a full round; follow it for the `game` workflow
- `glossary.md`: rules glossary
- `card-clarifications.md`: per-card rulings
- `symbols.md`: card symbols
- `game.md`: queue and game creation from the app's point of view

## Tests

- `dama/doctrine-test-bundle` wraps each test in a rolled-back transaction: tests cannot share data, so never chain them with `#[Depends]`. Logical cards and starter decks come from the fixtures loaded by `make db`.
- Build the game state a test needs with `$this->scenario()` (`tests/Builder/GameBuilder.php`) instead of dispatching the actions leading to it. Once the game has started, the player order is the declaration order of the players. It writes the game place directly, puts cards directly in their stacks (unless stated: face down in decks and provinces except the stronghold, face up elsewhere), and exposes players and cards by alias through `Scenario`. `build()` keeps everything in memory; `persist()` flushes, for handlers that load entities by id.
- Test at the narrowest level: guards with `Registry::get($game)->can()`, listeners and handlers by invoking them directly (no auto transitions), the message bus only to check automatic transitions.
- `tests/MessageHandler/MessageHandlerTest.php` is the single end-to-end setup scenario, kept as a safety net.

## Conventions

- `declare(strict_types=1);` in every file; `readonly` services with constructor promotion; `#[\Override]` on interface implementations.
- Code style is enforced by Rector and PHPStan (`rector.php`, `phpstan.neon`).
- Tests live in `tests/`, mirroring `src/`; kernel tests extend `AbstractKernelTestCase`.
