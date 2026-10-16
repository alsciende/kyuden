<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Candidate;
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
