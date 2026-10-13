<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * Workflows of the game and of each card type.
 *
 * Card workflows: the place of a card is computed from its stack and orientation (App\Workflow\CardMarkingStore);
 * the `stack` and `orientation` metadata of a transition say how it moves or turns the card
 * (applied by App\Workflow\CardTransitionListener). A transition that moves a card also gives its orientation.
 */

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\CardTypes\RoleCard;
use App\Entity\CardTypes\StrongholdCard;
use App\Entity\Game;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Workflow\WorkflowConfig;

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
                // `action_window`: players can trigger action abilities (TriggerActionAbility)
                // `opportunities`: players act in player order and can pass their opportunity to act (PassOpportunity)
                'places' => [
                    [
                        'name' => 'play_cards_from_provinces',
                        'metadata' => [
                            'action_window' => true,
                            'opportunities' => true,
                        ],
                    ],
                    [
                        // reactions to the honor dials being revealed, e.g. changing a bid (SetHonorBid)
                        'name' => 'reveal_honor_dials',
                        'metadata' => [
                            'opportunities' => true,
                        ],
                    ],
                ],
                // we are in a different place if the phase of the game is different AND the available player actions are different
                'transitions' => [
                    WorkflowConfig::transition('start_game', 'join_game', 'choose_stronghold', ['auto' => true]), // select first player
                    WorkflowConfig::transition('setup_game', 'choose_stronghold', 'dynasty_phase_begins', ['auto' => true]),
                    WorkflowConfig::transition('dynasty_phase_began', 'dynasty_phase_begins', 'reveal_facedown_dynasty_cards', ['auto' => true]),
                    WorkflowConfig::transition('dynasty_cards_revealed', 'reveal_facedown_dynasty_cards', 'collect_fate', ['auto' => true]),
                    WorkflowConfig::transition('fate_collected', 'collect_fate', 'play_cards_from_provinces', ['auto' => true]),
                    WorkflowConfig::transition('dynasty_actions_passed', 'play_cards_from_provinces', 'dynasty_phase_ends', ['auto' => true]),
                    WorkflowConfig::transition('dynasty_phase_ended', 'dynasty_phase_ends', 'draw_phase_begins', ['auto' => true]),
                    WorkflowConfig::transition('draw_phase_began', 'draw_phase_begins', 'honor_bid', ['auto' => true]),
                    WorkflowConfig::transition('honor_bids_confirmed', 'honor_bid', 'reveal_honor_dials', ['auto' => true]),
                    WorkflowConfig::transition('honor_dials_revealed', 'reveal_honor_dials', 'transfer_honor', ['auto' => true]),
                    WorkflowConfig::transition('honor_transferred', 'transfer_honor', 'draw_cards', ['auto' => true]),
                    WorkflowConfig::transition('cards_drawn', 'draw_cards', 'draw_phase_ends', ['auto' => true]),
                    WorkflowConfig::transition('t10', 'draw_phase_ends', 'conflict_phase_begins'),
                    WorkflowConfig::transition('t11', 'conflict_phase_begins', 'declare_conflict'),
                    WorkflowConfig::transition('t12', 'declare_conflict', 'declare_defenders'),
                    WorkflowConfig::transition('t13', 'declare_defenders', 'conflict_action_window'),
                    WorkflowConfig::transition('t14', 'conflict_action_window', 'determine_result'),
                    WorkflowConfig::transition('t15', 'determine_result', 'apply_unopposed'),
                    WorkflowConfig::transition('t16', 'apply_unopposed', 'break_province'),
                    WorkflowConfig::transition('t17', 'break_province', 'resolve_ring_effects'),
                    WorkflowConfig::transition('t18', 'resolve_ring_effects', 'claim_ring'),
                    WorkflowConfig::transition('t19', 'claim_ring', 'return_home'),
                    WorkflowConfig::transition('t20', 'return_home', 'conflict_ends'),
                    WorkflowConfig::transition('t21', 'conflict_ends', 'declare_conflict'),
                    WorkflowConfig::transition('t22', 'conflict_phase_begins', 'determine_imperial_favor'),
                    WorkflowConfig::transition('t23', 'conflict_ends', 'determine_imperial_favor'),
                    WorkflowConfig::transition('t24', 'determine_imperial_favor', 'glory_count'),
                    WorkflowConfig::transition('t25', 'glory_count', 'claim_imperial_favor'),
                    WorkflowConfig::transition('t26', 'claim_imperial_favor', 'conflict_phase_ends'),
                    WorkflowConfig::transition('t27', 'conflict_phase_ends', 'fate_phase_begins'),
                    WorkflowConfig::transition('t28', 'fate_phase_begins', 'discard_characters_with_no_fate'),
                    WorkflowConfig::transition('t29', 'discard_characters_with_no_fate', 'remove_fate_from_characters'),
                    WorkflowConfig::transition('t30', 'remove_fate_from_characters', 'place_fate_on_unclaimed_rings'),
                    WorkflowConfig::transition('t31', 'place_fate_on_unclaimed_rings', 'ready_cards'),
                    WorkflowConfig::transition('t32', 'ready_cards', 'discard_from_provinces'),
                    WorkflowConfig::transition('t33', 'discard_from_provinces', 'return_rings'),
                    WorkflowConfig::transition('t34', 'return_rings', 'pass_first_player_token'),
                    WorkflowConfig::transition('t35', 'pass_first_player_token', 'fate_phase_ends'),
                    WorkflowConfig::transition('t36', 'fate_phase_ends', 'dynasty_phase_begins'),
                ],
            ],
            'conflict_card' => WorkflowConfig::cardWorkflow(
                ConflictCard::class,
                [ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_HAND, ConflictCard::STATE_IN_PLAY, ConflictCard::STATE_DISCARD_PILE],
                [
                    WorkflowConfig::transition('draw_card', ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_HAND, [
                        'stack' => StackType::Hand,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('play_card', [ConflictCard::STATE_HAND, ConflictCard::STATE_DISCARD_PILE], ConflictCard::STATE_IN_PLAY, [
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('discard_card', [ConflictCard::STATE_DRAW_DECK, ConflictCard::STATE_HAND, ConflictCard::STATE_IN_PLAY], ConflictCard::STATE_DISCARD_PILE, [
                        'stack' => StackType::ConflictDiscardPile,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('put_back', [ConflictCard::STATE_HAND, ConflictCard::STATE_DISCARD_PILE], ConflictCard::STATE_DRAW_DECK, [
                        'stack' => StackType::ConflictDrawDeck,
                        'orientation' => Orientation::FaceDown,
                    ]),
                    WorkflowConfig::transition('take_back', ConflictCard::STATE_DISCARD_PILE, ConflictCard::STATE_HAND, [
                        'stack' => StackType::Hand,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    ...WorkflowConfig::bowAndReady(ConflictCard::STATE_IN_PLAY),
                    ...WorkflowConfig::triggerAction(ConflictCard::STATE_IN_PLAY),
                ],
            ),
            'dynasty_card' => WorkflowConfig::cardWorkflow(
                DynastyCard::class,
                [DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_DISCARD_PILE],
                [
                    WorkflowConfig::transition('draw_card', DynastyCard::STATE_DRAW_DECK, DynastyCard::STATE_PROVINCE_FACEDOWN, [
                        'stack' => StackType::RegularProvince,
                        'orientation' => Orientation::FaceDown,
                    ]),
                    WorkflowConfig::transition('turn_faceup', DynastyCard::STATE_PROVINCE_FACEDOWN, DynastyCard::STATE_PROVINCE_FACEUP, [
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('turn_facedown', DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_PROVINCE_FACEDOWN, [
                        'orientation' => Orientation::FaceDown,
                    ]),
                    WorkflowConfig::transition('play', DynastyCard::STATE_PROVINCE_FACEUP, DynastyCard::STATE_IN_PLAY, [
                        'stack' => StackType::InPlay,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('discard', DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_DISCARD_PILE, [
                        'stack' => StackType::DynastyDiscardPile,
                        'orientation' => Orientation::FaceUp,
                    ]),
                    ...WorkflowConfig::bowAndReady(DynastyCard::STATE_IN_PLAY),
                    // a faceup holding in a province is in play (App\Workflow\DynastyCard\TriggerActionGuardListener)
                    ...WorkflowConfig::triggerAction(DynastyCard::STATE_IN_PLAY, DynastyCard::STATE_PROVINCE_FACEUP),
                ],
            ),
            'province_card' => WorkflowConfig::cardWorkflow(
                ProvinceCard::class,
                [ProvinceCard::STATE_FACEDOWN, ProvinceCard::STATE_UNBROKEN, ProvinceCard::STATE_BROKEN],
                [
                    WorkflowConfig::transition('reveal', ProvinceCard::STATE_FACEDOWN, ProvinceCard::STATE_UNBROKEN, [
                        'orientation' => Orientation::FaceUp,
                    ]),
                    WorkflowConfig::transition('unreveal', ProvinceCard::STATE_UNBROKEN, ProvinceCard::STATE_FACEDOWN, [
                        'orientation' => Orientation::FaceDown,
                    ]),
                    WorkflowConfig::transition('break', ProvinceCard::STATE_UNBROKEN, ProvinceCard::STATE_BROKEN, [
                        'orientation' => Orientation::UpsideDown,
                    ]),
                    WorkflowConfig::transition('restore', ProvinceCard::STATE_BROKEN, ProvinceCard::STATE_UNBROKEN, [
                        'orientation' => Orientation::FaceUp,
                    ]),
                    ...WorkflowConfig::triggerAction(ProvinceCard::STATE_UNBROKEN),
                ],
            ),
            'role_card' => WorkflowConfig::cardWorkflow(
                RoleCard::class,
                [RoleCard::STATE_IN_PLAY],
                [WorkflowConfig::transition('noop', RoleCard::STATE_IN_PLAY, RoleCard::STATE_IN_PLAY)],
            ),
            'stronghold_card' => WorkflowConfig::cardWorkflow(
                StrongholdCard::class,
                [StrongholdCard::STATE_IN_PLAY],
                [
                    ...WorkflowConfig::bowAndReady(StrongholdCard::STATE_IN_PLAY),
                    ...WorkflowConfig::triggerAction(StrongholdCard::STATE_IN_PLAY),
                ],
            ),
        ],
    ],
]);
