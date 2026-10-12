<?php

declare(strict_types=1);

namespace App\Tests\Builder;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;

/**
 * Result of GameBuilder: the game and its players and cards, retrievable by alias.
 */
final readonly class Scenario
{
    /**
     * @param array<string, Player>       $players
     * @param array<string, PhysicalCard> $cards
     */
    public function __construct(
        private Game $game,
        private array $players,
        private array $cards,
    ) {
    }

    public function game(): Game
    {
        return $this->game;
    }

    public function player(string $alias): Player
    {
        return $this->players[$alias] ?? throw new \OutOfBoundsException("No player aliased '{$alias}' in scenario.");
    }

    public function card(string $alias): PhysicalCard
    {
        return $this->cards[$alias] ?? throw new \OutOfBoundsException("No card aliased '{$alias}' in scenario.");
    }
}
