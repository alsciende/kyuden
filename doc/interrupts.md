# Interrupts

How the engine is to offer the interrupts of the cards, and wait for them: the design decided with the author (2026-11-22), and the interrupts of the cards of the fixtures, grouped by triggering condition. The reactions are in `reactions.md`; what the engine does today is in `game-flow.md` ("Interrupts and reactions", "Reaction windows"). Done: the common ground, with the first triggering condition (1, a card would leave play); the other groups come next, in the proposed order.

## The rules

- An interrupt "interrupts the resolution of its triggering condition, sometimes canceling or changing the resolution of that condition" (glossary, Interrupts): it resolves before what it interrupts, which has not happened yet.
- Around each triggering condition (glossary, Triggering Condition): (1) the condition becomes imminent; (2) the interrupts to what "would" occur may be used, a cancel preventing the rest, a change making another condition imminent; (3) the forced interrupts resolve, in the order the first player chooses, then the interrupt window opens: the first player has the first opportunity, the players alternate until they all pass consecutively, and a player who has passed may still use an interrupt later in the window; (4) the condition occurs; (5) the forced reactions, then (6) the reaction window.
- One occurrence creating several triggering conditions (an effect discarding a character and its attachments) shares its interrupt and reaction windows.
- Playing a card or initiating an ability (glossary, Initiating Abilities/Playing Cards): the costs are paid (4), the targets chosen (5), then the effects attempt to initiate and a cancel may be used (6); the card is then "played", the ability "triggered" (8). A cancelled effect is no longer imminent and cannot be interrupted any more; its costs are paid, and a cancelled event is still played and goes to the discard pile (glossary, Cancel).
- Nested sequences (glossary): an interrupt leading to a new triggering condition opens a new sequence, which completes before the game returns to the first one (LIFO): Censure cancelling the Voice of Honor that cancels Banzai!, Banzai! resolves.
- Each interrupt once per round unless stated, forced ones included (the limits are not checked, as for the reactions).

## Why the reactions model is not enough

The engine is a board: a message applies its effect when it is handled, and the reaction windows (`PlayerState::reactionWindows`) offer, they block nothing. That suits the reactions, which come after their triggering condition, and the interrupts to what has already occurred in the engine's terms. It does not suit the interrupts that cancel or replace ("would… instead": Reprieve, Stand Your Ground, Iron Mine…): once offered, the effect is already applied.

Rejected alternatives:

- **Repairing after the fact** (restoring a character that has left play from a snapshot): its fate, status, tokens, skills, controller, participation, attachments and their state, the cards under it, the honor lost and the fate returned. Too much to save and restore, too easy to get wrong; no other online engine does it. `RestoreProvince` (Public Forum) stays until its triggering condition is taken in charge.
- **A toggle on the card in hand**, arming it to stop the game: the card is unplayable without it, and the stops tell the opponent what the hand holds.
- **Stopping only for the players who hold a card able to interrupt**: the stop tells the opponent that the player holds it.

The other engines suspend the game and hide the hand by bluffing: ringteki (jigoku.online, `server/game/gamesteps/TriggeredAbilityWindow.ts`) only asks the players with a valid choice, but offers a "bluff prompt" on a setting of the player (`eventsInDeck`: asked when a copy able to answer is out of the hand, in the deck; `events`: always asked when the effects of an opponent's event would initiate), with a timer passing on its own (`windowTimer`), "I need more time" and "Don't ask again until end of round"; MTG Arena stops where the player can play, unless they hold "Full Control".

## Design

### Initiation and resolution

The engine already splits each message in two: it is initiated when the middlewares accept it, resolved when its handler applies it. The interrupt window opens between the two; the handlers do not change. A message whose effect would create an imminent triggering condition, some player being asked, is not handled: it waits in the state while the window is open, and is handled once the window closes, unless cancelled.

The triggering conditions come from two sources:

- **The messages of the players** (effects, plays): `InterruptWindowMiddleware`, before the handler (after `GameLockMiddleware`, `GameEndedMiddleware`, `GameReplayMiddleware`), asks a resolver per kind of triggering condition what the message would create (`DiscardCard` on a character in play: that character, and each of its attachments, would leave play). It first checks that the message would be accepted (`assertAvailable()` of its handler, the transition `can()`), so that no window opens for a discard the engine would refuse. When nobody is asked, the message goes on at once.
- **The framework steps** (the automatic transitions): a transition declares the triggering condition it creates (metadata `interrupt: <trigger>`: `provinces_broken`, `result_determined`, `conflict_phase_ended`, `fate_phase_ended`, `draw_phase_began`, `imperial_favor_contest_began`, the discard of the characters without fate…). `WorkflowTransitionMiddleware` opens the window instead of applying it; a guard holds it while the window is open, and it applies once the window has closed, as `AllPlayersPassedGuardListener` holds the steps today.

The message suspended is re-dispatched by the middleware once the window closes, with a stamp skipping the check (`InterruptsResolvedStamp`); it is not recorded again (see "Replays").

The plays and the abilities are suspended like any other message: `PlayActionEvent`, `PlayInterruptEvent`, `PlayReactionEvent`, `PlayCharacter`, `PlayCharacterFromProvince`, `PlayAttachment`, `PlayCharacterAsAttachment`, `TriggerActionAbility`, `TriggerInterruptAbility`, `TriggerReactionAbility` create "the effects of this card would initiate" (step 6), so that `CardPlayedEvent` and `AbilityTriggeredEvent` (step 8) and their reactions come after the cancels. The costs are paid by the handler, after the window rather than before it: the final state is the same, and none of the cards reads the fate pools during the window. Meanwhile the event is still in the hand: the window reveals it (`#[Revealed]`), as a card announced at the table.

### What cancelling means

Each kind of triggering condition says what the window closing does, cancelled or not:

| Triggering condition | Not cancelled | Cancelled or replaced |
| --- | --- | --- |
| a card would leave play, a province would be broken, a ring would be returned… | the message is handled, the transition applied | the message is not handled, the transition not applied; the replacement ("instead") is applied by the player with the effect messages |
| the effects of a card or an ability would initiate | the message is handled; the player applies the effects, then ends the resolution | the message is still handled (costs paid, the event played and discarded), its resolution marked cancelled (`Resolution::$cancelled`): the player can only end it (`EndResolution`) |

A replacement making another condition imminent (Reprieve: the attachment discarded instead) is an effect message like any other, which goes through the middleware and may open its own window.

### The state

A stack of windows, `GameState::interruptWindows` (`State/Model/InterruptWindow`), the last one opened the only one active (nested sequences, LIFO). Each window holds:

- its id (from a counter of `GameState`, never from the generator, as `reactionWindowCount`), and its triggering conditions (one occurrence, several conditions: one window), each with its name and params (ids, public, as those of the reaction windows);
- what waits: a message (name and payload, as an `ActionRecord`), an automatic transition (its name), or nothing (a cancel window after a framework step that has nothing to hold);
- its stage: `would` (the interrupts to what would occur, the cancels), `forced` (the forced interrupts, which every player sees, resolved before the window opens: their order the first player's choice, not checked), then `standard`; a stage nobody is asked in is skipped;
- the players asked in the current stage, those who have passed since the last interrupt initiated (an interrupt cancels the passes), and the number of interrupts and reactions each player resolved when it opened (`resolutionBase`: the window waits for those initiated in it to end). The players asked answer at once, as in the reaction windows: no turn among them, the first player's priority left to the players (rare: both interrupting the same condition);
- `cancelled`: the condition is no longer imminent.

### Who is asked

The decision to ask a player depends on public information only, so that a stop tells the opponent nothing they cannot see:

- **A card the opponent sees** (in play, a holding or a province face up, an attachment: Reprieve, Iron Mine, Pledge of Loyalty, Akodo Kaede, Master of Many Lifetimes): its player is asked when its script says it can interrupt the condition.
- **An event out of sight** (the hand): its player is asked when the condition its script reads is met (on public information: an honored character they control would leave play) and a copy of the card is in their hand or their conflict deck, whichever. The opponent cannot tell the hand from the deck; they learn that the deck may hold the card, which its play would show anyway. A copy in the discard pile is public. A player asked without the card in hand gets the same window, offering nothing but passing.

The scripts subscribe to the imminent conditions statically, as to the triggering events of the reactions (`getSubscribedEvents()`): an imminent condition is not a domain event (nothing has changed yet, and the domain events are named in the past tense), but a model of `src/Trigger` (`ImminentTriggerInterface`: `getTriggerName()`, `getTriggerParams()`), the script returning offers (`InterruptOffer`: the card, forced or not). Compiled by `App\Kernel` with the subscriptions of the reactions.

### The messages

- `PassInterrupt` (public, not independent: the players alternate): the player passes in the window on top. A window closes once every player asked has passed consecutively and nobody resolves an interrupt.
- `CancelImminentTrigger` (special, named by the scripts of the cards that cancel or replace): the condition of the window on top is no longer imminent; the window closes at once, without waiting for the passes (a cancelled effect cannot be interrupted any more).
- While a window is open, the engine accepts (`InterruptWindowService::assertAccepted()`, in the middleware and `AvailableActionsResolver`): the interrupts (`PlayInterruptEvent`, `TriggerInterruptAbility`), the reactions (to what the interrupts do), `PassInterrupt`, `CancelImminentTrigger`, `ConcedeGame`, the abandonment, and any message but `PassOpportunity` from a player resolving an interrupt initiated in the window (`EndResolution` included). It refuses the rest (`InterruptWindowOpenException`): the phase actions, passing an opportunity, the effects outside an interrupt, so that what waits cannot become invalid meanwhile (the character moved elsewhere).
- An interrupt played is itself a message: it creates "its effects would initiate", and its window is stacked on top (Censure against Voice of Honor). An effect of an interrupt creating a condition stacks its window the same way.

Once a window closes, it is popped, what waits is resolved (unless cancelled), and the reaction windows of the condition open as today (`ReactionOfferer`, from the domain events the handler dispatches); the window below, if any, becomes active again.

### Replays, log, version

- The message suspended is recorded when it arrives (its `ActionRecord`, the version incremented); its re-dispatch is internal, like the automatic transitions, and the engine redoes it from the state: replaying the records gives the same game. Whether a window opens depends on the scripts, so that the records of a game played before the windows would no longer replay the same: no flag on the game (decided by the author, 2026-11-22), the games recorded are deleted once, when the windows are deployed (a migration, the stack of production stopped, no game in progress: the physical cards, the stacks and the players first, their foreign keys to the game having no `ON DELETE`, then the games), with their action records, their log, their spectators and their replays (`CASCADE`); the trophies earned stay, without their game, and the matches of the tournaments ended keep their result, without their game (`SET NULL`). From then on, a change of a script that opens or closes windows breaks the replay of the games played before it, as a change of a handler does.
- The log writes the imminent condition when the window opens ("{card} would leave play", "{player} is about to play {card}"), then the resolution (the transition, the event) at the version of the message that closed the window.
- The view gives every player the window on top (its first condition, its stage, the players it waits for, `timed` for the viewer) and to each player what they can interrupt with, under one name with the reaction windows (`ability_windows`, each with its timing: interrupt or reaction), which replaces `reaction_windows` in the API and the MCP tools (the web client, the house bot and the instructions of the agents follow).
- `AwaitedPlayersResolver` waits for the players the window on top waits for (abandonment). The house bot passes, and triggers and ends its forced interrupts, without their effect. The agents (MCP) are told to answer the window first.

### The web client

The window is drawn as the offers of a reaction window in the sidebar (`GameSidebar.vue`, `.offers`), with the red band on top of the modal windows (6px of `--seal`, as `MulliganWindow.vue`) and a tag `INTERRUPT` (as `FORCED`): no mockup (decided by the author). It names the conditions, the cards the player can interrupt with, the opponent's turn when it is theirs. A player asked about an event out of sight has a timer passing for them (a few seconds, `PassInterrupt` sent by their client) and "I need more time", the same whether the card is in their hand or not, so that the time they take tells nothing. A player asked about a card in sight has no timer.

## The interrupts of the cards

88 cards of `fixtures/cards` carry an interrupt (`Interrupt:` in their text), 4 of them forced. Grouped by triggering condition, whether it reads "when this province is broken" or "when this province would be broken": both interrupt the breaking, the second being able to prevent it.

| # | Triggering condition | Cards | Created by |
| --- | --- | ---: | --- |
| 1 | A card would leave play (sacrificed included) | 24 | `DiscardCard`, `SacrificeCard`, `ReturnCardToHand`, `PutCardOnDeck`, `ShuffleIntoDeck`, `RemoveFromGame`, `PlaceCardUnder`, `PutCardIntoProvince` on a card in play; the discard of the characters without fate (fate phase) |
| 2 | The effects of a card or an ability would initiate, a card played | 20 | the plays and the abilities triggered |
| 3 | A province would be broken | 20 | `BreakProvince`, `provinces_broken` |
| 4 | A phase would begin or end | 7 | `draw_phase_began`, `conflict_phase_ended`, `fate_phase_ended` |
| 5 | The conflict: declared, its result, unopposed, the glory count | 5 | `DeclareConflict`, `result_determined`, `unopposed_applied`, `imperial_favor_contest_began` |
| 6 | A ring effect would resolve | 5 | entering `resolve_ring_effects` |
| 7 | Honored, dishonored, fate removed | 3 | `HonorCharacter`, `DishonorCharacter`, `RemoveFate` and the fate phase |
| 8 | The first player token, a ring returned, drawing, a duel resolved | 4 | the end of the round, `ReturnRing`, `DrawCards`, the result of a duel |

Proposed order: the common ground (the stack, the stages, `PassInterrupt`, `CancelImminentTrigger`, the middleware, the guard of the automatic transitions, the window of the web client) with 1, the reason of the design; then 2 (the cancels and their nested windows), 3, then the small groups.

### 1. A card would leave play (24)

- Reprieve (`01-reprieve`, attachment): When attached character would leave play – discard this attachment instead
- Stand Your Ground (`01-stand-your-ground`, event): When an honored character you control would leave play – instead discard that character's status token
- Back-Alley Hideaway (`04-back-alley-hideaway`, holding): When a Scorpion character you control leaves play
- Fawning Diplomat (`05-fawning-diplomat`, character): When this character leaves play
- Iron Mine (`07-iron-mine`, holding): When a character you control would leave play – sacrifice this holding instead
- Fushichō (`08-fushicho`, character): When this character leaves play
- Tainted Koku (`10-tainted-koku`, attachment): When attached character leaves play
- Callow Delegate (`13-callow-delegate`, character): When this character leaves play
- Seppun Truthseeker (`15-seppun-truthseeker`, character, forced): When this character leaves play
- Yasuki Fuzake (`18-yasuki-fuzake`, character): When this character leaves play
- Fifth Tower Watch (`19-fifth-tower-watch`, character): When you sacrifice a character
- Akodo Kaede (`20-akodo-kaede`, character): When another character would leave play – remove 1 fate from this character instead
- Sharpened Tsuruhashi (`22-sharpened-tsuruhashi`, attachment): When attached character is sacrificed
- Gallant Quartermaster (`24-gallant-quartermaster`, character): When this character is sacrificed
- Chancellor's Aide (`28-chancellor-s-aide`, character): When this character leaves play
- Pledge of Loyalty (`29-pledge-of-loyalty`, province): When an honored character you control would leave play – discard its honored status token instead
- Disciple of Shinsei (`30-disciple-of-shinsei`, character): When this character leaves play
- Traveling Philosopher (`30-traveling-philosopher`, character): When this character leaves play
- Beautiful Entertainer (`33-beautiful-entertainer`, character): When this character leaves play
- Ceaseless Duty (`33-ceaseless-duty`, event): When a character with printed cost equal to or lower than the number of unbroken provinces you control would leave play
- Master of Many Lifetimes (`33-master-of-many-lifetimes`, character): When a character you control would leave play
- Agasha Sumiko (`34-agasha-sumiko`, character): When this character leaves play
- Daughter of War (`36-daughter-of-war`, attachment): When attached character leaves play
- Promising Youth (`39-promising-youth`, character): When attached character leaves play

### 2. The effects of a card or an ability would initiate, a card played (20)

- Forged Edict (`01-forged-edict`, event): When the effects of an event would initiate
- Shiba Yōjimbō (`01-shiba-yojimbo`, character): When the effects of a triggered ability that targets a Shugenja you control would initiate
- Voice of Honor (`01-voice-of-honor`, event): When the effects of an event would initiate
- Finger of Jade (`02-finger-of-jade`, attachment): When the effects of a card ability that targets attached character would initiate
- Pathfinder's Blade (`03-pathfinder-s-blade`, attachment): When the effects of a triggered ability on the attacked province would initiate
- Censure (`04-censure`, event): When the effects of an event would initiate
- Yogo Kikuyo (`08-yogo-kikuyo`, character): When the effects of a Spell event your opponent plays during a conflict would initiate
- Mantis Tenkinja (`14-mantis-tenkinja`, character): When you play an event
- Duty (`15-duty`, event): When the effect of a card ability…
- Defend Your Honor (`16-defend-your-honor`, event): When the effects of an opponent's event would initiate during a conflict
- Hantei XXXVIII (`16-hantei-xxxviii`, character): When an opponent triggers an ability that chooses 1 or more targets
- Seppun Hidden Guard (`16-seppun-hidden-guard`, character): When the effects of a character ability that targets a unique character you control would initiate
- Effective Deception (`21-effective-deception`, province): When the effects of a triggered ability would initiate during a conflict at this province
- Appeal to Sympathy (`28-appeal-to-sympathy`, event): When the effects of an event would initiate
- Highlight the Flaws (`30-highlight-the-flaws`, event): When the effects of a province's triggered ability would initiate
- Cursecatcher (`31-cursecatcher`, character): When the effects of a province's triggered ability would initiate
- Forgery (`32-forgery`, event): When the effects of an event would initiate
- Master of the Court (`33-master-of-the-court`, character): When the effects of an event would initiate
- Iron Mountain Castle (`34-iron-mountain-castle`, stronghold): When you play an attachment on a character you control
- Mushin no Shin (`39-mushin-no-shin`, event): When the effects of a triggered ability would initiate

The targets are not known to the engine (as for the reactions): the cards naming them are offered for every effect of their kind, their player judging.

### 3. A province would be broken (20)

- Restoration of Balance (`01-restoration-of-balance`), The Art of Peace (`01-the-art-of-peace`), The Art of War (`01-the-art-of-war`), Feast or Famine (`04-feast-or-famine`), Before the Throne (`05-before-the-throne`), Demonstrating Excellence (`09-demonstrating-excellence`), Upholding Authority (`10-upholding-authority`), Abandoning Honor (`11-abandoning-honor`), Seeking the Truth (`12-seeking-the-truth`), Appealing to the Fortunes (`13-appealing-to-the-fortunes`), Karmic Teachings (`28-karmic-teachings`), Slaughtered Village (`28-slaughtered-village`), Cycle of Vengeance (`37-cycle-of-vengeance`), The Way of Peace (`37-the-way-of-peace`) (provinces): When this province is broken
- Fury of the Kami (`28-fury-of-the-kami`), Model of Courtesy (`28-model-of-courtesy`) (provinces): When a province you control is broken
- Miwaku Kabe (`03-miwaku-kabe`, holding): When this holding's province is broken
- Watchtower of Sun's Shadow (`36-watchtower-of-sun-s-shadow`, holding, forced): When this holding's province is broken
- Public Forum (`04-public-forum`, province): When this province would be broken
- Strategic Weakpoint (`23-strategic-weakpoint`, holding): When the holding's province would be broken

The window opens before the province is broken, so that a province triggers its own interrupt unbroken (the bug of `trigger_ability` on a broken province, `HANDOFF.md`, is not met).

### 4. A phase would begin or end (7)

- Shiba Tsukune (`01-shiba-tsukune`, character), Ethereal Alignment (`30-ethereal-alignment`, event), Hidden Mountain Pass (`32-hidden-mountain-pass`, holding), Pious Guardian (`33-pious-guardian`, character), Warden of the Damned (`21-warden-of-the-damned`, character, forced): When the conflict phase ends
- Jurōjin's Curse (`11-jurojin-s-curse`, attachment, forced): When the fate phase ends
- Trading on the Sand Road (`17-trading-on-the-sand-road`, event): When the draw phase would begin – skip the draw phase

### 5. The conflict (5)

- Ikebana Artisan (`12-ikebana-artisan`, character): When you would lose 1 honor for an unopposed conflict
- Kakita Toshimoko (`16-kakita-toshimoko`, character): When this character would lose a conflict
- Ikoma Kiyono (`22-ikoma-kiyono`, character): When a glory count would begin
- Know the Terrain (`24-know-the-terrain`, event): When an attack is declared against a non-stronghold facedown province you control
- Seven Stings Keep (`30-seven-stings-keep`, stronghold): When you declare a conflict

### 6. A ring effect would resolve (5)

- Asako Azunami (`12-asako-azunami`, character): When you would resolve the water ring
- All and Nothing (`13-all-and-nothing`, event): When you would resolve the void ring effect
- Restored Heirloom (`14-restored-heirloom`, attachment): When you would resolve the water ring effect
- Leniency (`16-leniency`, event): When you would resolve a ring effect
- Jade Talisman (`42-jade-talisman`, attachment): When a ring effect would initiate

### 7. Honored, dishonored, fate removed (3)

- Young Rumormonger (`01-young-rumormonger`, character): When a character would be honored or dishonored
- Embrace the Void (`02-embrace-the-void`, attachment): When 1 or more fate would be removed from attached character
- Kitsuki Shomon (`15-kitsuki-shomon`, character): When another character you control would be dishonored

### 8. Others (4)

- Way of the Unicorn (`01-way-of-the-unicorn`, event): When you would pass the first player token
- Wholeness of the World (`08-wholeness-of-the-world`, event): When a ring in your claimed ring pool would be returned to the unclaimed ring pool
- Stay Your Hand (`16-stay-your-hand`, event): When a duel that targets a character you control would resolve (`CancelDuel` covers it meanwhile)
- Subterfuge (`41-subterfuge`, event): When an opponent would draw 1 or more cards outside the draw phase

## Decided (2026-11-22)

- **The order of the windows**: the newest first, as nested sequences complete last in, first out; a card played or triggered leaves the newest window offering it (today: the oldest), the reaction windows included.
- **The naming of the view**: one name for both, `ability_windows`, each window with its timing; `reaction_windows` goes, the agents being told.
- **The cards cancelling an opponent's event** (Voice of Honor, Censure, Forgery…): their player is asked for every event the opponent plays while a copy is in their hand or their conflict deck, with the timer, as ringteki's `events`: some friction, nothing told.
- **The settings of the player** (not being asked about an event out of sight, the length of the timer): later, if the players ask for them; the first version bluffs always, with a timer of fixed length.
