<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\PhysicalCard;
use App\Enum\Orientation;
use App\Enum\StackType;

/**
 * Helpers to write the workflow definitions of config/packages/workflows.php.
 *
 * @phpstan-type TransitionConfig array{name: string, from: list<array{place: string}>, to: list<array{place: string}>, metadata: array<string, mixed>}
 */
final class WorkflowConfig
{
    /**
     * @param string|list<string>  $from
     * @param array<string, mixed> $metadata
     *
     * @return TransitionConfig
     */
    public static function transition(string $name, string|array $from, string $to, array $metadata = []): array
    {
        $fromPlaces = [];
        foreach ((array) $from as $place) {
            $fromPlaces[] = [
                'place' => $place,
            ];
        }

        return [
            'name' => $name,
            'from' => $fromPlaces,
            'to' => [[
                'place' => $to,
            ]],
            'metadata' => $metadata,
        ];
    }

    /**
     * Every place the transitions start from or lead to, in order of appearance.
     *
     * @param list<TransitionConfig> $transitions
     *
     * @return list<string>
     */
    public static function placesOf(array $transitions, string ...$except): array
    {
        $places = [];
        foreach ($transitions as $transition) {
            foreach ([...$transition['from'], ...$transition['to']] as $place) {
                $places[] = $place['place'];
            }
        }

        return array_values(array_diff(array_unique($places), $except));
    }

    /**
     * Card workflows share their marking store: the place of a card is computed from its stack and orientation.
     * Transition metadata (`stack`, `orientation`) is applied by CardTransitionListener.
     *
     * @param class-string<PhysicalCard> $class
     * @param non-empty-list<string>     $places      the first one is the initial marking
     * @param list<TransitionConfig>     $transitions
     *
     * @return array{type: 'state_machine', audit_trail: array{enabled: true}, marking_store: array{service: string}, supports: list<string>, initial_marking: string, places: list<array{name: string}>, transitions: list<TransitionConfig>}
     */
    public static function cardWorkflow(string $class, array $places, array $transitions): array
    {
        $placeConfigs = [];
        foreach ($places as $place) {
            $placeConfigs[] = [
                'name' => $place,
            ];
        }

        return [
            'type' => 'state_machine',
            'audit_trail' => [
                'enabled' => true,
            ],
            'marking_store' => [
                'service' => CardMarkingStore::class,
            ],
            'supports' => [$class],
            'initial_marking' => $places[0],
            'places' => $placeConfigs,
            'transitions' => $transitions,
        ];
    }

    /**
     * A bowed card has the same transitions as a ready one: bowing and readying stay in the same place.
     *
     * @return list<TransitionConfig>
     */
    public static function bowAndReady(string $place): array
    {
        return [
            self::transition('bow', $place, $place, [
                'log' => '{card} is bowed',
                'orientation' => Orientation::Sideways,
            ]),
            self::transition('ready', $place, $place, [
                'log' => '{card} is readied',
                'orientation' => Orientation::FaceUp,
            ]),
        ];
    }

    /**
     * A card put into its deck, face down, from the places given (the deck too, to move a card within it): on top of it
     * (`put_on_top`), at its bottom (`put_at_bottom`, metadata `position`: `bottom`, read by CardTransitionListener), or
     * shuffled into it (`shuffle_into_deck`: the deck is shuffled afterwards by its handler).
     *
     * @param list<string> $from
     *
     * @return list<TransitionConfig>
     */
    public static function putOnDeck(array $from, string $deck, StackType $deckType): array
    {
        $move = ['stack' => $deckType, 'orientation' => Orientation::FaceDown];

        return [
            self::transition('put_on_top', $from, $deck, ['log' => '{card} is put from {from} on top of {to}', ...$move]),
            self::transition('put_at_bottom', $from, $deck, ['log' => '{card} is put from {from} at the bottom of {to}', 'position' => 'bottom', ...$move]),
            self::transition('shuffle_into_deck', $from, $deck, ['log' => '{card} is shuffled from {from} into {to}', ...$move]),
        ];
    }

    /**
     * Places from which the abilities of a card can be used (TriggerActionAbility, TriggerReactionAbility): the card
     * stays where it is.
     *
     * @return list<TransitionConfig>
     */
    public static function triggerAbility(string ...$places): array
    {
        $transitions = [];
        foreach ($places as $place) {
            $transitions[] = self::transition('trigger_ability', $place, $place, [
                'log' => 'An ability of {card} is triggered',
            ]);
        }

        return $transitions;
    }
}
