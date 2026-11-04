<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Places honor tokens from the general token pool on a province or a holding face up in a province (Fortified Assembly,
 * Public Forum, Distinguished Dojo, Endless Archives). These are counters, not status tokens: the honor of the players
 * does not change. The tokens of a holding return to the general token pool when it leaves play.
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class PlaceHonorTokens implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Province, or holding face up in a province, the honor tokens are placed on.')]
        private string $cardId,
        #[Description('Honor tokens placed.')]
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
