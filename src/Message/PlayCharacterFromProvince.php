<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The active player plays a character from one of their provinces during the play cards from provinces step.
 *
 * The player says what they pay, since card effects may modify costs: `cardCost` for the card, `fateCost` for the
 * additional fate, and `fate` is the number of fate placed on the character.
 * Refilling the province and ending the opportunity to act are separate actions of the player.
 */
#[AppAction]
final readonly class PlayCharacterFromProvince implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private string $cardId,
        private int $cardCost,
        private int $fateCost,
        private int $fate,
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

    public function getCardCost(): int
    {
        return $this->cardCost;
    }

    public function getFateCost(): int
    {
        return $this->fateCost;
    }

    public function getFate(): int
    {
        return $this->fate;
    }
}
