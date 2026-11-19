<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A reaction window open for the viewer: the triggering condition that opened it (its name, and the ids of what it
 * names, by param) and the cards of the viewer that can react to it. Shown to that player only: the cards may be in
 * their hand.
 */
final readonly class ReactionWindowView
{
    /**
     * @param array<string, string> $params
     * @param list<string>          $cardIds
     */
    public function __construct(
        public string $id,
        public string $trigger,
        public array $params,
        public array $cardIds,
    ) {
    }
}
