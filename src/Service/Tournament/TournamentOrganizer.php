<?php

declare(strict_types=1);

namespace App\Service\Tournament;

use App\Entity\Tournament;
use App\Entity\User;
use App\Exception\Api\InvalidTournamentStartException;
use App\Exception\Api\NotOrganizerException;
use App\Exception\Api\TournamentAlreadyOrganizedException;
use App\Exception\Api\TournamentClosedException;
use App\Exception\Api\TournamentTooCloseException;
use App\Exception\Data\TournamentNotFoundException;
use App\Repository\TournamentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Creates and cancels the tournaments of an account, checking the rules of the dates against the clock: a tournament
 * begins on the hour or the half hour, at least 12 hours ahead, no other tournament (not cancelled) begins within 12
 * hours of it, on the whole site, and its organizer organizes no other tournament still to be played. The organizer
 * is not registered by creating it. They cancel it until it locks.
 */
final readonly class TournamentOrganizer
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidTournamentStartException
     * @throws TournamentAlreadyOrganizedException
     * @throws TournamentTooCloseException
     */
    public function create(User $organizer, \DateTimeImmutable $startsAt): Tournament
    {
        $startsAt = $startsAt->setTimezone(new \DateTimeZone('UTC'));
        $now = $this->clock->now();
        if ((int) $startsAt->format('s') !== 0 || (int) $startsAt->format('u') !== 0 || (int) $startsAt->format('i') % Tournament::START_STEP_MINUTES !== 0) {
            throw new InvalidTournamentStartException('A tournament begins on the hour or the half hour.');
        }
        if ($startsAt < $now->modify('+' . Tournament::MINIMUM_NOTICE)) {
            throw new InvalidTournamentStartException('A tournament begins at least 12 hours from now.');
        }

        // the rules of the calendar hold under its lock, so that two tournaments created at once both obey them
        return $this->tournamentRepository->withCalendarLock(function () use ($organizer, $startsAt, $now): Tournament {
            if ($this->tournamentRepository->hasUpcomingOrganizedBy($organizer)) {
                throw new TournamentAlreadyOrganizedException();
            }
            $other = $this->tournamentRepository->findStartingNear($startsAt);
            if ($other instanceof Tournament) {
                throw new TournamentTooCloseException($other->getStartsAt());
            }

            $tournament = new Tournament(Uuid::v4()->toString(), $organizer, $startsAt, random_bytes(32), $now);
            $this->entityManager->persist($tournament);
            $this->entityManager->flush();

            return $tournament;
        });
    }

    /**
     * @throws TournamentNotFoundException
     * @throws NotOrganizerException
     * @throws TournamentClosedException
     */
    public function cancel(string $tournamentId, User $user): Tournament
    {
        return $this->entityManager->wrapInTransaction(function () use ($tournamentId, $user): Tournament {
            $tournament = $this->tournamentRepository->findForUpdate($tournamentId) ?? throw new TournamentNotFoundException($tournamentId);
            if ($tournament->getOrganizer() !== $user) {
                throw new NotOrganizerException();
            }
            if ($tournament->isCancelled()) {
                throw new TournamentClosedException('The tournament is cancelled already.');
            }
            if (! $tournament->isOpenAt($this->clock->now())) {
                throw new TournamentClosedException('The tournament is locked: it can no longer be cancelled.');
            }

            $tournament->cancel();

            return $tournament;
        });
    }
}
