<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Dishonors a character in play (e.g. the fire ring effect): an honored character becomes ordinary, an ordinary one
 * dishonored.
 *
 * The character can belong to any player: effects often target an opponent's character.
 */
#[AppAction]
final readonly class DishonorCharacter implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Character in play to dishonor.')]
        private string $cardId,
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
