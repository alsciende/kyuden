# Trophies

Working document: the list of the trophies and the rules for awarding them. Once implemented, the architecture goes to the README and the decisions to `HANDOFF.md`.

## Purpose

Trophies bring the players to the queue, so that the others find opponents there; along the way, they lead them to explore what the site offers (the house bot, friendly games, bots, watching, replays, decks of EmeraldDB, accounts), and reward playing well and playing every clan. They are a guide, not a proof of skill: nothing is ranked on them.

## Principles

- **Standard games count**: a standard game is a game matched by the queue, not friendly (not of an invitation) and not against the house bot. The trophies of the games (winning, moments of a game) are awarded in standard games only, except those marked otherwise in the list (*any game*), which lead to the other ways of playing. A player playing against themselves with two accounts through the queue is accepted: they fill the queue too. The replays (copies) never count.
- **Only what the engine knows**: the end of the game, its winner and its victory condition, broken provinces, claimed rings, duels won, rounds, clans of the strongholds, and the platform (accounts, queue, invitations, spectators, replays, decks). Never a value the players declare (skills, strengths, glory).
- **Read from what is stored**: the games, their state (`winnerId`, `victoryCondition`) and their log, so that the trophies can be awarded to the games played before.
- **Guests earn trophies too**: registering keeps the same user, so they keep them ("create an account to keep your trophies" is already true).
- **A trophy earned stays earned**, even when its goal grows: a stronghold added to the fixtures (23 today: Dragon 4, Lion 4, the others 3; Hisu Mori Toride is printed for the Lion and for the Unicorn, two cards) is needed by the players who do not have the trophy yet, not taken back from those who have it.
- **A victory is won by playing**: a victory by concession or abandonment counts as a game finished (`first_game`, `queue_game`, `games_10`), never as a victory (`wins_*`, `clan_*`, `strongholds_*`, `all_elements`, `house_bot_win`, `swift_conquest`…).
- **A bot in the queue plays standard games**: a bot of a player joining the queue without an invitation (MCP) fills it like anyone.
- **No secret trophy**: every goal is shown, since the trophies guide.
- **Few and clear**: about twenty at first, a name in the spirit of Rokugan (no card name) and the goal written plainly under it.

## The list

`Source` says what the award reads. A trophy of a game is awarded in standard games only, unless its goal says *any game* or names another kind of game.

### Discovering the site

| Code | Name | Goal | Source |
|---|---|---|---|
| `first_game` | The First Step | Finish a game, *any game* | game ended |
| `house_bot_game` | Guest of the Palace | Finish a game against Kyūden no Karō | game ended, house bot among the players |
| `queue_game` | Into the Wider World | Finish a standard game | game ended, standard |
| `games_10` | A Familiar Face at Court | Finish 10 standard games | count of the standard games ended |
| `friend_game` | Tea with a Friend | Finish a friendly game against a friend (an invitation code) | game of an invitation of kind `friend` |
| `bot_game` | The Puppeteer | Have your own bot play a game against you (an invitation of your bot) | game of an invitation of kind `bot` |
| `spectator` | Eyes of the Court | Watch the game of other players | `SpectatorRegistry` (first watch) |
| `replay` | Lessons of the Past | Replay one of your games to its end | `GameReplayer` (step reaching the end) |
| `imported_deck` | Emerald Lore | Import a deck from EmeraldDB | `DeckImporter` |
| `own_deck_game` | Beyond the Dojo | Finish a standard game with a deck that is not a starter deck | game ended, deck with an owner |
| `account` | A Name at Court | Create an account | `AccountRegistrar` / `POST /api/accounts` |
| `court_name` | Gempuku | Change your name at court | `PATCH /api/self` with a new `name` |
| `linked_account` | Ties of Loyalty | Link a Google or Discord account | linked account created |

### Winning

| Code | Name | Goal | Source |
|---|---|---|---|
| `wins_1` | First Victory | Win a game | `winnerId` |
| `wins_10` | Seasoned Commander | Win 10 games | count of `winnerId` |
| `wins_50` | Champion of the Empire | Win 50 games | count of `winnerId` |
| `honor_win` | Path of Honor | Win with 25 honor | `victoryCondition` `honor_win` |
| `honor_loss` | Shame of the Enemy | Win by leaving your opponent without honor | `victoryCondition` `honor_loss` |
| `conquest` | Fall of the Stronghold | Win by breaking the stronghold province | `victoryCondition` `stronghold_province_broken` |
| `house_bot_win` | Outwitting the Steward | Beat Kyūden no Karō (a game against the house bot) | `winnerId`, house bot the loser |
| `clan_<clan>` | Champion of the <Clan> | Win with a stronghold of the clan (one per Great Clan: 7 trophies) | `winnerId`, clan of the stronghold |
| `all_clans` | Voice of the Seven Clans | Win with each of the seven Great Clans | the seven `clan_<clan>` |
| `strongholds_<clan>` | Lord of the <Clan> Castles | Win with each stronghold of the clan (one per Great Clan: 7 trophies) | `winnerId`, stronghold of the winner |
| `all_strongholds` | Every Castle of Rokugan | Win with each stronghold | the seven `strongholds_<clan>` |
| `all_elements` | Harmony of the Elements | Win with a role of each element: air, earth, fire, void, water (a Keeper or a Seeker) | `winnerId`, role of the winner (its element trait) |

### Moments of a game

| Code | Name | Goal | Source |
|---|---|---|---|
| `five_rings` | The Five Rings | Claim the five rings in a single round | log: `RingClaimedEvent` per round |
| `untouched` | The Unbroken Wall | Win without losing a province | log: provinces of the winner broken |
| `duel_win` | Steel and Will | Win a duel | log: `DuelWonEvent` |
| `swift_conquest` | Swift as the Wind | Win by conquest before round 3 | `victoryCondition`, round at the end |
| `long_game` | The Long Winter | Finish a game of 8 rounds or more | round at the end |

## Open questions

- **Where they show**: the account, the end of a game ("Trophy earned"), the history? (mockup first)
- **Shown to the others**: the opponent and the spectators see the trophies of a player, in a way to define (mockup first).
