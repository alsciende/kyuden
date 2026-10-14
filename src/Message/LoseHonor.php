<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The target player loses honor, which returns to the general token pool, e.g. to pay a cost or resolve an effect.
 */
#[AppAction]
final readonly class LoseHonor implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player who loses honor.')]
        private string $targetPlayerId,
        #[Description('Honor lost.')]
        #[Assert\PositiveOrZero]
        private int $honorLoss,
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

    /**
     * The player the effect applies to.
     */
    public function getTargetPlayerId(): string
    {
        return $this->targetPlayerId;
    }

    public function getHonorLoss(): int
    {
        return $this->honorLoss;
    }
}
