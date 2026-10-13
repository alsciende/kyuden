<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * Removes fate from a card in play and returns it to the general token pool (e.g. the void ring effect).
 *
 * The card can belong to any player: effects often target an opponent's card.
 */
#[AppAction]
final readonly class RemoveFate implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private string $cardId,
        private int $amount,
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

    public function getAmount(): int
    {
        return $this->amount;
    }
}
