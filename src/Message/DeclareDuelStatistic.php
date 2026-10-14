<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A player involved in the duel declares the dueling statistic of their side (D.6), matching the duel type, with every
 * modifier applied: the engine does not apply card effects. The honor bid is added by the engine (D.7).
 */
#[AppAction]
final readonly class DeclareDuelStatistic implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Dueling statistic of the side of the player, with every modifier applied, without the honor bid.')]
        #[Assert\PositiveOrZero]
        private int $statistic,
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

    public function getStatistic(): int
    {
        return $this->statistic;
    }
}
