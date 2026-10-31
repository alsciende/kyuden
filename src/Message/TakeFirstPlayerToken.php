<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Gives the first player token to a player, e.g. to resolve an effect (Way of the Unicorn: keeping the token when it
 * would be passed).
 */
#[AppAction]
final readonly class TakeFirstPlayerToken implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player who takes the first player token.')]
        private string $targetPlayerId,
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

    public function getTargetPlayerId(): string
    {
        return $this->targetPlayerId;
    }
}
