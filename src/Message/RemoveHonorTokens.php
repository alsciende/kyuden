<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Removes honor tokens from a province or a holding face up in a province, to the general token pool (spent by an
 * ability, or placed by mistake). The honor of the players does not change.
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class RemoveHonorTokens implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Province, or holding face up in a province, holding the honor tokens.')]
        private string $cardId,
        #[Description('Honor tokens removed, at most those on the card.')]
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
