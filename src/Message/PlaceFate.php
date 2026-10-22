<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Places fate from the general token pool on a character in play (e.g. "place 1 fate on that character").
 *
 * The character can belong to any player: effects often target an opponent's character. Fate taken from a fate pool
 * is not placed this way: the fate spent from the pool is not moved yet.
 */
#[AppAction]
final readonly class PlaceFate implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Character in play the fate is placed on.')]
        private string $cardId,
        #[Description('Fate placed.')]
        #[Assert\Positive]
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
