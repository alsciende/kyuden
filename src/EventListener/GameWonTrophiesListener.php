<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\GameWonEvent;
use App\Service\Trophy\GameTrophies;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Once a game is won, its players earn its trophies (GameTrophies), in the transaction of the message that ends it, so
 * that the end of the game shows them at once. A game ended without a winner (abandoned) earns nothing; a replay
 * neither. Not in the log of the game: what a player has earned is theirs.
 */
#[AsEventListener]
readonly class GameWonTrophiesListener
{
    public function __construct(
        private GameTrophies $gameTrophies,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GameWonEvent $event): void
    {
        $this->gameTrophies->award($event->getGame(), $this->clock->now());
    }
}
