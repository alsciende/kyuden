<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * Discards a card chosen at random by the engine from the hand of a player (e.g. the earth ring effect).
 */
#[AppAction]
final readonly class DiscardRandomCardFromHand implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
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
