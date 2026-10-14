<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The target player gains honor from the general token pool, e.g. to resolve an effect.
 */
#[AppAction]
final readonly class GainHonor implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player who gains honor.')]
        private string $targetPlayerId,
        #[Description('Honor gained.')]
        #[Assert\PositiveOrZero]
        private int $honorGain,
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

    public function getHonorGain(): int
    {
        return $this->honorGain;
    }
}
