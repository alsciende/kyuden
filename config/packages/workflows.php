<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * Workflows of the game and of each card type.
 *
 * Card workflows: the place of a card is computed from its stack and orientation (App\Workflow\CardMarkingStore);
 * the `stack` and `orientation` metadata of a transition say how it moves or turns the card
 * (applied by App\Workflow\CardTransitionListener). A transition that moves a card also gives its orientation.
 *
 * Every transition gives the template of its entry in the game log (`log`, see App\GameLog\CardTransitionLogListener
 * and App\GameLog\GameTransitionLogListener for the params of a card transition).
 */

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\CardTypes\RoleCard;
use App\Entity\CardTypes\StrongholdCard;
use App\Entity\Game;
use App\Enum\Orientation;
use App\Enum\PassingRule;
use App\Enum\StackType;
use App\Workflow\WorkflowConfig;

// we are in a different place if the phase of the game is different AND the available player actions are different
$gameTransitions = [
    WorkflowConfig::transition('start_game', 'join_game', 'choose_stronghold', ['auto' => true, 'log' => 'The game starts']), // select first player
    WorkflowConfig::transition('setup_game', 'choose_stronghold', 'dynasty_mulligan', ['auto' => true, 'log' => 'The players set up the game']),
    // setup 6 and 7: in player order, each player mulligans the cards of their provinces, then of their starting hand;
    // passed at once in a game without mulligan (the house bot)
    WorkflowConfig::transition('dynasty_mulligan_ended', 'dynasty_mulligan', 'conflict_mulligan', ['auto' => true, 'log' => 'The players draw their starting hand']),
    WorkflowConfig::transition('conflict_mulligan_ended', 'conflict_mulligan', 'dynasty_phase_begins', ['auto' => true, 'log' => 'The setup is over']),
    WorkflowConfig::transition('dynasty_phase_began', 'dynasty_phase_begins', 'reveal_facedown_dynasty_cards', ['auto' => true, 'log' => 'The dynasty phase begins']),
    WorkflowConfig::transition('dynasty_cards_revealed', 'reveal_facedown_dynasty_cards', 'collect_fate', ['auto' => true, 'log' => 'The facedown dynasty cards are revealed']),
    WorkflowConfig::transition('fate_collected', 'collect_fate', 'play_cards_from_provinces', ['auto' => true, 'log' => 'Every player has collected fate']),
    WorkflowConfig::transition('dynasty_actions_passed', 'play_cards_from_provinces', 'dynasty_phase_ends', ['auto' => true, 'log' => 'Every player has passed: no more cards are played from provinces']),
    WorkflowConfig::transition('dynasty_phase_ended', 'dynasty_phase_ends', 'draw_phase_begins', ['auto' => true, 'log' => 'The dynasty phase ends']),
    WorkflowConfig::transition('draw_phase_began', 'draw_phase_begins', 'honor_bid', ['auto' => true, 'log' => 'The draw phase begins']),
    WorkflowConfig::transition('honor_bids_confirmed', 'honor_bid', 'reveal_honor_dials', ['auto' => true, 'log' => 'Every player has bid: the honor dials are revealed']),
    WorkflowConfig::transition('honor_dials_revealed', 'reveal_honor_dials', 'transfer_honor', ['auto' => true, 'log' => 'The reactions to the honor dials are over']),
    WorkflowConfig::transition('honor_transferred', 'transfer_honor', 'draw_cards', ['auto' => true, 'log' => 'Honor is transferred according to the bids']),
    WorkflowConfig::transition('cards_drawn', 'draw_cards', 'draw_phase_action_window', ['auto' => true, 'log' => 'Each player draws as many cards as their bid']),
    WorkflowConfig::transition('draw_actions_passed', 'draw_phase_action_window', 'draw_phase_ends', ['auto' => true, 'log' => 'The action window of the draw phase closes']),
    WorkflowConfig::transition('draw_phase_ended', 'draw_phase_ends', 'conflict_phase_begins', ['auto' => true, 'log' => 'The draw phase ends']),
    // 3.1, then the loop of 3.3: the action window opens again after each conflict or conflict opportunity passed
    WorkflowConfig::transition('conflict_phase_began', 'conflict_phase_begins', 'conflict_phase_action_window', ['auto' => true, 'log' => 'The conflict phase begins']),
    WorkflowConfig::transition('conflict_phase_actions_passed', 'conflict_phase_action_window', 'declare_conflict', ['auto' => true, 'log' => 'The action window closes: a conflict may be declared']),
    WorkflowConfig::transition('conflict_opportunities_exhausted', 'conflict_phase_action_window', 'determine_imperial_favor', ['auto' => true, 'log' => 'No conflict opportunity is left']),
    WorkflowConfig::transition('conflict_passed', 'declare_conflict', 'conflict_ends', ['log' => 'No conflict is declared']),
    // conflict resolution (3.2 to 3.2.8)
    WorkflowConfig::transition('conflict_declared', 'declare_conflict', 'reveal_attacked_province', ['log' => 'The defending player reveals the attacked province']),
    // the defending player reveals the attacked province (RevealProvince); a province already faceup is passed at once
    WorkflowConfig::transition('attacked_province_revealed', 'reveal_attacked_province', 'react_to_conflict_declaration', ['auto' => true, 'log' => 'The players may react to the conflict declaration']),
    WorkflowConfig::transition('declaration_reactions_passed', 'react_to_conflict_declaration', 'declare_defenders', ['auto' => true, 'log' => 'The reactions to the conflict declaration are over']),
    WorkflowConfig::transition('defenders_declared', 'declare_defenders', 'conflict_action_window', ['log' => 'The action window of the conflict opens']),
    WorkflowConfig::transition('conflict_actions_passed', 'conflict_action_window', 'determine_result', ['auto' => true, 'log' => 'The action window of the conflict closes']),
    WorkflowConfig::transition('result_determined', 'determine_result', 'react_to_conflict_result', ['auto' => true, 'log' => 'The result of the conflict is determined']),
    WorkflowConfig::transition('result_reactions_passed', 'react_to_conflict_result', 'apply_unopposed', ['auto' => true, 'log' => 'The reactions to the conflict result are over']),
    WorkflowConfig::transition('unopposed_applied', 'apply_unopposed', 'break_province', ['auto' => true, 'log' => 'The conflict is checked for being unopposed']),
    WorkflowConfig::transition('provinces_broken', 'break_province', 'react_to_broken_province', ['auto' => true, 'log' => 'The attacked provinces are checked for breaking']),
    WorkflowConfig::transition('break_reactions_passed', 'react_to_broken_province', 'resolve_ring_effects', ['auto' => true, 'log' => 'The reactions to the broken province are over']),
    // nothing to react to when no province is broken
    WorkflowConfig::transition('no_province_broken', 'react_to_broken_province', 'resolve_ring_effects', ['auto' => true, 'log' => 'No province is broken']),
    WorkflowConfig::transition('ring_effects_resolved', 'resolve_ring_effects', 'claim_ring', ['auto' => true, 'log' => 'The ring effects are resolved']),
    WorkflowConfig::transition('ring_claimed', 'claim_ring', 'react_to_ring_claim', ['auto' => true, 'log' => 'The winner of the conflict, if any, claims the contested ring']),
    WorkflowConfig::transition('claim_reactions_passed', 'react_to_ring_claim', 'return_home', ['auto' => true, 'log' => 'The reactions to the ring claim are over']),
    WorkflowConfig::transition('returned_home', 'return_home', 'conflict_ends', ['auto' => true, 'log' => 'The participating characters return home']),
    WorkflowConfig::transition('conflict_ended', 'conflict_ends', 'conflict_phase_action_window', ['auto' => true, 'log' => 'The conflict ends']),
    // 3.4 to 3.5
    WorkflowConfig::transition('imperial_favor_contest_began', 'determine_imperial_favor', 'glory_count', ['auto' => true, 'log' => 'The Imperial Favor is contested']),
    WorkflowConfig::transition('glory_counted', 'glory_count', 'claim_imperial_favor', ['auto' => true, 'log' => 'The glory is counted']),
    WorkflowConfig::transition('imperial_favor_claimed', 'claim_imperial_favor', 'conflict_phase_ends', ['log' => 'The Imperial Favor contest is over']),
    // the glory count is a tie: the Imperial Favor remains in its current state
    WorkflowConfig::transition('imperial_favor_unclaimed', 'claim_imperial_favor', 'conflict_phase_ends', ['auto' => true, 'log' => 'The glory count is a tie: the Imperial Favor stays where it is']),
    WorkflowConfig::transition('conflict_phase_ended', 'conflict_phase_ends', 'fate_phase_begins', ['auto' => true, 'log' => 'The conflict phase ends']),
    WorkflowConfig::transition('fate_phase_began', 'fate_phase_begins', 'discard_characters_with_no_fate', ['auto' => true, 'log' => 'The fate phase begins']),
    WorkflowConfig::transition('characters_discarded', 'discard_characters_with_no_fate', 'remove_fate_from_characters', ['auto' => true, 'log' => 'The characters without fate are discarded']),
    WorkflowConfig::transition('fate_removed', 'remove_fate_from_characters', 'place_fate_on_unclaimed_rings', ['auto' => true, 'log' => 'Fate is removed from the characters']),
    WorkflowConfig::transition('fate_placed_on_rings', 'place_fate_on_unclaimed_rings', 'fate_phase_action_window', ['auto' => true, 'log' => 'Fate is placed on the unclaimed rings']),
    WorkflowConfig::transition('fate_actions_passed', 'fate_phase_action_window', 'ready_cards', ['auto' => true, 'log' => 'The action window of the fate phase closes']),
    WorkflowConfig::transition('cards_readied', 'ready_cards', 'discard_from_provinces', ['auto' => true, 'log' => 'The cards are readied']),
    WorkflowConfig::transition('province_discards_passed', 'discard_from_provinces', 'return_rings', ['auto' => true, 'log' => 'The players are done discarding from their provinces']),
    WorkflowConfig::transition('rings_returned', 'return_rings', 'pass_first_player_token', ['auto' => true, 'log' => 'The rings return to the unclaimed pool']),
    WorkflowConfig::transition('first_player_token_passed', 'pass_first_player_token', 'fate_phase_ends', ['auto' => true, 'log' => 'The first player token is passed']),
    WorkflowConfig::transition('fate_phase_ended', 'fate_phase_ends', 'dynasty_phase_begins', ['auto' => true, 'log' => 'The fate phase ends']),
];
// the game ends as soon as a player meets a victory condition, before any other automatic transition
$gameTransitions[] = WorkflowConfig::transition(
    'end_game',
    WorkflowConfig::placesOf($gameTransitions, 'join_game'),
    Game::STATE_ENDED,
    ['auto' => true, 'priority' => 100, 'log' => 'The game ends'],
);

return App::config([
    'framework' => [
        'workflows' => [
            'game' => [
                'type' => 'state_machine',
                'audit_trail' => [
                    'enabled' => true,
                ],
                'marking_store' => [
                    'type' => 'method',
                    'property' => 'currentPlace',
                ],
                'supports' => [Game::class],
                'initial_marking' => 'join_game',
                // places not listed here are taken from the transitions;
                // `action_window`: players can trigger action abilities (TriggerActionAbility) and play cards from hand,
                // characters and attachments only without `play_from_hand: false`
                // `opportunities`: players have opportunities to act and can pass them (PassOpportunity); the PassingRule
                // says whether passing is final or the step ends once every player has passed consecutively
                // transition metadata: `auto` (applied by WorkflowTransitionMiddleware), `priority` (the highest enabled
                // automatic transition is applied first; 0 by default)
                'places' => [
                    [
                        'name' => 'play_cards_from_provinces',
                        'metadata' => [
                            'action_window' => true,
                            // 1.4 special action window: no character or attachment from hand
                            'play_from_hand' => false,
                            'opportunities' => PassingRule::Final,
                        ],
                    ],
                    [
                        // reactions to the honor dials being revealed, e.g. changing a bid (SetHonorBid)
                        'name' => 'reveal_honor_dials',
                        'metadata' => [
                            'opportunities' => PassingRule::Final,
                        ],
                    ],
                    [
                        'name' => 'draw_phase_action_window',
                        'metadata' => [
                            'action_window' => true,
                            'opportunities' => PassingRule::Consecutive,
                        ],
                    ],
                    [
                        'name' => 'conflict_phase_action_window',
                        'metadata' => [
                            'action_window' => true,
                            'opportunities' => PassingRule::Consecutive,
                        ],
                    ],
                    [
                        // the defending player has the first opportunity to act
                        'name' => 'conflict_action_window',
                        'metadata' => [
                            'action_window' => true,
                            'opportunities' => PassingRule::Consecutive,
                        ],
                    ],
                    [
                        // the attacking player may resolve the ring effect if they won the conflict
                        'name' => 'resolve_ring_effects',
                        'metadata' => [
                            'opportunities' => PassingRule::Final,
                        ],
                    ],
                    [
                        'name' => 'fate_phase_action_window',
                        'metadata' => [
                            'action_window' => true,
                            'opportunities' => PassingRule::Consecutive,
                        ],
                    ],
                    [
                        // each player may discard faceup dynasty cards from their unbroken provinces (DiscardCard, which
                        // refills them)
                        'name' => 'discard_from_provinces',
                        'metadata' => [
                            'opportunities' => PassingRule::Final,
                        ],
                    ],
                    ...array_map(
                        // reactions to a framework step: every player can react, then passes
                        fn (string $place): array => ['name' => $place, 'metadata' => ['opportunities' => PassingRule::Final]],
                        ['react_to_conflict_declaration', 'react_to_conflict_result', 'react_to_broken_province', 'react_to_ring_claim'],
                    ),
                ],
                'transitions' => $gameTransitions,
            ],
            'conflict_card' => WorkflowConfig::cardWorkflow(
                ConflictCard::class,
                [ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_HAND, ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_ATTACHED, ConflictCard::STATE_DISCARD_PILE, ConflictCard::STATE_REMOVED_FROM_GAME, ConflictCard::STATE_UNDERNEATH],
                [
                    WorkflowConfig::transition('draw_card', ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_HAND, [
                        'log' => '{owner} draws {card}',
                        'stack' => StackType::Hand,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // a character or an attachment played (PlayCharacter, PlayAttachment), from its hand or from elsewhere,
                    // by the `player` of the context, whoever owns it
                    WorkflowConfig::transition('play_card', [ConflictCard::STATE_HAND, ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_DISCARD_PILE, ConflictCard::STATE_REMOVED_FROM_GAME, ConflictCard::STATE_UNDERNEATH], ConflictCard::STATE_IN_PLAY, [
                        'log' => '{player} plays {card} from {from} into {to}',
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // a character played as an attachment (PlayCharacterAsAttachment: Tattooed Wanderer, Togashi Kazue…), from
                    // where a character is played: the character it is attached to is the `stack` of the context
                    WorkflowConfig::transition('play_as_attachment', [ConflictCard::STATE_HAND, ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_DISCARD_PILE, ConflictCard::STATE_REMOVED_FROM_GAME, ConflictCard::STATE_UNDERNEATH], ConflictCard::STATE_ATTACHED, [
                        'log' => '{player} plays {card} from {from} as an attachment on {character}',
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // an attachment detached and turned into a character (DetachAsCharacter: Promising Youth, Togashi
                    // Hoshi), in a new stack of its controller: a character played as an attachment, or an attachment
                    // (DetachAsCharacterHandler checks that it is attached to a character)
                    ...array_map(
                        fn (string $from): array => WorkflowConfig::transition('detach', $from, ConflictCard::STATE_IN_PLAY, [
                            'log' => '{card} is detached from {character} and becomes a character',
                            'stack' => StackType::InPlay,
                            'orientation' => Orientation::FaceUp,
                            'character' => true,
                        ]),
                        [ConflictCard::STATE_ATTACHED, ConflictCard::STATE_IN_PLAY],
                    ),
                    // an event played (PlayActionEvent, PlayInterruptEvent, PlayReactionEvent), from its hand or from
                    // elsewhere: it goes to the discard pile of its owner
                    WorkflowConfig::transition('play_event', [ConflictCard::STATE_HAND, ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_DISCARD_PILE, ConflictCard::STATE_REMOVED_FROM_GAME, ConflictCard::STATE_UNDERNEATH], ConflictCard::STATE_DISCARD_PILE, [
                        'log' => '{card} is put from {from} into {to}',
                        'stack' => StackType::ConflictDiscardPile,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // a character or an attachment put into play by an effect, without being played
                    // (PutCharacterIntoPlay, PutAttachmentIntoPlay): the card an attachment goes on is the `stack` of the
                    // context
                    WorkflowConfig::transition('put_into_play', [ConflictCard::STATE_HAND, ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_DISCARD_PILE, ConflictCard::STATE_REMOVED_FROM_GAME, ConflictCard::STATE_UNDERNEATH], ConflictCard::STATE_IN_PLAY, [
                        'log' => '{card} is put into play from {from}',
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('discard_card', [ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_HAND, ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_ATTACHED, ConflictCard::STATE_REMOVED_FROM_GAME, ConflictCard::STATE_UNDERNEATH], ConflictCard::STATE_DISCARD_PILE, [
                        'log' => '{card} is discarded from {from}',
                        'stack' => StackType::ConflictDiscardPile,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('sacrifice', [ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_ATTACHED], ConflictCard::STATE_DISCARD_PILE, [
                        'log' => '{card} is sacrificed',
                        'stack' => StackType::ConflictDiscardPile,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('return_to_hand', [ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_ATTACHED], ConflictCard::STATE_HAND, [
                        'log' => '{card} is returned to {to}',
                        'stack' => StackType::Hand,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('put_back', [ConflictCard::STATE_HAND, ConflictCard::STATE_DISCARD_PILE], ConflictCard::STATE_DRAW_DECK, [
                        'log' => '{card} is put from {from} into {to}',
                        'stack' => StackType::ConflictDrawDeck,
                        'orientation' => Orientation::FaceDown,
                    ]),
                    // a card searched for in a deck, taken back from the discard pile or from out of play (AddCardToHand)
                    WorkflowConfig::transition('add_to_hand', [ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_DISCARD_PILE, ConflictCard::STATE_REMOVED_FROM_GAME, ConflictCard::STATE_UNDERNEATH], ConflictCard::STATE_HAND, [
                        'log' => '{card} is added from {from} to {to}',
                        'stack' => StackType::Hand,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // a character or an attachment whose control is taken (TakeControl): the new controller is the
                    // `controller` of the context; a character moves to a stack of its controller (TakeControlListener)
                    ...WorkflowConfig::inPlace('take_control', [ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_ATTACHED], [
                        'log' => '{controller} takes control of {card}',
                    ]),
                    // an attachment moved to another character or a province (MoveAttachment): the target is the `stack` of
                    // the context
                    ...WorkflowConfig::inPlace('move_attachment', [ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_ATTACHED], [
                        'log' => '{card} is moved from {from} to {to}',
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    ...WorkflowConfig::putOnDeck(
                        [ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_HAND, ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_ATTACHED, ConflictCard::STATE_DISCARD_PILE, ConflictCard::STATE_REMOVED_FROM_GAME, ConflictCard::STATE_UNDERNEATH],
                        ConflictCard::STATE_DRAW_DECK,
                        StackType::ConflictDrawDeck,
                    ),
                    ...WorkflowConfig::setAside(
                        [ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_HAND, ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_ATTACHED, ConflictCard::STATE_DISCARD_PILE],
                        ConflictCard::STATE_REMOVED_FROM_GAME,
                        ConflictCard::STATE_UNDERNEATH,
                    ),
                    ...WorkflowConfig::bowAndReady(ConflictCard::STATE_IN_PLAY),
                    ...WorkflowConfig::bowAndReady(ConflictCard::STATE_ATTACHED),
                    ...WorkflowConfig::triggerAbility(ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_ATTACHED),
                ],
            ),
            'dynasty_card' => WorkflowConfig::cardWorkflow(
                DynastyCard::class,
                [DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_ATTACHED, DynastyCard::STATE_DISCARD_PILE, DynastyCard::STATE_REMOVED_FROM_GAME, DynastyCard::STATE_UNDERNEATH],
                [
                    WorkflowConfig::transition('draw_card', DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEDOWN, [
                        'log' => '{card} is put facedown into {to}',
                        'stack' => StackType::RegularProvince,
                        'orientation' => Orientation::FaceDown,
                    ]),
                    // a province filled faceup by an effect (FillProvince: Rally, A Season of War)
                    WorkflowConfig::transition('draw_card_faceup', DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEUP, [
                        'log' => '{card} is put faceup into {to}',
                        'stack' => StackType::RegularProvince,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('turn_faceup', DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, [
                        'log' => '{card} is turned faceup in {from}',
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('turn_facedown', DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_PROVINCE_FACEDOWN, [
                        'log' => '{card} is turned facedown in {from}',
                        'orientation' => Orientation::FaceDown,
                    ]),
                    // a character played (PlayCharacterFromProvince, PlayCharacter), from a province or from elsewhere, by
                    // the `player` of the context, whoever owns it
                    WorkflowConfig::transition('play', [DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_DISCARD_PILE, DynastyCard::STATE_REMOVED_FROM_GAME, DynastyCard::STATE_UNDERNEATH], DynastyCard::STATE_IN_PLAY, [
                        'log' => '{player} plays {card} from {from} into {to}',
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // a character played as an attachment (PlayCharacterAsAttachment: Voice of the Ancestors), from where
                    // a character is played: the character it is attached to is the `stack` of the context
                    WorkflowConfig::transition('play_as_attachment', [DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_DISCARD_PILE, DynastyCard::STATE_REMOVED_FROM_GAME, DynastyCard::STATE_UNDERNEATH], DynastyCard::STATE_ATTACHED, [
                        'log' => '{player} plays {card} from {from} as an attachment on {character}',
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // a character played as an attachment turned into a character again (DetachAsCharacter), in a new
                    // stack of its controller
                    WorkflowConfig::transition('detach', DynastyCard::STATE_ATTACHED, DynastyCard::STATE_IN_PLAY, [
                        'log' => '{card} is detached from {character} and becomes a character',
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                        'character' => true,
                    ]),
                    // a character put into play by an effect, without being played (PutCharacterIntoPlay)
                    WorkflowConfig::transition('put_into_play', [DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_DISCARD_PILE, DynastyCard::STATE_REMOVED_FROM_GAME, DynastyCard::STATE_UNDERNEATH], DynastyCard::STATE_IN_PLAY, [
                        'log' => '{card} is put into play from {from}',
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // a character whose control is taken (TakeControl): see the conflict cards
                    ...WorkflowConfig::inPlace('take_control', [DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_ATTACHED], [
                        'log' => '{controller} takes control of {card}',
                    ]),
                    // a character played as an attachment moved to another character (MoveAttachment): the target is the
                    // `stack` of the context
                    WorkflowConfig::transition('move_attachment', DynastyCard::STATE_ATTACHED, DynastyCard::STATE_ATTACHED, [
                        'log' => '{card} is moved from {from} to {to}',
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // a holding put into play in a province by an effect (PutHoldingIntoProvince), from the deck (Frontline
                    // Engineer), the discard pile or from out of play (Kaiu Shihobu): the province is the `stack` of the
                    // context
                    WorkflowConfig::transition('put_into_province', [DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_DISCARD_PILE, DynastyCard::STATE_REMOVED_FROM_GAME, DynastyCard::STATE_UNDERNEATH], DynastyCard::STATE_PROVINCE_FACEUP, [
                        'log' => '{card} is put from {from} into {to}',
                        'stack' => StackType::RegularProvince,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // a card put into a province by an effect (PutCardIntoProvince), from its deck, its discard pile,
                    // another province, play or out of play: the province is the `stack` of the context
                    WorkflowConfig::transition('move_to_province_faceup', [DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_ATTACHED, DynastyCard::STATE_DISCARD_PILE, DynastyCard::STATE_REMOVED_FROM_GAME, DynastyCard::STATE_UNDERNEATH], DynastyCard::STATE_PROVINCE_FACEUP, [
                        'log' => '{card} is put from {from} into {to}',
                        'stack' => StackType::RegularProvince,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('move_to_province_facedown', [DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_ATTACHED, DynastyCard::STATE_DISCARD_PILE, DynastyCard::STATE_REMOVED_FROM_GAME, DynastyCard::STATE_UNDERNEATH], DynastyCard::STATE_PROVINCE_FACEDOWN, [
                        'log' => '{card} is put facedown from {from} into {to}',
                        'stack' => StackType::RegularProvince,
                        'orientation' => Orientation::FaceDown,
                    ]),
                    // a card switched with a card of another province (SwitchProvinceCards), keeping its orientation: the
                    // province is the `stack` of the context
                    WorkflowConfig::transition('switch_province_faceup', DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_PROVINCE_FACEUP, [
                        'log' => '{card} is moved from {from} to {to}',
                        'stack' => StackType::RegularProvince,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('switch_province_facedown', DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEDOWN, [
                        'log' => '{card} is moved from {from} to {to}',
                        'stack' => StackType::RegularProvince,
                        'orientation' => Orientation::FaceDown,
                    ]),
                    // from a province: a card discarded from a broken province, or during the fate phase
                    WorkflowConfig::transition('discard_card', [DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_ATTACHED, DynastyCard::STATE_REMOVED_FROM_GAME, DynastyCard::STATE_UNDERNEATH], DynastyCard::STATE_DISCARD_PILE, [
                        'log' => '{card} is discarded from {from}',
                        'stack' => StackType::DynastyDiscardPile,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // from a province: a holding (App\Workflow\DynastyCard\SacrificeGuardListener)
                    WorkflowConfig::transition('sacrifice', [DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_ATTACHED], DynastyCard::STATE_DISCARD_PILE, [
                        'log' => '{card} is sacrificed',
                        'stack' => StackType::DynastyDiscardPile,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // a dynasty card that would enter a hand is put into the dynasty discard pile instead
                    WorkflowConfig::transition('return_to_hand', [DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_ATTACHED], DynastyCard::STATE_DISCARD_PILE, [
                        'log' => '{card} would return to hand and is put into {to}',
                        'stack' => StackType::DynastyDiscardPile,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // the discard pile forms a new deck when the deck runs out of cards (App\Service\DrawDeckService)
                    WorkflowConfig::transition('put_back', DynastyCard::STATE_DISCARD_PILE, DynastyCard::STATE_DRAW_DECK, [
                        'log' => '{card} is put from {from} into {to}',
                        'stack' => StackType::DynastyDrawDeck,
                        'orientation' => Orientation::FaceDown,
                    ]),
                    ...WorkflowConfig::putOnDeck(
                        [DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_ATTACHED, DynastyCard::STATE_DISCARD_PILE, DynastyCard::STATE_REMOVED_FROM_GAME, DynastyCard::STATE_UNDERNEATH],
                        DynastyCard::STATE_DRAW_DECK,
                        StackType::DynastyDrawDeck,
                    ),
                    ...WorkflowConfig::setAside(
                        [DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_ATTACHED, DynastyCard::STATE_DISCARD_PILE],
                        DynastyCard::STATE_REMOVED_FROM_GAME,
                        DynastyCard::STATE_UNDERNEATH,
                    ),
                    ...WorkflowConfig::bowAndReady(DynastyCard::STATE_IN_PLAY),
                    ...WorkflowConfig::bowAndReady(DynastyCard::STATE_ATTACHED),
                    // a faceup holding in a province is in play (App\Workflow\DynastyCard\TriggerAbilityGuardListener)
                    ...WorkflowConfig::triggerAbility(DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_ATTACHED, DynastyCard::STATE_PROVINCE_FACEUP),
                ],
            ),
            'province_card' => WorkflowConfig::cardWorkflow(
                ProvinceCard::class,
                [ProvinceCard::STATE_FACEDOWN, ProvinceCard::STATE_UNBROKEN, ProvinceCard::STATE_BROKEN],
                [
                    WorkflowConfig::transition('reveal', ProvinceCard::STATE_FACEDOWN, ProvinceCard::STATE_UNBROKEN, [
                        'log' => '{card} is revealed',
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('unreveal', ProvinceCard::STATE_UNBROKEN, ProvinceCard::STATE_FACEDOWN, [
                        'log' => '{card} is turned facedown',
                        'orientation' => Orientation::FaceDown,
                    ]),
                    WorkflowConfig::transition('break', ProvinceCard::STATE_UNBROKEN, ProvinceCard::STATE_BROKEN, [
                        'log' => '{card} is broken',
                        'orientation' => Orientation::UpsideDown,
                    ]),
                    WorkflowConfig::transition('restore', ProvinceCard::STATE_BROKEN, ProvinceCard::STATE_UNBROKEN, [
                        'log' => '{card} is restored',
                        'orientation' => Orientation::FaceUp,
                    ]),
                    ...WorkflowConfig::triggerAbility(ProvinceCard::STATE_UNBROKEN),
                ],
            ),
            'role_card' => WorkflowConfig::cardWorkflow(
                RoleCard::class,
                [RoleCard::STATE_IN_PLAY],
                [WorkflowConfig::transition('noop', RoleCard::STATE_IN_PLAY, RoleCard::STATE_IN_PLAY, [
                    'log' => '{card} is unchanged',
                ])],
            ),
            'stronghold_card' => WorkflowConfig::cardWorkflow(
                StrongholdCard::class,
                [StrongholdCard::STATE_IN_PLAY],
                [
                    ...WorkflowConfig::bowAndReady(StrongholdCard::STATE_IN_PLAY),
                    ...WorkflowConfig::triggerAbility(StrongholdCard::STATE_IN_PLAY),
                ],
            ),
        ],
    ],
]);
