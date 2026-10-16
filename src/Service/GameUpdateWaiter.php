<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Blocks until a game has changed since a version, for a bounded time (long-poll): the version is read from the
 * database at a fixed interval, without locking the game nor holding a transaction.
 */
final readonly class GameUpdateWaiter
{
    /**
     * Seconds between two reads of the version.
     */
    public const float POLL_INTERVAL = 0.25;

    public function __construct(
        private GameRepository $gameRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Returns once the version of the game is greater than $since, or once $timeout seconds have passed; at once when
     * the game has ended, since it will not change anymore. The game is refreshed when it has changed.
     */
    public function wait(Game $game, int $since, float $timeout): void
    {
        $deadline = (float) $this->clock->now()->format('U.u') + $timeout;
        $version = $game->getVersion();

        while ($version <= $since && $game->getCurrentPlace() !== Game::STATE_ENDED) {
            $remaining = $deadline - (float) $this->clock->now()->format('U.u');
            if ($remaining <= 0) {
                return;
            }

            $this->clock->sleep(min(self::POLL_INTERVAL, $remaining));
            $version = $this->gameRepository->findVersion($game->getId()) ?? $version;
            if ($version !== $game->getVersion()) {
                $this->entityManager->refresh($game);
            }
        }
    }
}
