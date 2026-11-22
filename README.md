# Kyūden

[![Test](https://github.com/alsciende/kyuden/actions/workflows/test.yml/badge.svg?branch=main)](https://github.com/alsciende/kyuden/actions/workflows/test.yml)
[![codecov](https://codecov.io/github/alsciende/kyuden/graph/badge.svg)](https://codecov.io/github/alsciende/kyuden)

[kyuden.app](https://kyuden.app): server-side engine to play **Legend of the Five Rings LCG** (Fantasy Flight Games). PHP 8.5 (FrankenPHP), Symfony 7.4, Doctrine ORM on PostgreSQL, Symfony Messenger, Symfony Workflow, Mercure; and its web client (`front/`): Vue 3, Vite, TypeScript.

## Installation

You need git, make and Docker.

```shell
git clone <this repo>
cd kyuden
make build
make up
make install
make db
make front-install
open http://localhost:8080
```

The database of development holds users with known passwords and tokens (`src/DataFixtures/UserFixtures.php`): john and jane have an account, harry and sally are guests.

## Scope

- The engine acts as a **board**: it enforces the game flow (rounds, phases, framework steps) but **does not enforce card rules**: the players apply the cards with generic effect messages.
- Only the **Stronghold format** (two players) is in scope.

The state of the implementation, the decisions taken and the next steps are in `HANDOFF.md`.

## Everyday commands

Everything runs in Docker through the Makefile:

- `make up` / `make down` / `make sh`: start, stop, a shell in the container of Symfony
- `make db`: recreate the database and load the fixtures; `make db-test` for the test database
- `make test`: PHPUnit, then Vitest
- `make lint`: PHPStan, Rector (dry run), `vue-tsc`, ESLint, Prettier

`CLAUDE.md` lists every target and the console commands.

## Repository

| Path | What |
| --- | --- |
| `src/Message`, `src/MessageHandler` | the actions of the players and their handlers |
| `src/Entity`, `src/State` | the domain model and the state of a game |
| `config/packages/workflows.php`, `src/Workflow` | the game and card workflows, and their listeners |
| `src/Controller`, `src/View`, `src/Mcp` | the HTTP API, its views, the MCP server for bots |
| `src/CardScript` | what the text of a card means for a game in progress |
| `src/Service/Bot` | the house bot |
| `front/` | the web client (Vue 3) |
| `fixtures/` | the card database, the starter decks |
| `schemas/messages` | the JSON schemas of the public messages |
| `docker/` | the development and production stacks |
| `doc/` | the rules of the game and the documentation of the project |

## Documentation

The project:

- [`doc/architecture.md`](doc/architecture.md): the domain model, the state, the action pipeline, the workflows, the game log
- [`doc/game-flow.md`](doc/game-flow.md): how the engine implements the rules, and the effect messages
- [`doc/api.md`](doc/api.md): the HTTP API and the MCP server
- [`doc/web-client.md`](doc/web-client.md): the screens of the web client and its identity
- [`doc/card-scripts.md`](doc/card-scripts.md): the scripts of the cards, and how to add one
- [`doc/reactions.md`](doc/reactions.md): the reactions of the cards by triggering condition, the order in which the engine offers them
- [`doc/interrupts.md`](doc/interrupts.md): the design of the interrupt windows (to come), the interrupts of the cards by triggering condition
- [`doc/services.md`](doc/services.md): the house bot, the replay, the trophies, the worker, matchmaking, spectators, security, Mercure
- [`doc/production.md`](doc/production.md): the image, continuous integration, releases and deployment
- [`HANDOFF.md`](HANDOFF.md): status, decisions and next steps; [`TROPHIES.md`](TROPHIES.md): the catalogue of the trophies

The rules of the game (the source of truth): [`doc/learn_to_play.md`](doc/learn_to_play.md), [`doc/timing-and-gameplay.md`](doc/timing-and-gameplay.md), [`doc/glossary.md`](doc/glossary.md), [`doc/card-clarifications.md`](doc/card-clarifications.md), [`doc/symbols.md`](doc/symbols.md).

## License

The code of Kyūden is under the [PolyForm Noncommercial License 1.0.0](LICENSE.md): anyone may use it, fork it, change it and distribute it for any noncommercial purpose, keeping the license and its `Required Notice` line. Any commercial use needs the written agreement of the author. The project takes no pull request: fork it.

The license covers the code only, not what belongs to others:

- *Legend of the Five Rings*, its rules (`doc/learn_to_play.md`, `timing-and-gameplay.md`, `glossary.md`, `card-clarifications.md`, `symbols.md`), the names, texts and images of its cards (`fixtures/`, the card backs of `front/src/assets/images`) and its graphic designs, the clan mons and the symbols of the game among them (`front/src/assets/mons`, the icon font `front/src/assets/fonts/fiveringsdb.woff`, the icons of `front/public/icons`: the files were made for fiveringsdb, the designs are not the author's), belong to Fantasy Flight Games. Kyūden is a fan project, neither affiliated with nor endorsed by Fantasy Flight Games.
- The icons of the actions (`front/src/lib/actionIcons.ts`) are from [Lucide](https://lucide.dev), under the ISC license.
- The fonts (EB Garamond, Shippori Mincho, Barlow) are under the SIL Open Font License.
- The dependencies (`vendor/`, `node_modules/`) keep their own licenses.
