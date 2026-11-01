# HANDOFF

## Current status

Every phase of the round is modeled: a game loops from round to round until a victory condition is met. The engine is a board (see README): the players apply the cards with generic effect messages, which now cover the cards of the Core Set but those listed under "Next steps". Around it: the HTTP API, the MCP server for bots, the house bot, accounts (guests, password, linked providers), the queue, friendly games (own bot or a friend), the web client and the production stack (FrankenPHP, CI, releases on `ghcr.io`), deployed at kyuden.app (DigitalOcean).

## Principles to keep

- **The engine moves pieces, the players apply the cards.** Effect messages (bow, honor, fate, discard, move…) are accepted in any place, from any player, on any card: about 30 cards make the opponent act during the player's action, and most effects target the opponent's cards. Values that cards modify (skills, province strength, glory, dueling statistic, costs) are declared by the players, prefilled by the clients; the engine applies the rules to them. It does not check triggering conditions, timings of the abilities of cards (cards gain and lose abilities), limits per round, attachment restrictions, nor that a ring effect matches the ring.
- **Randomness and visibility** are the only places where the engine does more than move pieces: every draw goes through the generator of the game (replay), and what a player sees depends only on the state, secrets stored apart (`pendingHonorBid` / `honorBid`).
- **A card gets a new id when the players lose track of it** (a shuffle), never otherwise: a card returned to hand or put on a deck without a shuffle stays known, as at a real table.
- **No backward compatibility of the event log**: messages were renamed several times (`EndResolution`, `PlayActionEvent`…), and older events cannot be replayed. Acceptable while no game is live; to reconsider once one is.
- **Interrupts and reactions** are accepted from any player in any place and ended with `EndResolution`; the interrupt and reaction windows of the rules (first player first, consecutive passes) are not modeled. The `react_to_*` places of the conflict are generous on purpose: to lock down once the game has been tested.
- **Accounts**: no email nor personal data; an account is recovered through the providers it is linked to, which the site should press players to link. Only a bot gets API keys, so that a program is always known as a bot; a human helped by an AI is a matter of the terms of use. The `playerId` is public, never a credential.
- **Mockups first** for the screens of the web client: the board and the cards looked at (Claude artifact `GiPbGSPNcvLD2r9eFKKNp5`), the accounts, the queue and the friendly games (`LBUBwNBPdSopj5NMj5GLTL`).

## Known limitations

- **Rules not enforced**: uniqueness, mulligan, the Limited keyword and "max X per conflict / round", covert (the keyword and the number of evaded characters), ancestral attachments (discarded with their character). The end of a lasting effect is not handled: a player takes control back with `TakeControl` (Blackmail).
- **Web client**: covert is not offered (`evaded_ids` sent empty); a multi-province conflict shows its first province only; no round number; no layout of its own for the draw and fate phases, the duels and the reaction windows.
- **House bot**: plays no conflict card and triggers no ability, always puts the Imperial Favor on its military side, never discards from its provinces, never ends a duel; a bot stuck in a step waits for the next message (`app:bot:play`).
- **Agents (MCP)**: no game played by an agent yet; an agent that stops without conceding blocks the game (no timeout); the view is sent whole on each `get_game`; check that clients keep the whole `instructions` (about 9 kB).
- **Scaling**: each long-poll holds a FrankenPHP thread for up to 30 s (classic mode, 20 threads); the OAuth state, the rate limiter and the MCP sessions live in filesystem caches (one host only; MCP sessions lost on restart).
- **Security**: a Mercure subscriber token outlives a revoked session by up to an hour; no rate limit on creating guests and accounts; deleting an account asks no re-authentication (Symfony 8.2 brings one).
- **Decks of EmeraldDB**: the decks of the Emerald Legacy card pool are refused (their new cards are unknown to the engine); a deck whose page EmeraldDB does not show (private, not published) cannot be downloaded: only what `/api/decklists/<id>` answers without authentication.
- **Data**: card images point at FFG's CloudFront name (`d2qmv0gg5fizll.cloudfront.net`: the FFG CDN name has a mismatched certificate), which may change; the banned and restricted lists are not checked, and copies are counted by title where EmeraldDB counts by version.
- `npm audit`: 4 high vulnerabilities in the lint tooling (`braces`), not shipped; the fix is a breaking downgrade, wait for an update.

## Next steps

### Messages left (from the review of the Core Set)

- Playing an attachment or an action event from the conflict discard pile or the top of the conflict deck (Guidance of the Ancestors, Artisan Academy): `play_card` already allows the discard pile, `HandService::assertPlayable()` and the client only offer the hand.
- A character played as an attachment (Tattooed Wanderer, Togashi Kazue): a new place of the conflict card workflow, last.
- Beyond the Core Set: `TurnDynastyCardFacedown` (Governor's Spy, which should also renew ids), `TurnProvinceFacedown`, `FillProvince` face up, `MoveStatusToken`, `RemoveFromGame`, adding an attacked province (A War on Two Fronts: `ConflictState::attackedProvinceIds` is ready), moving the contested ring to another province, discarding the Imperial Favor.
- Not planned: `DiscardStatusToken` (`HonorCharacter` / `DishonorCharacter` make a character ordinary), `ReturnRing` (covered by `SwitchRing`); Breakthrough needs a place or a flag of the `game` workflow.

### Web client

- Choosing the stronghold province: the five provinces larger and centered.
- Dedicated layouts for the remaining steps (see the limitations), and a component test of `GameView`.
- Discard piles: point at their cards, and highlight the card pointed at for the opponent (from the last log entry).
- Accounts: list and edit their decks; "Leave the game" leads an account to the queue.

### API and platform

- **Agents**: play a game against `agent/play.sh` and fix what it reports; a timeout for an agent that stops; a compact view if the context fills.
- **Feedback**: read the reasons of the concessions (payload of the `ConcedeGame` events): a console command or a listener of `GameConcededEvent`.
- **Decks**: a deck of EmeraldDB is imported from the URL of its page (`EmeraldDbDeckFetcher`: `POST /api/decks` with `url`, `app:deck:import`, `/upload-deck <url>` in the development console, and the field of the queue screen, friendly games included: mockup in the row "Play a game: the queue" of `LBUBwNBPdSopj5NMj5GLTL`); importing does not join the queue, the player still clicks. A user deletes their own decks (`DELETE /api/decks/{id}`, soft-deleted once played, restored when imported again; the button on the tiles of the queue: mockups "hovering one of your decks shows Delete", "confirm before deleting a deck", "deck deleted"), and identical decks are found by their content hash (`Deck::contentHash`, not indexed: an index across users when statistics need it). Then: the text export of EmeraldDB, the banned and restricted lists.
- **Authentication**: rate limit the creation of guests and accounts; press the players to link a provider; sessions expiring after a few hours, renewed by the client.
- **Ranking** of the games that are not friendly.
- **Mercure protocol 1.0**: leave the compatibility mode (`protocol_version_compatibility 8`): `protocol_version: 1.0`, the RFC 9068 claims in the tokens of `SubscriptionFactory` and of the publisher, an `issuer` block in the `Caddyfile`, the topics as matchers; then check the clients (`front/src/lib/hub.ts`, `assets/console/mercure.js`) and the tests of the private topics.
- **Rewinding a game** (`app:game:rewind <game> <version>`): deleting events changes nothing (the state lives in the entities), so replay instead: read the events up to the version, delete the game, replay them with `EventPlayer` in a transaction. Caveats: a deck or an engine changed since makes the replay diverge; the house bot must not play during it; the clients get a notification per message.
