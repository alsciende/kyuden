<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Repository\GameRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Blocks until a user plays a game in progress, for a bounded time (long-poll), e.g. once the queue has matched them:
 * their games are read from the database at a fixed interval, without holding a transaction.
 */
final readonly class GameStartWaiter
{
    /**
     * Seconds between two reads of the games.
     */
    public const float POLL_INTERVAL = 1.0;

    public function __construct(
        private GameRepository $gameRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The games in progress of the user, the last joined first: at once when there are some, else once a game has
     * started or $timeout seconds have passed (none then).
     *
     * @return list<Game>
     */
    public function wait(string $userId, float $timeout): array
    {
        $deadline = (float) $this->clock->now()->format('U.u') + $timeout;

        while (true) {
            $games = array_values(array_filter(
                $this->gameRepository->findByUser($userId),
                fn (Game $game): bool => $game->getCurrentPlace() !== Game::STATE_ENDED,
            ));
            $remaining = $deadline - (float) $this->clock->now()->format('U.u');
            if ($games !== [] || $remaining <= 0) {
                return $games;
            }

            $this->clock->sleep(min(self::POLL_INTERVAL, $remaining));
        }
    }
}
