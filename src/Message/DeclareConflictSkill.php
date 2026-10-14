<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The attacking or defending player declares the total skill of their side, matching the conflict type,
 * with every modifier applied (3.2.3): the engine does not apply card effects.
 */
#[AppAction]
final readonly class DeclareConflictSkill implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Total skill of the side of the player, with every modifier applied.')]
        #[Assert\PositiveOrZero]
        private int $skill,
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

    public function getSkill(): int
    {
        return $this->skill;
    }
}
