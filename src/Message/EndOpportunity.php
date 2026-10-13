<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The active player is done with their opportunity to act (e.g. after playing a character and resolving what follows):
 * the next player who has not passed gets the opportunity to act.
 */
#[AppAction]
final readonly class EndOpportunity implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
    ) {
    }

    #[\Override]
    public function getGameId(): string
    {
        return $this->gameId;
    }

    #[\Override]
    public function getPlayerId(): string
    {
        return $this->playerId;
    }
}
