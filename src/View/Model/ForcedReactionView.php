<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A forced reaction a player has to trigger (ForcedReactionsResolver), shown to every reader: the cards with one are
 * visible to every player. The web client moves the game on for nobody while one is pending.
 */
final readonly class ForcedReactionView
{
    /**
     * @param array<string, string> $params what the triggering condition names, by param (ids)
     */
    public function __construct(
        public string $cardId,
        /**
         * The reaction window offering it to its controller.
         */
        public string $windowId,
        public string $trigger,
        public array $params,
    ) {
    }
}
