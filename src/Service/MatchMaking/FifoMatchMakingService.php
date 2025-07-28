<?php

declare(strict_types=1);

namespace App\Service\MatchMaking;

use App\Repository\CandidateRepository;
use App\Service\GameLauncher\GameLauncherInterface;

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
        $candidates = $this->candidateRepository->findAll();
        $pairs = array_chunk($candidates, 2);

        while (($pair = array_shift($pairs)) && (count($pair) === 2)) {
            $this->launcher->createGame($pair);
        }
    }
}
