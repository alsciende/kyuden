<?php

declare(strict_types=1);

namespace App\Service\MatchMaking;

use App\Entity\Candidate;
use App\Entity\Deck;
use App\Entity\Invitation;
use App\Entity\TournamentEntry;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Exception\Api\AlreadyQueuedException;
use App\Exception\Api\GameInProgressException;
use App\Exception\Api\InvalidInvitationException;
use App\Exception\Api\InvalidTournamentMatchException;
use App\Exception\Api\NotQueuedException;
use App\Exception\Data\DeckNotFoundException;
use App\Repository\CandidateRepository;
use App\Repository\DeckRepository;
use App\Repository\GameRepository;
use App\Repository\TournamentMatchRepository;
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
 * invitation. A user who plays a game that has not ended finishes it first.
 *
 * The players of a match of a tournament join it with their match (joinMatch()), with the deck of their registration,
 * to be matched together only, until 10 minutes after their round has begun; joining with a match replaces any other
 * wait in the queue.
 */
final readonly class MatchMakingQueue
{
    public function __construct(
        private CandidateRepository $candidateRepository,
        private DeckRepository $deckRepository,
        private GameRepository $gameRepository,
        private TournamentMatchRepository $tournamentMatchRepository,
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
     * @throws GameInProgressException    the user plays a game that has not ended
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
        if ($this->gameRepository->hasInProgress($user->getId())) {
            throw new GameInProgressException();
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
     * Joins the queue with a match of a tournament, the deck of the registration of the user, replacing any other wait
     * in the queue.
     *
     * @throws InvalidTournamentMatchException
     * @throws AlreadyQueuedException          waiting with this match already
     * @throws GameInProgressException         the user plays a game that has not ended
     */
    public function joinMatch(User $user, int $matchId): void
    {
        $match = $this->tournamentMatchRepository->find($matchId);
        $entry = $match?->findEntryOf($user);
        if (! $match instanceof TournamentMatch || ! $entry instanceof TournamentEntry) {
            throw new InvalidTournamentMatchException('No match of a tournament of this user has this id.');
        }
        $tournament = $match->getTournament();
        if ($match->getRound() !== $tournament->getCurrentRound() || $match->hasResult() || $match->getGame() !== null || $match->isBye()) {
            throw new InvalidTournamentMatchException('This match is not to be played now.');
        }
        $deadline = $tournament->getJoinDeadline();
        if ($deadline === null || $this->clock->now() >= $deadline) {
            throw new InvalidTournamentMatchException('The time to join this match is over.');
        }
        if ($this->gameRepository->hasInProgress($user->getId())) {
            throw new GameInProgressException();
        }

        $this->candidateRepository->withQueueLock(function () use ($user, $match, $entry): void {
            $previous = $this->candidateRepository->findOneBy(['user' => $user]);
            if ($previous instanceof Candidate) {
                if ($previous->getMatch() === $match) {
                    throw new AlreadyQueuedException();
                }
                $this->removeCandidate($previous, $user);
                // removed before the new one is inserted: a user waits once
                $this->entityManager->flush();
            }

            $candidate = new Candidate();
            $candidate->setUser($user);
            $candidate->setDeck($entry->getDeck());
            $candidate->setMatch($match);
            $this->entityManager->persist($candidate);
            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException) {
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
            $this->removeCandidate($candidate, $user);
            $this->entityManager->flush();
        });
    }

    /**
     * The id of the match of a tournament the user waits with, null when they wait without one or are not in the queue.
     */
    public function findMatchId(User $user): ?int
    {
        return $this->candidateRepository->findOneBy(['user' => $user])?->getMatch()?->getId();
    }

    private function removeCandidate(Candidate $candidate, User $user): void
    {
        $this->entityManager->remove($candidate);
        // the host cancels their invitation
        $invitation = $candidate->getInvitation();
        if ($invitation instanceof Invitation && $invitation->getHost() === $user) {
            $this->entityManager->remove($invitation);
        }
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
