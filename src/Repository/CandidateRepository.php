<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Candidate;
use App\Entity\TournamentMatch;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Candidate>
 */
class CandidateRepository extends ServiceEntityRepository
{
    /**
     * Key of the advisory lock of the queue.
     */
    private const int QUEUE_LOCK_KEY = 5_000_001;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Candidate::class);
    }

    /**
     * The number of the candidates waiting in the open queue (not those of an invitation or a match of a tournament,
     * who wait for each other only), the user given left out.
     */
    public function countOpenQueueExcept(User $user): int
    {
        return (int) $this->createQueryBuilder('candidate')
            ->select('COUNT(candidate.id)')
            ->where('candidate.invitation IS NULL')
            ->andWhere('candidate.match IS NULL')
            ->andWhere('candidate.user != :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The candidates waiting with these matches of a tournament, with their users.
     *
     * @param list<TournamentMatch> $matches
     *
     * @return list<Candidate>
     */
    public function findWaitingWithMatches(array $matches): array
    {
        if ($matches === []) {
            return [];
        }

        /** @var list<Candidate> $candidates */
        $candidates = $this->createQueryBuilder('candidate')
            ->addSelect('user')
            ->join('candidate.user', 'user')
            ->where('candidate.match IN (:matches)')
            ->setParameter('matches', $matches)
            ->getQuery()
            ->getResult();

        return $candidates;
    }

    /**
     * Runs a callback while holding the lock of the queue (a PostgreSQL advisory lock of the session, held across the
     * transactions of the callback).
     *
     * @param callable(): void $callback
     */
    public function withQueueLock(callable $callback): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->executeStatement('SELECT pg_advisory_lock(:key)', ['key' => self::QUEUE_LOCK_KEY]);
        try {
            $callback();
        } finally {
            $connection->executeStatement('SELECT pg_advisory_unlock(:key)', ['key' => self::QUEUE_LOCK_KEY]);
        }
    }
}
