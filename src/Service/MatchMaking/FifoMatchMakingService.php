<?php

declare(strict_types=1);

namespace App\Service\MatchMaking;

use App\Repository\CandidateRepository;
use App\Service\GameLauncher\GameLauncherInterface;

/**
 * Pairs the candidates in their order of arrival. The queue is locked while the games are created, so that two
 * concurrent runs never put a candidate in two games.
 */
readonly class FifoMatchMakingService implements MatchMakingInterface
{
    public function __construct(
        private CandidateRepository $candidateRepository,
        private GameLauncherInterface $launcher,
    ) {
    }

    #[\Override]
    public function findMatches(): void
    {
        $this->candidateRepository->withQueueLock(function (): void {
            $candidates = $this->candidateRepository->findBy([], ['id' => 'ASC']);
            $pairs = array_chunk($candidates, 2);

            while (($pair = array_shift($pairs)) && (count($pair) === 2)) {
                $this->launcher->createGame($pair);
            }
        });
    }
}
