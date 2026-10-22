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
    WorkflowConfig::transition('setup_game', 'choose_stronghold', 'dynasty_phase_begins', ['auto' => true, 'log' => 'The players set up the game']),
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
    WorkflowConfig::transition('conflict_declared', 'declare_conflict', 'react_to_conflict_declaration', ['log' => 'The players may react to the conflict declaration']),
    WorkflowConfig::transition('declaration_reactions_passed', 'react_to_conflict_declaration', 'declare_defenders', ['auto' => true, 'log' => 'The reactions to the conflict declaration are over']),
    WorkflowConfig::transition('defenders_declared', 'declare_defenders', 'conflict_action_window', ['log' => 'The action window of the conflict opens']),
    WorkflowConfig::transition('conflict_actions_passed', 'conflict_action_window', 'determine_result', ['auto' => true, 'log' => 'The action window of the conflict closes']),
    WorkflowConfig::transition('result_determined', 'determine_result', 'react_to_conflict_result', ['auto' => true, 'log' => 'The result of the conflict is determined']),
    WorkflowConfig::transition('result_reactions_passed', 'react_to_conflict_result', 'apply_unopposed', ['auto' => true, 'log' => 'The reactions to the conflict result are over']),
    WorkflowConfig::transition('unopposed_applied', 'apply_unopposed', 'break_province', ['auto' => true, 'log' => 'The conflict is checked for being unopposed']),
    WorkflowConfig::transition('provinces_broken', 'break_province', 'react_to_broken_province', ['auto' => true, 'log' => 'The attacked provinces are checked for breaking']),
    WorkflowConfig::transition('break_reactions_passed', 'react_to_broken_province', 'resolve_ring_effects', ['auto' => true, 'log' => 'The reactions to the broken province are over']),
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
                [ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_HAND, ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_DISCARD_PILE],
                [
                    WorkflowConfig::transition('draw_card', ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_HAND, [
                        'log' => '{owner} draws {card}',
                        'stack' => StackType::Hand,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('play_card', [ConflictCard::STATE_HAND, ConflictCard::STATE_DISCARD_PILE], ConflictCard::STATE_IN_PLAY, [
                        'log' => '{owner} plays {card} from {from} into {to}',
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('discard_card', [ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_HAND, ConflictCard::STATE_IN_PLAY], ConflictCard::STATE_DISCARD_PILE, [
                        'log' => '{card} is discarded from {from}',
                        'stack' => StackType::ConflictDiscardPile,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('put_back', [ConflictCard::STATE_HAND, ConflictCard::STATE_DISCARD_PILE], ConflictCard::STATE_DRAW_DECK, [
                        'log' => '{card} is put from {from} into {to}',
                        'stack' => StackType::ConflictDrawDeck,
                        'orientation' => Orientation::FaceDown,
                    ]),
                    WorkflowConfig::transition('take_back', ConflictCard::STATE_DISCARD_PILE, ConflictCard::STATE_HAND, [
                        'log' => '{owner} takes {card} back from {from} into {to}',
                        'stack' => StackType::Hand,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    ...WorkflowConfig::bowAndReady(ConflictCard::STATE_IN_PLAY),
                    ...WorkflowConfig::triggerAbility(ConflictCard::STATE_IN_PLAY),
                ],
            ),
            'dynasty_card' => WorkflowConfig::cardWorkflow(
                DynastyCard::class,
                [DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_DISCARD_PILE],
                [
                    WorkflowConfig::transition('draw_card', DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEDOWN, [
                        'log' => '{card} is put facedown into {to}',
                        'stack' => StackType::RegularProvince,
                        'orientation' => Orientation::FaceDown,
                    ]),
                    WorkflowConfig::transition('turn_faceup', DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, [
                        'log' => '{card} is turned faceup in {from}',
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('turn_facedown', DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_PROVINCE_FACEDOWN, [
                        'log' => '{card} is turned facedown in {from}',
                        'orientation' => Orientation::FaceDown,
                    ]),
                    WorkflowConfig::transition('play', DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY, [
                        'log' => '{owner} plays {card} from {from} into {to}',
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // from a province: a card discarded from a broken province, or during the fate phase
                    WorkflowConfig::transition('discard_card', [DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY], DynastyCard::STATE_DISCARD_PILE, [
                        'log' => '{card} is discarded from {from}',
                        'stack' => StackType::DynastyDiscardPile,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    // the discard pile forms a new deck when the deck runs out of cards (App\Service\DrawDeckService)
                    WorkflowConfig::transition('put_back', DynastyCard::STATE_DISCARD_PILE, DynastyCard::STATE_DRAW_DECK, [
                        'log' => '{card} is put from {from} into {to}',
                        'stack' => StackType::DynastyDrawDeck,
                        'orientation' => Orientation::FaceDown,
                    ]),
                    ...WorkflowConfig::bowAndReady(DynastyCard::STATE_IN_PLAY),
                    // a faceup holding in a province is in play (App\Workflow\DynastyCard\TriggerAbilityGuardListener)
                    ...WorkflowConfig::triggerAbility(DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_PROVINCE_FACEUP),
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
