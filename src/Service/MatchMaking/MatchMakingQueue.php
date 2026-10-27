<?php

declare(strict_types=1);

namespace App\Service\MatchMaking;

use App\Entity\Candidate;
use App\Entity\Deck;
use App\Entity\Invitation;
use App\Entity\User;
use App\Exception\Api\AlreadyQueuedException;
use App\Exception\Api\InvalidInvitationException;
use App\Exception\Api\NotQueuedException;
use App\Exception\Data\DeckNotFoundException;
use App\Repository\CandidateRepository;
use App\Repository\DeckRepository;
use App\Security\CurrentAccessToken;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * The matchmaking queue, shared by the HTTP API (`/api/queue`) and the MCP tools: a user joins it with one of the decks
 * they can use and is matched with the next candidate at once (MatchMakingInterface). A user waits in the queue once;
 * once matched, they are no longer in it.
 *
 * The host of an invitation and its guest join it with the invitation, to be matched together only; the bot of an
 * invitation always joins with it (the invitation its key is limited to). The host leaving the queue cancels their
 * invitation.
 */
final readonly class MatchMakingQueue
{
    public function __construct(
        private CandidateRepository $candidateRepository,
        private DeckRepository $deckRepository,
        private MatchMakingInterface $matchMaking,
        private EntityManagerInterface $entityManager,
        private CurrentAccessToken $currentAccessToken,
        private ClockInterface $clock,
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
     * @throws DeckNotFoundException       the deck does not exist or belongs to another user
     * @throws AlreadyQueuedException
     * @throws InvalidInvitationException the invitation is not pending, is not for this user, or its guest waits already
     */
    public function join(User $user, string $deckId, ?Invitation $invitation = null): void
    {
        $deck = $this->deckRepository->findUsable($deckId, $user);
        if (! $deck instanceof Deck) {
            throw new DeckNotFoundException($deckId);
        }

        $invitation = $this->invitationOf($user, $invitation);

        if ($this->candidateRepository->count(['user' => $user]) > 0) {
            throw new AlreadyQueuedException();
        }

        $candidate = new Candidate();
        $candidate->setUser($user);
        $candidate->setDeck($deck);
        $candidate->setInvitation($invitation);
        $this->candidateRepository->withQueueLock(function () use ($candidate, $invitation, $user): void {
            // an open invitation: the first guest takes the seat
            if ($invitation instanceof Invitation && $invitation->getHost() !== $user && $this->hasGuestWaiting($invitation)) {
                throw new InvalidInvitationException('Someone has already taken the seat of this invitation.');
            }

            $this->entityManager->persist($candidate);
            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                // joined concurrently
                throw new AlreadyQueuedException();
            }
        });

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

        $this->candidateRepository->withQueueLock(function () use ($candidate, $user): void {
            $this->entityManager->remove($candidate);
            // the host cancels their invitation
            $invitation = $candidate->getInvitation();
            if ($invitation instanceof Invitation && $invitation->getHost() === $user) {
                $this->entityManager->remove($invitation);
            }
            $this->entityManager->flush();
        });
    }

    private function hasGuestWaiting(Invitation $invitation): bool
    {
        return array_any(
            $this->candidateRepository->findBy(['invitation' => $invitation]),
            fn (Candidate $candidate): bool => $candidate->getUser() !== $invitation->getHost(),
        );
    }

    /**
     * The invitation the user joins with: the one their key is limited to, else the one they give.
     *
     * @throws InvalidInvitationException
     */
    private function invitationOf(User $user, ?Invitation $invitation): ?Invitation
    {
        $limitedTo = $this->currentAccessToken->get()?->getInvitation();
        if ($limitedTo instanceof Invitation) {
            if ($invitation instanceof Invitation && $invitation !== $limitedTo) {
                throw new InvalidInvitationException('This key is limited to its invitation.');
            }
            $invitation = $limitedTo;
        }

        if (! $invitation instanceof Invitation) {
            return null;
        }

        if ($invitation->isUsed()) {
            throw new InvalidInvitationException('This invitation has been used.');
        }
        if ($invitation->isExpired($this->clock->now())) {
            throw new InvalidInvitationException('This invitation has expired.');
        }
        if ($invitation->getHost() !== $user && ! $invitation->accepts($user)) {
            throw new InvalidInvitationException('This invitation is not for this user.');
        }

        return $invitation;
    }
}
