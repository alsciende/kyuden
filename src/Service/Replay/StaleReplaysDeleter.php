<?php

declare(strict_types=1);

namespace App\Service\Replay;

use App\Repository\GameRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Deletes the replays their user no longer reads: not read (nor changed) for STALE_AFTER seconds. The user starts
 * another one when they come back.
 */
final readonly class StaleReplaysDeleter
{
    public const int STALE_AFTER = 3600;

    public function __construct(
        private GameRepository $gameRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int the number of replays deleted
     */
    public function deleteAll(): int
    {
        $replayIds = $this->gameRepository->findStaleReplayIds($this->clock->now()->modify(sprintf('-%d seconds', self::STALE_AFTER)));
        foreach ($replayIds as $replayId) {
            $this->gameRepository->delete($replayId);
        }

        return count($replayIds);
    }
}
