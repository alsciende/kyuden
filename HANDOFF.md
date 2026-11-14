# HANDOFF

## Current status

A full Stronghold game is playable, from setup (mulligans included) to a victory condition, round after round. The engine is a board (see README): the players apply the cards with generic effect messages, which cover every card of the fixtures except those listed under "Cards not playable yet". Around it: the HTTP API, the MCP server for bots, the house bot, accounts (guests, password, Google and Discord), the queue, friendly games, spectators, the history and the replay of the games, the nobori of the players (its mons and patterns to be earned), the web client (desktop and tablet, phones in landscape, installable) and the production stack, deployed at kyuden.app by each tag `vX.Y.Z` of `main`. Version 1.0 is released, after a full game played against an agent through the MCP server. How it all works is in the README; what follows is why, and what comes next.

## Decisions to keep

- **1.0 is a stable contract**: the HTTP API, the MCP tools (their names and arguments) and the event log. Adding is minor; changing or removing a message, or enforcing a rule on what is accepted today, breaks it.
- **The engine moves pieces, the players apply the cards.** Effects are accepted in any place, from any player, on any card (many cards make the opponent act during the player's action). Values that cards modify (skills, strengths, glory, dueling statistic, costs) are declared by the players, prefilled by the clients. Not checked: triggering conditions, timings of abilities, limits, restrictions, the ring effect matching the ring.
- **Randomness and visibility** are where the engine does more: every draw goes through the generator of the game, and what a player sees depends only on the state (secrets stored apart).
- **A new id only when the players lose track of a card** (a shuffle, the redistribution of the provinces); a province turned face down keeps its id. No `TurnDynastyCardFacedown`: a new id in place would hide nothing.
- **The event log is a contract**: replays rebuild games from it. A message renamed or changed stays deserializable (keep an alias) and plays the same; otherwise the games played before diverge. Games older than the salted ids (November 2026) cannot be replayed.
- **Interrupts and reactions** are accepted anywhere and ended with `EndResolution`; their windows are not modeled. The `react_to_*` places stay generous (decided before 1.0): locking them down would break the replays.
- **An effect that the framework also does is another message** (`TakeImperialFavor` vs `ClaimImperialFavor`, `PlaceFateOnRing` vs `PlaceFate`, `GrantConflictOpportunity` vs `DeclareNextConflict`): so that the scripts of the cards can name it.
- **Special actions**: the messages few cards need are `special`, offered behind a "+", those the active cards need first, the rest on demand (the safety net). The script of a card says which, never the fixtures; **a script informs, it never decides**. Approximations accepted where no message fits (Calling in Favors, Karada District, Shinjo Saddle as `move_attachment`; Frontline Engineer, Kaiu Shihobu as `put_holding_into_province`).
- **Status tokens are not honoring**: placing, discarding or moving one does not trigger what reacts to honor/dishonor. A second token of the same kind is refused, not lost. Honor tokens are counters, like fate.
- **Cards set aside**: a card removed face down goes to the pile of the sender, known to them only (Stolen Secrets must not show the stolen card to its victim); a card face down under a card is known to the owner of that card (Under Siege).
- **Given actions** (`GiveActionOpportunity`) add up for the same player, replace those given to the other, are lost when passing; the next opportunities go to the target rather than activating them at once, so that the opponent is never active in between.
- **Characters as attachments**: the place is computed from the position in the stack (the first card is the character), no flag in the state.
- **Duels**: the honor is transferred once both statistics are declared (bids changed by cards count); the statistic is prefilled without the bid, which the engine adds.
- **What is resolved**: events and abilities are recorded, not characters and attachments played (no reminder needed).
- **Mulligan** in player order (the glossary); none in the games of the house bot (the introductory game goes straight to the board).
- **Accounts**: no email nor personal data; an account is recovered through its linked providers (to be pressed on players). Sessions of accounts slide over 30 days rather than short sessions and a refresh token (stolen together, for nothing). Only bots get API keys; a human helped by an AI is a matter of the terms. The `playerId` is never a credential.
- **Independent messages** (`AppAction::$independent`) ignore the expected version: what a player does on their own where both act at once (revealing their dynasty cards, the stronghold province, honor bids, declared totals). Decided by message, not by place; passing and the effects on the board stay checked (they may answer what the opponent did).
- **Mercure announces, the clients read**: no state on the hub (secrets per player, history of the hub, cost per message).
- **Nobody plays two games at once** (replays apart); waiting in the queue, an account may watch.
- **Players in the queue** are shown (the home, the screen of the queue) even when they are at most one, matched at once: the activity of the site shows in the games in progress; the count leaves out the viewer and the candidates of an invitation, and shows "nobody" rather than nothing, so that the tile keeps its shape.
- **Spectators** are accounts only, outside the game (no event, no log, no version), and see only what is public.
- **Replay** rebuilds the game from its events on a hidden copy, for one of its players once it has ended, showing what they saw then; going back is starting again.
- **Abandonment**: the time is checked before sending the message, the handler checks the state only (so that it replays). The house bot never claims (a human pausing would lose). A game everybody left ends after an hour without a winner rather than with a loser.
- **Worker**: the tasks are on `task.bus`, without the middlewares of the actions (their transaction would hold the lock of a game); one worker (the scheduler needs a single consumer). `default.bus` keeps its name (decided before 1.0).
- **Web client**: the trash button of a card stays (decided before 1.0). Mockups first; the references: board `GiPbGSPNcvLD2r9eFKKNp5`, accounts/queue/friendly games/spectators `LBUBwNBPdSopj5NMj5GLTL`, waiting in the queue `KJWPhVGdXGkiftvaAB5XFH`, cards set aside `LdtL4NT1SnDLTzRZpz376x`, history and replay `KZMDhHY4oeLxaTvx2hE8bE`, mulligan `K178dcmNYPeiY9es4xob8F`, duel `E8u3G7M7AU4ZeEqkbGXdwf`, players in the queue `XQrxVW5j55szWxXpWXWPPt`, nobori `Xf7m3w2nLcrBmYkrasWceM`. The windows over the page are native modal `<dialog>`s rather than `div`s with a role (the focus kept in the window and given back, no `z-index` to order); the popovers of the bars stay `div`s until the oldest iPadOS to support is decided (`popover`: 18.3). The colors are written once (`light-dark()`); a Safari before 17.5 shows the day only, nothing dark: decided, rather than keeping every color three times for it.
- **Free play, nothing secret**: every game can be watched and is announced on Discord (the games of the house bot too: the activity of the site shows there), with no consent asked; keeping one's games private may come later, as a paid option.
- **Nobori**: the catalogue of the mons and the patterns is in enums (`Mon`, `BannerPattern`), each value with its drawing in the web client (a test checks the files); what a player has earned is in tables (`UnlockedMon`, `UnlockedPattern`), the ensō and `none` given to all and never stored. The nobori looks the same by day and by night (paper, a strong pattern, the mon on a disc of paper in its standard color), rather than a pair of colors per mon. The house bot shows the mon of the clan it plays on a plain nobori, computed in the view.
- **The house bot** is Kyūden no Karō, "the steward of the palace": a title rather than a name, being a bot.
- **Card database**: every pack of FFG, the Stronghold cards only: Clan War (`28-`) without its treaties (Team Conquest), Under Fu Leng's Shadow (`42-`, its number at FFG, L5C42) without the Shadowlands faction (cooperative play, no clan to build a deck). The cards of these two came from EmeraldDB (`api/cards`), its text as is (`\n` as `<br>`); a cost `X` is null; Support is kept, a keyword without effect between two players.

## Known limitations

- **Rules not enforced**: uniqueness, Limited, "max X per conflict / round", covert, ancestral attachments; lasting effects do not end (a player takes control back with `TakeControl`).
- **Abilities of a broken province** (bug, seen in a game against an agent): `trigger_ability` of `province_card` only leaves `unbroken` (`config/packages/workflows.php`), and the province is already broken in `react_to_broken_province`: an interrupt or a reaction to its own breaking (Upholding Authority: "When this province is broken") is refused ("Cannot apply transition "trigger_ability" on workflow "province_card""), in the window as after it.
- **Glory count counted twice for the rings** (bug, seen in a game against an agent): the web client prefills the glory count with the claimed rings (`gloryCount()` of `front/src/lib/board.ts`), and the engine adds them again (`GloryCountedTransitionListener`; `DeclareGloryCount` asks for the glory "without the claimed rings"): a player of the web client with no ready character and 2 rings declared 2 and won the Imperial Favor against an agent declaring 0 with 2 rings too (4 against 2, a tie by the rules).
- **Abilities triggered from a hand** (bug, seen in a game against an agent): `trigger_ability` of `conflict_card` does not leave the hand: the reaction of Vengeful Oathkeeper ("put this character into play from your hand") is refused (`not_enabled_transition`); the agent applied the effect with `put_character_into_play`, the instructions of the server telling it to concede on a rejected action.
- **Key of an invitation after the game** (seen in a game against an agent): the key is refused (401 `invalid_token`) as soon as the game has ended, so that the agent cannot read the result (`get_game`, `list_my_games`, the last `wait_for_update`) the instructions ask it to tell. To do: keep the key valid for a while after the end (an hour: the invitation is used, the queue refuses it, the game rejects every action), then delete the keys by batch in the worker, beside `DeleteExpiredSessions` (`App\Schedule`); a batch alone changes nothing while the validity is checked against the game on each request (to check in `AccessTokenHandler`). Check `end_abandoned_game` and the reads of the other games of the bot meanwhile.
- **Stale versions in reaction windows**: passing (`PassOpportunity`) is still rejected (`stale_version`) when the opponent acted meanwhile, on purpose: the player may want to react to what the opponent just did.
- **Spectators**: same layouts as the player at the bottom, a few texts still speak to the player; players cannot refuse them.
- **Web client**: the provinces offered to a character in play are those of its controller; covert is not offered (`evaded_ids` empty); a province does not show its status token (the view has it: `personal_honor` of the province, e.g. dishonored by Return from Shadows).
- **House bot**: no conflict card, no ability, the Imperial Favor always military, never discards from its provinces, never ends a duel; its opponent waits while the worker is stopped.
- **Agents (MCP)**: the view is sent whole on each `get_game`; check that clients keep the whole `instructions` (about 9 kB).
- **Scaling**: each long-poll holds a FrankenPHP thread up to 30 s (20 threads); OAuth state, rate limiter and MCP sessions in filesystem caches (one host; MCP sessions lost on restart).
- **Security**: a Mercure token outlives its session by up to an hour; account sessions have no maximum lifetime; the user creation limit is by IP only (a tournament network may hit it); deleting an account asks no re-authentication (Symfony 8.2 brings one).
- **EmeraldDB**: Emerald Legacy cards unknown; private decks cannot be downloaded.
- **Data**: a card added to the fixtures needs its image uploaded (nothing checks it); the cards of these two packs not reviewed for the effects the players cannot apply (no script yet: special actions, timings), nor tainted cards (Corrupted, taint: not modeled); banned and restricted lists not checked; copies counted by title, not by version.
- `npm audit`: 4 high vulnerabilities in the lint tooling (`braces`), not shipped; wait for an update.

## Next steps

### Cards not playable yet

Remaining effects the players cannot apply (about 15 cards):

- Facedown provinces as characters: Force of the River.
- Control of a holding moved into one's province: Akodo Zentaro.
- Rings: a ring out of the game until the end of the round (Sudden Tempest), an attachment on a ring (Greater Understanding).
- Conflicts: two facedown provinces switched (Know the Terrain; `MoveConflict` comes close), defenders declared first (Seven Stings Keep), the conflict resolved as if both had passed (Moment of Perfect Beauty).
- What the engine applies and a card changes: the honor loss of an unopposed conflict (Ikebana Artisan), the last honor lost (Duty), an ability of a card one does not control (Kujira's Hireling), a secret dial outside a bid (Maze of Illusion).
- Phases skipped or repeated: A Season of War, Jurojin's Curse, Trading on the Sand Road.

Not planned for now (few cards, deep changes): all of the above but Akodo Zentaro and Know the Terrain. Borderline, left as is: "does not bow as a result of conflict resolution" (`ReadyCard` afterwards), An Ocean in a Drop (the player chooses the order), Disguised (play, then move attachments, fate and token, discard the replaced character).

### Card scripts

- Conditions on the special actions where they only make sense somewhere (a duel from a character in play only…).
- New hooks of `AbstractCardScript`: the constant skill bonuses (computed twice today, by `board.ts` and `BoardReader`); the triggering conditions of reactions and interrupts, to point the player at the cards that can react.

### Role cards

- Done: the role is shown beside the stronghold province (`ProvinceRow.vue`), its abilities triggered from the card opened (`trigger_ability` of the workflow `role_card`, the timings from the scripts of the Keeper and Seeker roles).
- Left: the house bot does not use its role (it triggers no ability).

### Web client

- Component tests of `GameView` (player and spectator modes) and `GameReplayView`.
- Discard piles: point at their cards; highlight for the opponent the card pointed at.
- Accounts: list and edit their decks.
- A layout of their own for phones (mockup first): they get the layout of a tablet scaled down (about 0.46 on an iPhone with a notch: the smallest texts near 6pt, the small icons hard to touch), decided as a first step rather than a second web client; touch previews of the cards on the board; notifications of the player's turn (Web Push: VAPID keys, subscriptions stored, sent by the worker).

### API and platform

- **Searching a deck**: a message "search the dynasty deck", a `LookAtCards` of the whole deck under its own name (Emperor's Summons, Favorable Dealbroker…), so that the log tells a search and not a look at N cards.
- **Agents**: a timeout for an agent that stops; a compact view if the context fills.
- **Decks**: the text export of EmeraldDB; the banned and restricted lists; an index on `contentHash` when statistics need it.
- **Authentication**: press the players to link a provider; list the sessions of an account to revoke them; once Symfony 8.2 is out, reset one's password after a recent authentication (sudo mode).
- **npm**: move the stack to npm 12 (`node:24-alpine` ships 11), then check `make front-install`, `make front-build` and the CI.
- **Ranking** of the games that are not friendly.
- **Discord**: edit the announcement of a game once it ends (the winner, a link to its replay): post with `?wait=true`, keep the id of the message, PATCH it on `game_ended`.
- **Earning the nobori**: how a player earns a mon or a pattern (games played, victories with a clan, trophies…), calling `NoboriCollection::unlock()`; until then, `app:nobori:unlock` by hand.
- **Trophies** awarded to the players who reach goals, to lead them to explore what the site offers.
- **Tournaments**: create a tournament and play in one.
- **Rewinding a game** (`app:game:rewind <game> <version>`): replay with `GameReplayer` up to the version, then swap the copy for the game (deleting events changes nothing: the state lives in the entities); it diverges if a deck or the engine changed since.
- **Replay**: lock the copy (two steps at once are not serialized); replaying as a spectator (the public view of the copy).
