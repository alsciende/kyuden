<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Turns a face-down dynasty card in a province face up, e.g. during the dynasty phase.
 */
#[AppAction]
class RevealDynastyCard implements PlayerActionInterface
{
    public function __construct(
        private readonly string $gameId,
        private readonly string $playerId,
        #[Description('Face-down dynasty card in a province.')]
        private readonly string $cardId,
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

    public function getCardId(): string
    {
        return $this->cardId;
    }
}
