<?php

declare(strict_types=1);

namespace App\Service\Deck;

use App\Entity\Deck;
use App\Exception\Api\DeckInQueueException;
use App\Repository\CandidateRepository;
use App\Repository\DeckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Deletes a deck of a user. A deck played in a game stays for its players, marked deleted: nobody can use it any more,
 * and importing it again restores it (DeckImporter). The caller flushes.
 */
readonly class DeckDeleter
{
    public function __construct(
        private DeckRepository $deckRepository,
        private CandidateRepository $candidateRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws DeckInQueueException
     */
    public function delete(Deck $deck): void
    {
        if ($this->candidateRepository->count(['deck' => $deck]) > 0) {
            throw new DeckInQueueException();
        }

        if ($this->deckRepository->isPlayed($deck)) {
            $deck->delete($this->clock->now());
        } else {
            $this->entityManager->remove($deck);
        }
    }
}
