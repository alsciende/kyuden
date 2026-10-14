<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Each player declares the total glory of the ready characters they control, with every modifier applied
 * (3.4.1): the engine adds 1 for each ring in their claimed ring pool.
 */
#[AppAction]
final readonly class DeclareGloryCount implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Total glory of the ready characters the player controls, with every modifier applied, without the claimed rings.')]
        #[Assert\PositiveOrZero]
        private int $glory,
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

    public function getGlory(): int
    {
        return $this->glory;
    }
}
