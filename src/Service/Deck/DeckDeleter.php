<?php

declare(strict_types=1);

namespace App\Service\Deck;

use App\Entity\Deck;
use App\Exception\Api\DeckInQueueException;
use App\Exception\Api\DeckInTournamentException;
use App\Repository\CandidateRepository;
use App\Repository\DeckRepository;
use App\Repository\TournamentEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Deletes a deck of a user. A deck played in a game or registered in a tournament stays for its players, marked
 * deleted: nobody can use it any more, and importing it again restores it (DeckImporter). A deck waiting in the queue
 * or registered in a tournament still to be played is not deleted. The caller flushes.
 */
readonly class DeckDeleter
{
    public function __construct(
        private DeckRepository $deckRepository,
        private CandidateRepository $candidateRepository,
        private TournamentEntryRepository $tournamentEntryRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws DeckInQueueException
     * @throws DeckInTournamentException
     */
    public function delete(Deck $deck): void
    {
        if ($this->candidateRepository->count(['deck' => $deck]) > 0) {
            throw new DeckInQueueException();
        }
        if ($this->tournamentEntryRepository->isDeckInUpcomingTournament($deck)) {
            throw new DeckInTournamentException();
        }

        if ($this->deckRepository->isPlayed($deck) || $this->tournamentEntryRepository->isDeckRegistered($deck)) {
            $deck->delete($this->clock->now());
        } else {
            $this->entityManager->remove($deck);
        }
    }
}
