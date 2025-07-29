<?php

declare(strict_types=1);

namespace App\Event;

class DynastyPhaseBeginsEvent implements FrameworkEventInterface
{
    public function __construct(
        private readonly string $gameId,
    ) {
    }

    public function getGameId(): string
    {
        return $this->gameId;
    }
}
