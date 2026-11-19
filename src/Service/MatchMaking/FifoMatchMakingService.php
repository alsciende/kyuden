<?php

declare(strict_types=1);

namespace App\Service\MatchMaking;

use App\Entity\Candidate;
use App\Entity\Invitation;
use App\Entity\TournamentMatch;
use App\Repository\CandidateRepository;
use App\Service\GameLauncher\GameLauncherInterface;

/**
 * Pairs the candidates in their order of arrival. The candidates of an invitation (its host and its guest) are only
 * paired together, as soon as both are in the queue, whoever came first; so are the players of a match of a tournament.
 * The queue is locked while the games are
 * created, so that two concurrent runs never put a candidate in two games.
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
            $open = [];
            /** @var array<string, list<Candidate>> $invited */
            $invited = [];
            /** @var array<int, list<Candidate>> $matched */
            $matched = [];
            foreach ($this->candidateRepository->findBy([], ['id' => 'ASC']) as $candidate) {
                $invitation = $candidate->getInvitation();
                $match = $candidate->getMatch();
                if ($match instanceof TournamentMatch) {
                    $matched[(int) $match->getId()][] = $candidate;
                } elseif ($invitation instanceof Invitation) {
                    $invited[$invitation->getId()][] = $candidate;
                } else {
                    $open[] = $candidate;
                }
            }

            foreach ($invited as $pair) {
                if (count($pair) === 2) {
                    $this->launcher->createGame($pair, $pair[0]->getInvitation());
                }
            }

            foreach ($matched as $pair) {
                if (count($pair) === 2) {
                    $this->launcher->createGame($pair, match: $pair[0]->getMatch());
                }
            }

            $pairs = array_chunk($open, 2);
            while (($pair = array_shift($pairs)) && (count($pair) === 2)) {
                $this->launcher->createGame($pair);
            }
        });
    }
}
