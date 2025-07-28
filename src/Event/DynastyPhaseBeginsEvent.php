<?php

namespace App\Event;

class DynastyPhaseBeginsEvent implements FrameworkEventInterface
{
    public function __construct(
        private string $gameId,
    )
    {
    }

    public function getGameId(): string
    {
        return $this->gameId;
    }
}