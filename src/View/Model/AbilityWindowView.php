<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\AbilityTiming;

/**
 * A window open for the viewer, of an interrupt or a reaction (its timing): the triggering condition that opened it
 * (its name, and the ids of what it names, by param) and the cards of the viewer that can answer it. Shown to that
 * player only: the cards may be in their hand.
 */
final readonly class AbilityWindowView
{
    /**
     * @param array<string, string> $params
     * @param list<string>          $cardIds
     * @param list<string>          $forcedCardIds those of the cards whose ability is forced: they cannot be declined
     */
    public function __construct(
        public string $id,
        public AbilityTiming $timing,
        public string $trigger,
        public array $params,
        public array $cardIds,
        public array $forcedCardIds = [],
    ) {
    }
}
