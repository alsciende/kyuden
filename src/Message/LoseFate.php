<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The target player loses fate from their fate pool, which returns to the general token pool, e.g. to pay a cost or
 * resolve an effect. Rejected when their fate pool does not hold that much fate.
 */
#[AppAction]
final readonly class LoseFate implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player who loses fate.')]
        private string $targetPlayerId,
        #[Description('Fate lost.')]
        #[Assert\PositiveOrZero]
        private int $fateLoss,
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

    public function getFateLoss(): int
    {
        return $this->fateLoss;
    }
}
