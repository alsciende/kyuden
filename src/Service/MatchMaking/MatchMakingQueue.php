<?php

declare(strict_types=1);

namespace App\Service\MatchMaking;

use App\Entity\Candidate;
use App\Entity\Deck;
use App\Entity\User;
use App\Exception\Api\AlreadyQueuedException;
use App\Exception\Api\NotQueuedException;
use App\Exception\Data\DeckNotFoundException;
use App\Repository\CandidateRepository;
use App\Repository\DeckRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The matchmaking queue, shared by the HTTP API (`/api/queue`) and the MCP tools: a user joins it with one of the decks
 * they can use and is matched with the next candidate at once (MatchMakingInterface). A user waits in the queue once;
 * once matched, they are no longer in it.
 */
final readonly class MatchMakingQueue
{
    public function __construct(
        private CandidateRepository $candidateRepository,
        private DeckRepository $deckRepository,
        private MatchMakingInterface $matchMaking,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * The id of the deck the user waits with, null when they are not in the queue.
     */
    public function findDeckId(User $user): ?string
    {
        return $this->candidateRepository->findOneBy(['user' => $user])?->getDeck()->getId();
    }

    /**
     * @throws DeckNotFoundException  the deck does not exist or belongs to another user
     * @throws AlreadyQueuedException
     */
    public function join(User $user, string $deckId): void
    {
        $deck = $this->deckRepository->findUsable($deckId, $user);
        if (! $deck instanceof Deck) {
            throw new DeckNotFoundException($deckId);
        }

        if ($this->candidateRepository->count(['user' => $user]) > 0) {
            throw new AlreadyQueuedException();
        }

        $candidate = new Candidate();
        $candidate->setUser($user);
        $candidate->setDeck($deck);
        $this->entityManager->persist($candidate);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // joined concurrently
            throw new AlreadyQueuedException();
        }

        $this->matchMaking->findMatches();
    }

    /**
     * @throws NotQueuedException
     */
    public function leave(User $user): void
    {
        $candidate = $this->candidateRepository->findOneBy(['user' => $user]);
        if (! $candidate instanceof Candidate) {
            throw new NotQueuedException();
        }

        $this->candidateRepository->withQueueLock(function () use ($candidate): void {
            $this->entityManager->remove($candidate);
            $this->entityManager->flush();
        });
    }
}
