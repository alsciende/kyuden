# Reactions

The reactions of the cards of the fixtures, grouped by triggering condition, to decide in which order the engine offers them (`card-scripts.md`, "Reaction offers") and what each group needs. Only the triggering condition counts here: what the reaction does is applied by the players with the effect messages, as for every card.

334 cards of `fixtures/cards` carry a reaction (`Reaction:` in their text, one each, those granted to the attached character included: "Attached character gains…", or the attachments whose text says "this character"). 16 are forced reactions, not offered (see "Forced reactions"), 4 are out of scope (see the end). The groups count every card; what is done says how many cards subscribe.

Conventions of the lists: a card is listed under the event that triggers it, with its triggering condition as printed (the elements, types and clans in plain words), not its effect. The limits ("Max 1 per round") are not checked, as for the province revealed.

## Summary and proposed order

The order weighs the number of cards against what the engine lacks: first the groups whose domain event and reaction window already exist, then those needing a triggering event dispatched after a transition (as `ProvinceRevealedEvent` after `reveal`), then the small groups.

| # | Triggering condition | Cards | What exists in the engine | What is missing |
| --- | --- | ---: | --- | --- |
| – | A province revealed | 34 | done (34) | – |
| 1 | A conflict won or lost | 93 | done (88) | – |
| 2 | A conflict declared (attackers, covert) | 33 | done (27) | – |
| 3 | A ring claimed | 8 | done (7) | – |
| 4 | A pass on declaring a conflict | 10 | done (9) | – |
| 5 | A province broken | 9 | done (9) | – |
| 6 | The honor dials of a bid revealed | 7 | done (9: the 2 of a duel of 12 too) | – |
| 7 | Defenders declared, a character declared as attacker or defender | 4 | done (2) | – |
| 8 | A phase begins | 24 | done (21) | – |
| 9 | A character or an attachment enters play | 22 | done (22) | – |
| 10 | A player plays this card, or a character | 14 | done (14) | – |
| 11 | A card played (an event, a trait, during a conflict) | 21 | done (21) | – |
| 12 | Duels | 7 | done (5, and 2 with 6: Iaijutsu Master, Niten Pupil) | – |
| 13 | A character moves to the conflict | 7 | `CharacterMovedToConflictEvent`, `CharacterPlayedIntoConflictEvent`, `CharacterPutIntoConflictEvent` | the events triggering ("commits": 2 and 7) |
| 14 | Fate placed on or removed from a character | 6 | `FatePlacedOnCardEvent`, `FateRemovedFromCardEvent` | the events triggering; the total removed in the fate phase |
| 15 | The honor of a player | 5 | `HonorGainedEvent`, `HonorLostEvent`, `HonorTransferredEvent` | the events triggering; the cause (see below) |
| 16 | A status token, honored, dishonored | 4 | `PersonalHonorChangedEvent`, `StatusTokenPlacedEvent` | the events triggering; the cause |
| 17 | The hand: discarded from it, looked at | 4 | the discard transitions, `CardsLookedAtEvent`, `CardsRevealedEvent` | an event after a discard from a hand; the cause |
| 18 | The dynasty phase: passing, collecting fate | 4 | `OpportunityPassedEvent`, `FateGainedEvent`, `FateCollectedEvent` | the events triggering |
| 19 | Ring effects, fate taken from a ring | 5 | `RingFateTakenEvent`, place `resolve_ring_effects` | a ring effect resolved is not known to the engine |
| 20 | A character bowed or readied | 3 | transitions `bow`, `ready` | an event after the transition; the cause |
| 21 | A card turned face up in a province | 2 | transition `turn_faceup` of a dynasty card | an event after the transition |
| 22 | A character leaves play | 2 | `LeavePlayListener` | an event once the card has left play |
| 23 | An ability triggered | 2 | the handlers `Trigger*Ability` | an event |
| 24 | The end of the resolution of a conflict | 2 | place `return_home` | the provinces broken during the conflict |
| – | Out of scope | 2 | – | Mindful Warrior and Shadowed Alleyway too, listed under 1 and 2 |

Steps 1 to 7 cover 164 cards with what exists, 8 to 11 81 cards with one triggering event dispatched after the transitions (entering play and playing a card share it), 12 to 24 53 cards.

## Questions to decide

- **The cause of an effect** (Ready for Battle, Ardent Omoidasu, Righteous Samurai, Shiotome Heroine, Akodo Motivator, Shosuro Hyobu, Adorned Temple, Isawa Tsuke, Curry Favor): "an opponent's card effect", "a ring effect", "by a card effect", "as a result of conflict resolution". The engine does not know which ability a message applies. It knows the sender, what they resolve (`PlayerState::resolutions`) and the step (`resolve_ring_effects`, the bows of the end of a conflict): enough to approximate, or the card is offered on every change and its player judges.
- **The targets of an event** (decided): the engine does not know what an event chooses; Withstand the Darkness and Dragon Tattoo are offered for every event played (by the opponent while the player controls a Crab character, by the player), the player judges.
- **The effects of an event resolved** (decided): The Mirror's Gaze is offered once the opponent plays an event; the window blocks nothing, so that its player triggers it once the effects have resolved, which the engine does not know.
- **The declaration and the reveal** (decided): the reactions to the declaration come once the attacked province is revealed (`ConflictDeclarationCompletedEvent`), as the rules say and so that a province reacting to its attack can trigger, face up; The Crashing Wave (before the province is revealed) and Enlightened Warrior (the fate on the contested ring, which the attacker takes at once) react to the declaration itself (`ConflictDeclaredEvent`, which carries the fate of the ring).
- **"Commits to a conflict"** (Spyglass, Daikyū): declared as attacker or defender; with the moves to the conflict (13), it needs the events of 2 and 7.

## 1. A conflict won or lost (93)

`ConflictWonEvent` (the winner, the skills), in `react_to_conflict_result`; a conflict nobody wins (`ConflictUnwonEvent`) triggers none of them. The conditions read the conflict still in `GameState`: the winner and the loser, the side of a character (participating, attacking, defending: `TriggerConditions`), the type, the element of the contested ring, the attacked province, unopposed (no defender), the difference of skill ("by 5 or more"), the number of participants of each player, plus the honor of the players, the size of their hands, the traits and the status of the characters. More than half react to "this character" or "attached character" winning.

- Asako Diplomat (`01-asako-diplomat`, character): After this character wins a conflict
- Blackmail Artist (`01-blackmail-artist`, character): After this character wins a political conflict
- Deathseeker (`01-deathseeker`, character): After this character loses a conflict as an attacker
- Defend the Wall (`01-defend-the-wall`, province): After you win a conflict at this province
- Display of Power (`01-display-of-power`, event): After you lose an unopposed conflict
- Fallen in Battle (`01-fallen-in-battle`, event): After you win a military conflict by 5 or more skill
- Hida Tomonatsu (`01-hida-tomonatsu`, character): After this character wins a conflict as a defender
- Honored Blade (`01-honored-blade`, attachment): After attached character wins a conflict
- Ikoma Eiji (`01-ikoma-eiji`, character): After you lose a political conflict
- Indomitable Will (`01-indomitable-will`, event): After you win a conflict in which you control only a single participating character
- Keeper of Air (`01-keeper-of-air`, role): After you win an air conflict as the defending player
- Keeper of Earth (`01-keeper-of-earth`, role): After you win an earth conflict as the defending player
- Keeper of Fire (`01-keeper-of-fire`, role): After you win a fire conflict as the defending player
- Keeper of Void (`01-keeper-of-void`, role): After you win a void conflict as the defending player
- Keeper of Water (`01-keeper-of-water`, role): After you win a water conflict as the defending player
- Spies at Court (`01-spies-at-court`, event): After you win a political conflict
- Vengeful Oathkeeper (`01-vengeful-oathkeeper`, character): After you lose a military conflict
- Gifted Tactician (`02-gifted-tactician`, character): After this character wins a military conflict
- Staunch Hida (`02-staunch-hida`, character): After this character wins a conflict as a defender
- Time for War (`02-time-for-war`, event): After you lose a political conflict
- Kuni Ritsuko (`07-kuni-ritsuko`, character): After this character wins a conflict as a defender
- The Path of Man (`08-the-path-of-man`, event): After you win a conflict by 5 or more skill
- Hisu Mori Toride (`09-hisu-mori-toride`, stronghold): After you win a military conflict by 5 or more skill
- Icon of Favor (`11-icon-of-favor`, attachment): After you win a fire conflict
- Soshi Shiori (`11-soshi-shiori`, character): After you win a conflict
- Hisu Mori Toride (`14-hisu-mori-toride`, stronghold): After you win a military conflict with more participating characters than your opponent
- Minami Kaze Regulars (`14-minami-kaze-regulars`, character): After this character wins a conflict in which you have more participating characters than your opponent
- Roving Michibiku (`14-roving-michibiku`, character): After this character wins a conflict
- Gift of Amaterasu (`16-gift-of-amaterasu`, event): After you win a conflict by 5 or more skill
- Honed Nodachi (`16-honed-nodachi`, attachment): After attached character wins a military conflict
- Midnight Prowler (`16-midnight-prowler`, character): After this character wins a military conflict
- Solitary Strength (`16-solitary-strength`, attachment): After attached character wins a conflict
- The Fires of Justice (`16-the-fires-of-justice`, event): After you win a military conflict
- Unmatched Expertise (`16-unmatched-expertise`, attachment): After attached character loses a conflict
- Spoils of War (`17-spoils-of-war`, event): After you win a military as the attacker
- Utaku Battle Steed (`17-utaku-battle-steed`, attachment): After attached character wins a military conflict
- Akodo Makoto (`18-akodo-makoto`, character): After this character wins a conflict
- Letter from the Daimyō (`18-letter-from-the-daimyo`, attachment): After attached character wins a political conflict
- Blade of 10,000 Battles (`19-blade-of-10-000-battles`, attachment): After attached character wins a conflict, if you are more honorable than your opponent
- False Loyalties (`19-false-loyalties`, event): After an opponent wins a conflict, if he or she is more honorable than you
- Benevolent Ambassador (`20-benevolent-ambassador`, character): After this character wins a conflict
- Utaku Rumaru (`20-utaku-rumaru`, character): After this character wins a conflict
- Court of Justice (`21-court-of-justice`, holding): After you win a political conflict
- Hida Sugi (`21-hida-sugi`, character): After this character wins a conflict
- Shosuro Ibuki (`21-shosuro-ibuki`, character): After this character wins a conflict
- Licensed Quarter (`22-licensed-quarter`, holding): After you win a conflict
- Togashi Yoshi (`22-togashi-yoshi`, character): After this character wins a conflict
- Quarrelsome Youth (`23-quarrelsome-youth`, character): After this character loses a conflict as an attacker, if you have fewer cards in your hand than your opponent
- Unified Company (`23-unified-company`, character): After this character wins a conflict, if you have fewer cards in you hand than your opponent
- Daidoji Harrier (`24-daidoji-harrier`, character): After this character wins a military conflict
- Golden Plains Raider (`24-golden-plains-raider`, character): After this character wins a conflict as an attacker
- Hida Kotoe (`25-hida-kotoe`, character): After this character wins a conflict as the defender
- Hida O-Ushi (`26-hida-o-ushi`, character): After you win a conflict as the defending player
- Purifier Apprentice (`26-purifier-apprentice`, character): After you win a conflict as the defending player
- Seventh Tower (`26-seventh-tower`, holding): After you win a conflict as the defending player, if the attacked province has a Kaiu Wall holding
- Slovenly Scavenger (`26-slovenly-scavenger`, character): After this character wins a conflict
- Watchtower of Valor (`26-watchtower-of-valor`, holding): After you win a conflict as the defending player, if the attacked province has a Kaiu Wall holding
- Champions of Yomi (`27-champions-of-yomi`, character): After a character you control loses a conflict as the attacker
- Embrace Death (`27-embrace-death`, event): After a Bushi character you control loses a conflict as the attacker
- Hall of Victories (`27-hall-of-victories`, holding): After a player wins a conflict
- Kenson no Gakka (`27-kenson-no-gakka`, province): After you lose a conflict at this province
- Kitsuki Kagi (`27-kitsuki-kagi`, character): After this character wins a conflict
- Kyūden Ikoma (`27-kyuden-ikoma`, stronghold): After a character you control loses a conflict as an attacker
- Matsu Tsuko (`27-matsu-tsuko`, character): After this character wins a conflict as the attacker against a non-stronghold province, if you are more honorable than your opponent
- Total Warfare (`27-total-warfare`, attachment): After a player loses a conflict at attached province
- Kuni Silencer (`28-kuni-silencer`, character): After this character wins a conflict as the defender
- Mindful Warrior (`28-mindful-warrior`, character): After this character wins a conflict
- Garanto Guardian (`29-garanto-guardian`, character): After this character wins a conflict as the defender
- Kakita Yoshi (`29-kakita-yoshi`, character): After this character wins a political conflict as the attacker
- Return from Shadows (`31-return-from-shadows`, event): After you win a conflict unopposed
- Studious (`31-studious`, attachment): After this character wins a conflict
- Third Whisker Sneak (`31-third-whisker-sneak`, character): After this character wins an unopposed conflict
- Way With Words (`31-way-with-words`, attachment): After this character wins a political conflict
- Kyofuki's Hammer (`32-kyofuki-s-hammer`, attachment): After attached character wins a conflict
- Mercenary Company (`32-mercenary-company`, character): After this character loses a conflict
- Sturdy Tetsubō (`32-sturdy-tetsubo`, attachment): After attached character wins a conflict
- Fearless Skirmisher (`33-fearless-skirmisher`, character): After this character wins a military conflict
- Seven Fold Palace (`33-seven-fold-palace`, stronghold): After an honored character you control wins a conflict as the attacker
- Asako Maezawa (`36-asako-maezawa`, character): After this character wins a conflict
- Chikara (`36-chikara`, attachment): After this character wins a conflict
- Guardians of Rokugan (`36-guardians-of-rokugan`, event): After you win a conflict as the defending player
- Self-Understanding (`36-self-understanding`, attachment): After this character wins a conflict
- Raze to the Ground (`37-raze-to-the-ground`, event): After you win a military conflict against a non-stronghold province
- Untainted (`39-untainted`, attachment): After you win a conflict at attached province
- Higashi Kaze Company (`40-higashi-kaze-company`, character): After this character wins a conflict
- Cleanse the Empire (`41-cleanse-the-empire`, event): After you win a conflict as the attacking player
- Daidoji Iron Warrior (`41-daidoji-iron-warrior`, character): After this character wins a conflict
- Inquisitorial Initiate (`41-inquisitorial-initiate`, character): After this character wins a conflict
- Setting the Standard (`41-setting-the-standard`, attachment): After this character wins a conflict
- Hida Backbreaker (`42-hida-backbreaker`, character): After this character wins a military conflict
- Iron Warrior Vanguard (`42-iron-warrior-vanguard`, character): After this character wins a conflict
- Jade-Inlaid Katana (`42-jade-inlaid-katana`, attachment): After this character wins a conflict
- Scarlet Sabre (`42-scarlet-sabre`, attachment): After this character wins a conflict

## 2. A conflict declared (33)

`ConflictDeclaredEvent` (the attacker, the defender, the ring, the type, the province, the attackers), in `react_to_conflict_declaration`; covert with `CharactersEvadedEvent` (Tengu Sensei, Shinjo Yasamura). Conditions: the attacked province (this province, one without holding, without Kaiu Wall), the element and the type, an attacker (this character, alone, of a trait), the number of conflicts declared against the player this phase (Shiba Pureheart).

- Endless Plains (`01-endless-plains`, province): After an attack is declared here
- Enlightened Warrior (`01-enlightened-warrior`, character): After an opponent selects a ring with fate on it
- Mantra of Fire (`01-mantra-of-fire`, event): After an opponent declares a fire conflict
- Secret Cache (`01-secret-cache`, province): After a conflict is declared against this province
- Mantra of Water (`04-mantra-of-water`, event): After your opponent declares a water conflict
- War Dog Master (`04-war-dog-master`, character): After this character is declared as an attacker
- Hiruma Outpost (`09-hiruma-outpost`, holding): After an opponent declares a conflict against a province that does not have a holding in it
- Sacred Sanctuary (`09-sacred-sanctuary`, province): After an attack is declared at this province
- Mantra of Earth (`14-mantra-of-earth`, event): After an opponent declares an earth conflict
- Tengu Sensei (`14-tengu-sensei`, character): After this character chooses a character using covert
- Midnight Revels (`16-midnight-revels`, province): After a conflict is declared against this province
- Fortified Assembly (`22-fortified-assembly`, province): After an attack is declared against this province
- Issue a Challenge (`22-issue-a-challenge`, event): After a Bushi character you control attacks alone
- Shiro Kitsuki (`23-shiro-kitsuki`, stronghold): After a conflict is declared
- Mantra of Air (`24-mantra-of-air`, event): After an opponent declares a air conflict
- Fuchi Mura (`25-fuchi-mura`, province): After an attack is declared against this province
- Shinjo Yasamura (`26-shinjo-yasamura`, character): After this character evades a character using covert
- Battle Aspirant (`28-battle-aspirant`, character): After this character is declared as an attacker in a military conflict
- Shadowed Alleyway (`28-shadowed-alleyway`, province): After an attack is declared against this province
- Temple of Daikoku (`28-temple-of-daikoku`, province): After an attack is declared against this province
- Temple of Jikoju (`28-temple-of-jikoju`, province): After an attack is declared against this province
- Temple of Osano-wo (`28-temple-of-osano-wo`, province): After an attack is declared against this province
- Temple of Shinsei's Wisdom (`28-temple-of-shinsei-s-wisdom`, province): After an attack is declared against this province
- Temple of the Thunders (`28-temple-of-the-thunders`, province): After an attack is declared against this province
- The Crashing Wave (`28-the-crashing-wave`, event): After an attack is declared against a province but before the province is revealed
- Never Yield (`30-never-yield`, event): After you declare an attack
- Mantra of Void (`31-mantra-of-void`, event): After an opponent declares a void conflict
- Under Siege (`32-under-siege`, event): After a conflict is declared
- Shiba Pureheart (`34-shiba-pureheart`, character): After an opponent declares their second conflict against you this phase
- A War on Two Fronts (`41-a-war-on-two-fronts`, event): After you declare a military conflict, if you are more honorable than your opponent
- Golden Plains (`41-golden-plains`, province): After an attack is declared against this province
- Moto Beastmaster (`42-moto-beastmaster`, character): After this character is declared as an attacker
- Third Tower (`42-third-tower`, holding): After an opponent declares a conflict against a province you control that does not contain a Kaiu Wall holding

## 3. A ring claimed (8)

`RingClaimedEvent` (the player, the ring), in `react_to_ring_claim`; `ClaimRing` claims one too, outside a conflict. Conditions: the element (that of the role of the player: Keeper Initiate, Seeker Initiate), the type of the conflict, a participating character, the defending player.

- Akodo Toturi (`01-akodo-toturi`, character): After you claim a ring during a military conflict in which this character is participating
- Doji Hotaru (`01-doji-hotaru`, character): After you claim a ring during a political conflict in which this character is participating
- Keeper Initiate (`01-keeper-initiate`, character): After you claim a ring that matches the element of your role
- Seeker Initiate (`01-seeker-initiate`, character): After you claim a ring that matches the element of your role
- Asako Tsuki (`07-asako-tsuki`, character): After the water ring is claimed
- Kudaka (`09-kudaka`, character): After you claim the air ring
- Isawa Ujina (`16-isawa-ujina`, character): After the void ring is claimed
- Kansen Haunt (`26-kansen-haunt`, holding): After you claim a ring during a conflict in which you are the defending player, if you are less honorable than an opponent

## 4. A pass on declaring a conflict (10)

`ConflictPassedEvent` (the player). Conditions: the player or an opponent, their ready characters.

- Intimidating Hida (`01-intimidating-hida`, character): After an opponent passes on declaring a conflict
- Hida Amoro (`08-hida-amoro`, character): After a player passes on declaring a conflict
- Pacifist Philosopher (`08-pacifist-philosopher`, character): After a player passes on declaring a conflict
- Fruitful Respite (`26-fruitful-respite`, event): After an opponent passes on declaring a conflict, if he or she controls 1 or more ready characters
- Mediator of Hostilities (`28-mediator-of-hostilities`, character): After a player passes on declaring a conflict
- One of the Forgotten (`28-one-of-the-forgotten`, character): After an opponent passes on declaring a conflict, if that opponent controls any ready characters
- Our Foe Does Not Wait (`29-our-foe-does-not-wait`, event): After you pass on declaring a conflict
- Kuni Purifier (`32-kuni-purifier`, character): After an opponent passes on declaring a conflict
- Asako Lawmaster (`33-asako-lawmaster`, character): After you pass on declaring a conflict
- Endless Archives (`33-endless-archives`, holding): After you pass on declaring a conflict

## 5. A province broken (9)

The transition `break` of `province_card` (the conflict, `BreakProvince`), in `react_to_broken_province` during a conflict: an event after the transition, as `ProvinceRevealedEvent`. Conditions: the player who breaks it (the attacker), the type of the conflict, a participating or attacking character, the traits of the participants. "Raze to the Ground" is under 1 (a conflict won against a non-stronghold province).

- For Greater Glory (`01-for-greater-glory`, event): After you break a province during a military conflict
- Shinjo Altansarnai (`01-shinjo-altansarnai`, character): After you break a province during a military conflict in which this character is participating
- Glorious Victory (`16-glorious-victory`, event): After you break a province during a military conflict
- Utaku Kamoko (`16-utaku-kamoko`, character): After an opponent breaks one of your provinces
- Callous Ashigaru (`21-callous-ashigaru`, attachment): After you break a province during a military conflict in which attached character is attacking
- Battle Meditation (`40-battle-meditation`, event): After you break a province during a conflict, if you control a participating Berserker character
- Castle of the Forgotten (`40-castle-of-the-forgotten`, stronghold): After you break a province
- Overrun (`40-overrun`, event): After you break a province
- Repentant Legion (`40-repentant-legion`, character): After you break a province during a conflict in which this character is participating

## 6. The honor dials of a bid revealed (7)

`HonorBidRevealedEvent` is dispatched for each player: the reactions come once every dial is revealed, with the bids (one event for the bid). Conditions: the bids compared (lower, equal), the honor of the players. The dials of a duel are under 12.

- Bayushi Manipulator (`01-bayushi-manipulator`, character): After honor dials are revealed
- Contingency Plan (`01-contingency-plan`, event): After honor dials are revealed
- Tactician's Apprentice (`13-tactician-s-apprentice`, character): After honor dials are revealed, if your bid is lower than an opponent's
- Akodo Kage (`15-akodo-kage`, character): After honor dials are revealed for an honor bid, if your bid is lower than an opponent's and you are more honorable than that opponent
- Ide Negotiator (`16-ide-negotiator`, character): After you reveal your honor dial
- Kitsuki Jusai (`18-kitsuki-jusai`, character): After honor dials are revealed, if your honor bid is equal to an opponent's
- Privileged Position (`28-privileged-position`, event): After honor dials are revealed during an honor bid

## 7. Declared as attacker or defender, defenders declared (4)

`ConflictDeclaredEvent` (the attackers) and `DefendersDeclaredEvent` (the defenders). Conditions: this character or the attached character, a Monk, a participating character (Arrogant Kakita, once the defenders are declared).

- Ancient Master (`09-ancient-master`, character): After attached character is declared as an attacker or defender
- Marauding Oni (`10-marauding-oni`, character): After this character is declared as an attacked or defender
- Ki Alignment (`38-ki-alignment`, event): After you declare a Monk character as an attacker or defender
- Arrogant Kakita (`39-arrogant-kakita`, character): After defenders are declared during a conflict in which this character is participating

## 8. A phase begins (24)

The places `dynasty_phase_begins`, `draw_phase_begins`, `conflict_phase_begins`, `fate_phase_begins`: an event when the phase begins, with the phase ("a phase begins": every phase). Conditions: the phase, the honor of the players, the number of characters they control, the Imperial Favor.

- Forgotten Library (`01-forgotten-library`, holding): After the draw phase begins
- Secluded Temple (`01-secluded-temple`, holding): After the conflict phase begins
- Steadfast Samurai (`01-steadfast-samurai`, character): After the fate phase begins, if you have at least 5 more honor than an opponent
- Master of Gisei Toshi (`06-master-of-gisei-toshi`, character): After the conflict phase begins
- Waning Hostilities (`06-waning-hostilities`, event): After the conflict phase begins
- Togashi Mendicant (`07-togashi-mendicant`, character): After the fate phase begins
- Secluded Shrine (`08-secluded-shrine`, holding): After the conflict phase begins
- Hiruma Kogoe (`11-hiruma-kogoe`, character): After the draw phase begins, if you have lower honor than an opponent
- Ethereal Dreamer (`13-ethereal-dreamer`, character): After the conflict phase begins
- Kuni Laboratory (`13-kuni-laboratory`, holding): After the conflict phase begins
- Kakita Ryoku (`17-kakita-ryoku`, character): After a phase begins, if you have the Imperial Favor
- Bayushi Shoju (`24-bayushi-shoju`, character): After the conflict phase begins
- Mirumoto Masashige (`25-mirumoto-masashige`, character): After the conflict phase begins, if an opponent controls more characters than you
- Agasha Hiyori (`26-agasha-hiyori`, character): After a phase begins
- All Out Assault (`28-all-out-assault`, event): After the conflict phase begins
- Expert Interpreter (`28-expert-interpreter`, character): After the conflict phase begins
- Roadside Inn (`28-roadside-inn`, holding): After the fate phase begins
- Seize the Day (`28-seize-the-day`, event): After the conflict phase begins
- Field of Ruin (`29-field-of-ruin`, attachment): After the conflict phase begins
- Daidoji Marketplace (`30-daidoji-marketplace`, holding): After the conflict phase begins
- Inspired Visionary (`30-inspired-visionary`, character): After the fate phase begins
- Divine Ancestry (`33-divine-ancestry`, event): After a phase begins
- Feeding an Army (`38-feeding-an-army`, event): After the conflict phase begins
- Reconnaissance (`39-reconnaissance`, event): After the conflict phase begins

## 9. A character or an attachment enters play (22)

The transitions into `in_play` and `attached` (played, put into play, played as an attachment, detached): an event after the transition, telling how it entered play, from where (a province: Akodo Gunsō), and whether into the conflict, as a defender (Hiruma Ambusher: `PutCharacterIntoPlay` into the conflict). Conditions: this card, during a conflict, the character an attachment is on (Elegant Tessen), the Bushi the player controls.

- Akodo Gunsō (`01-akodo-gunso`, character): After this character enters play from a province
- Hiruma Ambusher (`01-hiruma-ambusher`, character): After this character enters play as a defender
- Honored General (`01-honored-general`, character): After this character enters play
- Iuchi Wayfinder (`01-iuchi-wayfinder`, character): After this character enters play
- Matsu Beiona (`01-matsu-beiona`, character): After this character enters play, if you control 3 or more other Bushi characters
- Goblin Sneak (`02-goblin-sneak`, character): After this character enters play
- Kitsuki Yaruma (`02-kitsuki-yaruma`, character): After this character enters play
- Shrine Maiden (`03-shrine-maiden`, character): After this character enters play
- Nezumi Infiltrator (`12-nezumi-infiltrator`, character): After this character enters play during a conflict
- Opium Wastrel (`15-opium-wastrel`, character): After this character enters play during a conflict
- Iuchi Farseer (`17-iuchi-farseer`, character): After this character enters play
- Truthseeker (`22-truthseeker`, character): After this character enters play
- Army of the Rising Wave (`23-army-of-the-rising-wave`, character): After this character enters play
- Elegant Tessen (`25-elegant-tessen`, attachment): After this attachments enters play, if it is on a character with printed cost 2 or less
- Favorable Dealbroker (`26-favorable-dealbroker`, character): After this character enters play
- Kaiu Shihobu (`26-kaiu-shihobu`, character): After this character enters play
- Ashigaru Levy (`27-ashigaru-levy`, character): After this character enters play
- Hunting Falcon (`31-hunting-falcon`, attachment): After this attachment enters play
- Apprentice Engineer (`34-apprentice-engineer`, character): After this character enters play
- Doji Diplomat (`34-doji-diplomat`, character): After this character enters play
- Khanbulak Benefactor (`36-khanbulak-benefactor`, character): After this character enters play
- Daidoji Uji (`37-daidoji-uji`, character): After this character enters play

## 10. A player plays this card, or a character (14)

`CardPlayedEvent`, dispatched after the event of 9 ("play" is not "put into play", so that the two are apart, and 11 shares it): `PlayCharacter`, `PlayCharacterFromProvince`, `PlayAttachment`, `PlayCharacterAsAttachment`. Conditions: this card, from the hand or the provinces, into the conflict (Shinjo Ambusher), during a conflict, the clan and the printed cost of a character played by the player.

- Shosuro Miyako (`01-shosuro-miyako`, character): After you play a character from your hand
- Meek Informant (`02-meek-informant`, character): After you play this character
- Hiruma Skirmisher (`04-hiruma-skirmisher`, character): After you play this character
- Hawk Tattoo (`12-hawk-tattoo`, attachment): After you play this attachment during a conflict
- Mark of Shame (`13-mark-of-shame`, attachment): After you play this attachment
- Ignoble Enforcers (`15-ignoble-enforcers`, character): After you play this character, you may lose up to 3 honor
- Shinjo Ambusher (`16-shinjo-ambusher`, character): After you play this character into a conflict
- Agasha Taiko (`17-agasha-taiko`, character): After you play this character
- Aranat (`17-aranat`, character): After you play this character
- Asahina Takamori (`18-asahina-takamori`, character): After you play a crane character
- Benevolent Host (`18-benevolent-host`, character): After you play this character
- Shinjo Gunsō (`21-shinjo-gunso`, character): After you play this character from your provinces
- Shosuro Miyako (`21-shosuro-miyako`, character): After you play this character
- Audience Chamber (`42-audience-chamber`, holding): After you play a character with printed cost 4 or higher

## 11. A card played (21)

`CardPlayedEvent` of 10, dispatched for the events too (once in the discard pile), so that one condition reads every card played. Conditions: the player or an opponent, during a conflict, a participating or defending character, the type (event), the traits (Spell, Air, Water, Gaijin, Tactic, Tattoo, Weapon, Kihō, Meishōdō, Mahō), the fate spent on it (`FateSpentEvent`: Sententious Poet), the honor of the players. See "Questions to decide" for the targets and The Mirror's Gaze.

- Niten Master (`01-niten-master`, character): After you attach a Weapon attachment to this character
- Watch Commander (`01-watch-commander`, attachment): After an opponent plays a card during a conflict in which attached character is participating
- Doji Shigeru (`08-doji-shigeru`, character): After your opponent plays an event during a conflict in which this character is participating
- Shiba Tetsu (`08-shiba-tetsu`, character): After you play a Spell card during a conflict
- Isawa Uona (`09-isawa-uona`, character): After you play an Air card during a conflict
- The Mirror's Gaze (`09-the-mirror-s-gaze`, attachment): After the effects of an opponent's event resolve
- Yasuki Oguri (`15-yasuki-oguri`, character): After an opponent plays an event during a conflict in which this character is defending
- Winter Court Hosts (`18-winter-court-hosts`, character): After your opponent plays a card during a conflict in which this character is participating, if you are more honorable than that opponent
- Sententious Poet (`19-sententious-poet`, character): After your opponent spends fate to play a card during a conflict in which this character is participating
- Doji Hotaru (`21-doji-hotaru`, character): After an opponent plays a card during a conflict in which this character is participating
- Isawa Heiko (`21-isawa-heiko`, character): After you play a Water card
- Erudite Prestige (`23-erudite-prestige`, attachment): After you play a card during a conflict in which attached character is participating
- Withstand the Darkness (`23-withstand-the-darkness`, event): After an opponent plays an event that chooses a crab character you control as a target
- Dragon Tattoo (`25-dragon-tattoo`, attachment): After you play an event that chooses attached character as a target
- Togashi Acolyte (`25-togashi-acolyte`, character): After you play a card during a conflict in which attached character is participating
- Togashi Gaijutsu (`25-togashi-gaijutsu`, character): After you play a Tattoo attachment on a character
- Worldly Shiotome (`34-worldly-shiotome`, character): After you play a Gaijin card
- Iuchi Shahai (`38-iuchi-shahai`, character): After you play a Meishōdō or Mahō card
- Field Tactician (`42-field-tactician`, character): After you play a Tactic card
- Silent Enforcer (`42-silent-enforcer`, character): After you play an event during a conflict in which this character is participating
- Togashi Dreamer (`42-togashi-dreamer`, character): After you play a Kihō event during a conflict in which this character is participating

## 12. Duels (7)

`DuelWonEvent` (the winner), `DuelEndedEvent` (Kyūden Kakita), and the dials of a duel revealed (as 6, during a duel). Conditions: the characters of the duel (`DuelState`: this character, the attached character, a Duelist, controlled by the player).

- Kakita Blade (`03-kakita-blade`, attachment): After attached character wins a duel
- Insult to Injury (`11-insult-to-injury`, event): After a Duelist character you control wins a duel
- Iaijutsu Master (`16-iaijutsu-master`, attachment): After honor dials are revealed during a duel in which attached character is participating
- Proving Ground (`16-proving-ground`, holding): After a character you control wins a duel
- Distinguished Dōjō (`18-distinguished-dojo`, holding): After you win a duel
- Kyūden Kakita (`18-kyuden-kakita`, stronghold): After a duel resolves
- Niten Pupil (`20-niten-pupil`, character): After honor dials are revealed for a duel, if this character is involved in the duel

## 13. A character moves to the conflict (7)

`CharacterMovedToConflictEvent` (`MoveToConflict`), `CharacterPlayedIntoConflictEvent` (Hida Tsuru), and the declarations of 2 and 7 for "commits". Conditions: this character or the attached character participating, the type of the conflict, a character of the player.

- Ide Trader (`01-ide-trader`, character): After 1 or more characters move to a conflict in which this character is participating
- Spyglass (`01-spyglass`, attachment): After attached character commits to a conflict or moves to a conflict
- Outskirts Sentry (`16-outskirts-sentry`, character): After a character is moved to the conflict in which this character is participating
- Hida Tsuru (`17-hida-tsuru`, character): After a character is moved to or played into a conflict in which this character is participating
- Moto Stables (`30-moto-stables`, holding): After a character you control moves to a conflict
- Twilight Rider (`30-twilight-rider`, character): After this character moves to a conflict
- Daikyū (`42-daikyu`, attachment): After a character either commits to or moves to a military conflict this character is participating in

## 14. Fate placed on or removed from a character (6)

`FatePlacedOnCardEvent`, `FateRemovedFromCardEvent` (`RemoveFate`, the fate phase, leaving play). Conditions: this character or a character of the player, outside the fate phase; Starry Heaven Sanctuary reads the total removed in the fate phase, once the step ends (one event per character today); "by a card effect" (Adorned Temple) is a cause.

- Ikoma Prodigy (`01-ikoma-prodigy`, character): After 1 or more fate is placed on this character
- Starry Heaven Sanctuary (`22-starry-heaven-sanctuary`, holding): After fate is removed from characters in the fate phase
- Kaito Mai (`38-kaito-mai`, character): After 1 or more fate is removed from this character outside the fate phase
- Shadowed Village (`38-shadowed-village`, holding): After 1 or more fate is removed from a character you control outside the fate phase
- Kami of Ancient Wisdom (`40-kami-of-ancient-wisdom`, character): After 1 or more fate is removed from a character outside the fate phase
- Adorned Temple (`41-adorned-temple`, holding): After 1 or more fate is placed on a character you control by a card effect

## 15. The honor of a player (5)

`HonorGainedEvent`, `HonorLostEvent`, `HonorTransferredEvent` (Way of the Chrysanthemum: given by a bid, in the step of the bid). Conditions: the player or an opponent; "through a card effect", "an opponent's card or ring effect" are causes.

- Way of the Chrysanthemum (`05-way-of-the-chrysanthemum`, event): After 1 or more honor is given to you from an honor bid
- Heartless Intimidator (`09-heartless-intimidator`, character): After an opponent loses 1 or more honor
- Shiotome Heroine (`16-shiotome-heroine`, character): After an opponent gains 1 or more honor through a card effect
- Righteous Samurai (`34-righteous-samurai`, character): After an opponent's card or ring effect causes you to lose 1 or more honor
- Sinister Peacekeeper (`36-sinister-peacekeeper`, character): After an opponent gains 1 or more honor

## 16. A status token, honored, dishonored (4)

`PersonalHonorChangedEvent` (honored, dishonored), `StatusTokenPlacedEvent` (Final Whisper: a token received). Conditions: this character or another character of the player, an opponent's character; "an opponent's card ability or ring effect" is a cause.

- Savvy Politician (`01-savvy-politician`, character): After this character is honored
- Armament Artisan (`18-armament-artisan`, character): After another character you control is honored
- Ardent Omoidasu (`27-ardent-omoidasu`, character): After an opponent's card ability or ring effect dishonors a character you control
- Final Whisper (`40-final-whisper`, event): After an opponent's character receives a status token

## 17. The hand (4)

A discard from a hand (the transitions of `DiscardCard`, `DiscardRandomCardFromHand`): an event after them, once per message ("1 or more cards"); `CardsLookedAtEvent` and `CardsRevealedEvent` on the hand of an opponent. Conditions: the owner of the hand; "due to the cost or effect of a card ability" is a cause.

- Shosuro Hyobu (`17-shosuro-hyobu`, character): After an opponent discards 1 or more cards from his or her hand due to the cost or effect of a card ability
- Akodo Motivator (`27-akodo-motivator`, character): After an opponent's card ability or ring effect discards 1 or more cards from your hand
- Keen Warrior (`36-keen-warrior`, character): After you look at 1 or more cards in an opponent's hand (or those are revealed)
- Discerning Yoriki (`42-discerning-yoriki`, character): After you look at 1 or more cards in an opponent's hand (or those are revealed)

## 18. The dynasty phase (4)

`OpportunityPassedEvent` in the dynasty phase (`firstToPass`, with the fate gained: `FateGainedEvent`), `FateCollectedEvent` (Shiro Shinjo).

- Shinjo Scout (`07-shinjo-scout`, character): After you gain fate for passing in the dynasty phase
- Sneaky Shinjo (`10-sneaky-shinjo`, character): After you pass in the dynasty phase
- Shiro Shinjo (`17-shiro-shinjo`, stronghold): After you collect fate during the dynasty phase
- Ride at Dawn (`32-ride-at-dawn`, event): After you pass during the dynasty phase

## 19. Ring effects (5)

`RingFateTakenEvent` (the fate of a ring taken: Kitsuki Yuikimi, Trusted Advisor, Four Temples Advisor), during a conflict with a participating character. A ring effect resolved (Medium of the Living Soul) and a character honored or dishonored by the fire ring (Isawa Tsuke) are not known to the engine: the players apply the ring effect with the generic messages, in `resolve_ring_effects`; offered in that step, or once it ends.

- Kitsuki Yuikimi (`20-kitsuki-yuikimi`, character): After you gain a fate from a ring during a conflict in which this character is participating
- Trusted Advisor (`21-trusted-advisor`, character): After you gain fate from a ring during a conflict in which this character is participating
- Four Temples Advisor (`25-four-temples-advisor`, attachment): After you gain fate from a ring
- Isawa Tsuke (`27-isawa-tsuke`, character): After you honor or dishonor a character by the effect of the fire ring
- Medium of the Living Soul (`28-medium-of-the-living-soul`, character): After you resolve a ring effect during a conflict in which this character is participating

## 20. A character bowed or readied (3)

The transitions `bow` and `ready`: an event after them. Conditions: the controller; the cause (an opponent's card or ring effect, the resolution of the conflict: the bows of `return_home`; Curry Favor also counts the conflicts the player declared).

- Ready for Battle (`01-ready-for-battle`, event): After an opponent's card effect or a ring effect bows a character you control
- Curry Favor (`05-curry-favor`, event): After a character you control bows as a result of conflict resolution from the second conflict you declared this round
- Earth Becomes Sky (`20-earth-becomes-sky`, event): After a character an opponent controls is readied

## 21. A card turned face up in a province (2)

The transition `turn_faceup` of a dynasty card in a province (the dynasty phase, an effect): an event after it. Lost Papers is an event reacting from a province, where it is no card the player can play: its offer needs it to be usable there (not `PlayService` from the hand).

- Mountaintop Statuary (`05-mountaintop-statuary`, holding): After this holding is turned faceup
- Lost Papers (`31-lost-papers`, event): After this card is revealed in a province

## 22. A character leaves play (2)

The transitions out of `in_play` (`LeavePlayListener`): an event once the card has left play. Conditions: a character of the player, its clan, during a conflict or the conflict phase.

- Vengeful Berserker (`01-vengeful-berserker`, character): After another character you control leaves play during a conflict
- Yasuki Taka (`06-yasuki-taka`, character): After a crab character leaves play during the conflict phase

## 23. An ability triggered (2)

`TriggerActionAbility`, `TriggerInterruptAbility`, `TriggerReactionAbility`: an event once triggered. Conditions: the controller of the card, during a conflict with the attached character participating; "a character" (not a holding, a province…).

- Kamayari (`03-kamayari`, attachment): After a character triggers an ability during a conflict in which attached character is participating
- Kirei-ko (`16-kirei-ko`, event): After a character your opponent controls triggers an ability

## 24. The end of the resolution of a conflict (2)

The place `return_home`: the conflict must remember the provinces broken during it (Breakthrough also counts the conflicts the player declared this phase).

- Breakthrough (`01-breakthrough`, event): After the resolution of your first conflict this phase as the attacking player, if that conflict broke a province
- Ivory Kingdoms Unicorn (`17-ivory-kingdoms-unicorn`, character): After the resolution of a conflict in which this character was attacking, if a province was broken in the conflict

## Forced reactions (16)

Their effect is not the choice of their player ("Forced Reaction:"): the engine does not offer them. They stay in their groups above. A reminder to their player (a window without the choice to decline) is left for later.

- A phase begins: Steadfast Samurai (`01-steadfast-samurai`), Kuni Laboratory (`13-kuni-laboratory`), Bayushi Shoju (`24-bayushi-shoju`).
- A conflict won or lost: Unmatched Expertise (`16-unmatched-expertise`), Hall of Victories (`27-hall-of-victories`), Total Warfare (`27-total-warfare`), Mercenary Company (`32-mercenary-company`).
- A conflict declared: Temple of Daikoku (`28-temple-of-daikoku`), Temple of Jikoju (`28-temple-of-jikoju`), Temple of Osano-wo (`28-temple-of-osano-wo`), Temple of Shinsei's Wisdom (`28-temple-of-shinsei-s-wisdom`), Temple of the Thunders (`28-temple-of-the-thunders`).
- A ring claimed: Isawa Ujina (`16-isawa-ujina`).
- A pass on declaring a conflict: Hida Amoro (`08-hida-amoro`).
- Declared as attacker or defender, defenders declared: Marauding Oni (`10-marauding-oni`), Arrogant Kakita (`39-arrogant-kakita`).

## Out of scope (4)

- Strategizing (`28-strategizing`, province): the rings on the provinces belong to the Enlightenment format.
- Mindful Warrior (`28-mindful-warrior`, character): it switches a ring with a ring claimed on a province, of the Enlightenment format too (listed under 1).
- Shadowed Alleyway (`28-shadowed-alleyway`, province): it moves a ring claimed on a province (listed under 2).
- Greater Understanding (`19-greater-understanding`, attachment): an attachment on a ring, not playable (`HANDOFF.md`, "Cards not playable yet").

## Done: a province revealed (34)

`ProvinceRevealedEvent`, `tests/CardScript/ProvinceRevealedReactionsTest.php`.

- Elemental Fury (`01-elemental-fury`, province): After this province is revealed
- Night Raid (`01-night-raid`, province): After this province is revealed
- Rally to the Cause (`01-rally-to-the-cause`, province): After this province is revealed
- Seeker of Air (`01-seeker-of-air`, role): After an air province you control is revealed
- Seeker of Earth (`01-seeker-of-earth`, role): After an earth province you control is revealed
- Seeker of Fire (`01-seeker-of-fire`, role): After a fire province you control is revealed
- Seeker of Void (`01-seeker-of-void`, role): After a void province you control is revealed
- Seeker of Water (`01-seeker-of-water`, role): After a water province you control is revealed
- Tears of Amaterasu (`02-tears-of-amaterasu`, province): After this province is revealed
- Child of the Plains (`04-child-of-the-plains`, character): After a province this character is attacking is revealed
- Guardians of the Seikitsu (`05-guardians-of-the-seikitsu`, province): After this province is revealed
- Flooded Waste (`12-flooded-waste`, province): After this province is revealed
- Raging Battleground (`13-raging-battleground`, province): After this province is revealed
- Illustrious Forge (`16-illustrious-forge`, province): After this province is revealed
- Temple of the Dragons (`16-temple-of-the-dragons`, province): After this province is revealed
- Compass (`17-compass`, attachment): After an opponent's province is revealed during a conflict in which attached character is participating
- Khan's Ordu (`17-khan-s-ordu`, province): After this province is revealed
- Shinjo Trailblazer (`17-shinjo-trailblazer`, character): After an opponent's province is revealed during a conflict
- Market of Kaze-no-Kami (`18-market-of-kaze-no-kami`, province): After this province is revealed
- Scene of the Crime (`19-scene-of-the-crime`, province): After this province is revealed
- Emperor's Summons (`22-emperor-s-summons`, province): After this province is revealed
- Outflank (`23-outflank`, event): After an opponent's province is revealed during a conflict
- Way Station Trader (`28-way-station-trader`, character): After a province is revealed during a conflict in which this character is participating
- Sharing Shinsei's Wisdom (`29-sharing-shinsei-s-wisdom`, province): After this province is revealed
- Retire to the Brotherhood (`30-retire-to-the-brotherhood`, province): After this province is revealed
- Spectral Visitation (`30-spectral-visitation`, province): After this province is revealed
- Kitsuki Chiari (`31-kitsuki-chiari`, character): After a province you control is revealed
- Breaking In (`32-breaking-in`, province): After this province is revealed
- Waterfall Tattoo (`33-waterfall-tattoo`, attachment): After a province you control is revealed
- Ganzu Warrior (`34-ganzu-warrior`, character): After a province is revealed during a conflict in which this character is participating
- Brushfires (`36-brushfires`, province): After this province is revealed
- Seeking Enlightenment (`38-seeking-enlightenment`, province): After this province is revealed
- Deserted Shrine (`40-deserted-shrine`, province): After this province is revealed
- Offerings to the Kami (`40-offerings-to-the-kami`, province): After this province is revealed
