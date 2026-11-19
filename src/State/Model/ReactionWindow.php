<?php

declare(strict_types=1);

namespace App\State\Model;

/**
 * A reaction window opened by a triggering condition (TriggeringEventInterface), as one player sees it: the cards of
 * that player that can react to it (ReactionOfferer), until they trigger them, decline them or pass. It offers, it
 * blocks nothing: the engine accepts the reactions anywhere, and does not wait for the players to answer.
 */
class ReactionWindow
{
    /**
     * @param string                $id      shared by the windows of every player for the same triggering condition
     * @param string                $trigger the name of the triggering condition (`province_revealed`)
     * @param array<string, string> $params  what the triggering condition names, by param (ids)
     * @param list<string>          $cardIds the cards of the player that can react to it
     */
    public function __construct(
        public string $id,
        public string $trigger,
        public array $params,
        public array $cardIds,
    ) {
    }
}
