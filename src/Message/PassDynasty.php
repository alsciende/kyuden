<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The active player passes during the play cards from provinces step: they cannot act anymore until the end of the phase.
 */
#[AppAction]
final readonly class PassDynasty implements PlayerActionInterface
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
