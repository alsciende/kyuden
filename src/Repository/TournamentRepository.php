<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tournament>
 */
class TournamentRepository extends ServiceEntityRepository
{
    /**
     * Key of the advisory lock of the calendar of the tournaments (another key than the queue's).
     */
    private const int CALENDAR_LOCK_KEY = 5_000_002;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tournament::class);
    }

    /**
     * Runs the callback holding the lock of the calendar, so that two tournaments created at once do not both pass
     * the rules of the dates.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function withCalendarLock(callable $callback): mixed
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->executeStatement('SELECT pg_advisory_lock(:key)', ['key' => self::CALENDAR_LOCK_KEY]);
        try {
            return $callback();
        } finally {
            $connection->executeStatement('SELECT pg_advisory_unlock(:key)', ['key' => self::CALENDAR_LOCK_KEY]);
        }
    }

    /**
     * The tournament, its row locked until the end of the transaction (which the caller has begun).
     */
    public function findForUpdate(string $id): ?Tournament
    {
        return $this->find($id, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * The tournaments /tournaments lists, the first to begin first: those to be played (upcoming, locked, in
     * progress), and the cancelled ones that would not have begun yet.
     *
     * @return list<Tournament>
     */
    public function findListed(\DateTimeImmutable $now): array
    {
        /** @var list<Tournament> $tournaments */
        $tournaments = $this->createQueryBuilder('t')
            ->addSelect('o')
            ->join('t.organizer', 'o')
            ->where('t.status IN (:statuses)')
            ->orWhere('t.status = :cancelled AND t.startsAt > :now')
            ->setParameter('statuses', TournamentStatus::upcomingOrInProgress())
            ->setParameter('cancelled', TournamentStatus::Cancelled)
            ->setParameter('now', $now)
            ->orderBy('t.startsAt', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $tournaments;
    }

    /**
     * Whether the user organizes a tournament still to be played (neither ended nor cancelled).
     */
    public function hasUpcomingOrganizedBy(User $organizer): bool
    {
        return $this->count(['organizer' => $organizer, 'status' => TournamentStatus::upcomingOrInProgress()]) > 0;
    }

    /**
     * A tournament not cancelled beginning less than 12 hours (Tournament::MINIMUM_SPACING) before or after the
     * start given, the closest one.
     */
    public function findStartingNear(\DateTimeImmutable $startsAt): ?Tournament
    {
        $spacing = \DateInterval::createFromDateString(Tournament::MINIMUM_SPACING);
        /** @var list<Tournament> $tournaments */
        $tournaments = $this->createQueryBuilder('t')
            ->where('t.status != :cancelled')
            ->andWhere('t.startsAt > :after AND t.startsAt < :before')
            ->setParameter('cancelled', TournamentStatus::Cancelled)
            ->setParameter('after', $startsAt->sub($spacing))
            ->setParameter('before', $startsAt->add($spacing))
            ->getQuery()
            ->getResult();
        usort($tournaments, static fn (Tournament $a, Tournament $b): int => abs($a->getStartsAt()->getTimestamp() - $startsAt->getTimestamp())
            <=> abs($b->getStartsAt()->getTimestamp() - $startsAt->getTimestamp()));

        return $tournaments[0] ?? null;
    }

    /**
     * The ids of the tournaments the worker has to move on at that time (TournamentRunner): those open whose lock time
     * has come, those locked whose start has come, and those in progress whose players had to join their current round
     * by then.
     *
     * @return list<string>
     */
    public function findIdsToRun(\DateTimeImmutable $now): array
    {
        /** @var list<string> $ids */
        $ids = $this->createQueryBuilder('t')
            ->select('t.id')
            ->where('t.status = :open AND t.startsAt <= :lockedFrom')
            ->orWhere('t.status = :locked AND t.startsAt <= :now')
            ->orWhere('t.status = :inProgress AND t.roundStartedAt <= :joinedBy')
            ->setParameter('open', TournamentStatus::Open)
            ->setParameter('locked', TournamentStatus::Locked)
            ->setParameter('inProgress', TournamentStatus::InProgress)
            ->setParameter('now', $now)
            ->setParameter('lockedFrom', $now->modify('+' . Tournament::LOCK_LEAD))
            ->setParameter('joinedBy', $now->modify('-' . Tournament::JOIN_DELAY))
            ->orderBy('t.startsAt', \SortDirection::Ascending)
            ->getQuery()
            ->getSingleColumnResult();

        return $ids;
    }

    /**
     * The ids of the tournaments that have ended, the first to begin first.
     *
     * @return list<string>
     */
    public function findEndedIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->createQueryBuilder('t')
            ->select('t.id')
            ->where('t.status = :ended')
            ->setParameter('ended', TournamentStatus::Ended)
            ->orderBy('t.startsAt', \SortDirection::Ascending)
            ->addOrderBy('t.id', \SortDirection::Ascending)
            ->getQuery()
            ->getSingleColumnResult();

        return $ids;
    }
}
