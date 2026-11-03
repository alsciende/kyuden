<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Spectator;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The spectators of the games, written in SQL: two reads of the same spectator at once must not insert it twice.
 *
 * @extends ServiceEntityRepository<Spectator>
 */
class SpectatorRepository extends ServiceEntityRepository
{
    private const string DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Spectator::class);
    }

    /**
     * The user reads the game: it watches it from now on, or still does. Returns whether it has just arrived.
     */
    public function see(string $gameId, string $userId, \DateTimeImmutable $now): bool
    {
        $inserted = $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
                INSERT INTO spectators (game_id, user_id, arrived_at, last_seen_at) VALUES (:game, :user, :now, :now)
                ON CONFLICT (game_id, user_id) DO UPDATE SET last_seen_at = EXCLUDED.last_seen_at
                RETURNING (xmax = 0) AS inserted
                SQL,
            ['game' => $gameId, 'user' => $userId, 'now' => $now->format(self::DATE_FORMAT)],
        );

        // a boolean of PostgreSQL, whatever the driver makes of it
        return $inserted === true || $inserted === 't';
    }

    /**
     * Removes the spectators of a game who have not read it since a date. Returns how many were removed.
     */
    public function prune(string $gameId, \DateTimeImmutable $seenBefore): int
    {
        return (int) $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM spectators WHERE game_id = :game AND last_seen_at < :before',
            ['game' => $gameId, 'before' => $seenBefore->format(self::DATE_FORMAT)],
        );
    }

    /**
     * Removes the spectators of every game who have not read it since a date (games nobody reads any more: ended,
     * abandoned).
     */
    public function purge(\DateTimeImmutable $seenBefore): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM spectators WHERE last_seen_at < :before',
            ['before' => $seenBefore->format(self::DATE_FORMAT)],
        );
    }

    /**
     * Removes a spectator of a game. Returns whether it was watching.
     */
    public function leave(string $gameId, string $userId): bool
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM spectators WHERE game_id = :game AND user_id = :user',
            ['game' => $gameId, 'user' => $userId],
        ) > 0;
    }

    /**
     * The spectators of a game who have read it since a date, with their users, in their order of arrival.
     *
     * @return list<Spectator>
     */
    public function findSeenSince(string $gameId, \DateTimeImmutable $since): array
    {
        /** @var list<Spectator> */
        return $this->createQueryBuilder('s')
            ->addSelect('u')
            ->join('s.user', 'u')
            ->where('IDENTITY(s.game) = :game')
            ->andWhere('s.lastSeenAt >= :since')
            ->setParameter('game', $gameId)
            ->setParameter('since', $since)
            ->orderBy('s.arrivedAt', \SortDirection::Ascending)
            ->addOrderBy('u.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * The number of spectators of each game who have read it since a date, by id of the game (games without one are
     * missing).
     *
     * @param list<string> $gameIds
     *
     * @return array<string, int>
     */
    public function countSeenSince(array $gameIds, \DateTimeImmutable $since): array
    {
        if ($gameIds === []) {
            return [];
        }

        /** @var list<array{gameId: string, spectators: int|string}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.game) AS gameId', 'COUNT(s.user) AS spectators')
            ->where('s.game IN (:games)')
            ->andWhere('s.lastSeenAt >= :since')
            ->setParameter('games', $gameIds)
            ->setParameter('since', $since)
            ->groupBy('s.game')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(fn (array $row): array => [$row['gameId'], (int) $row['spectators']], $rows), 1, 0);
    }

    public function deleteByUser(User $user): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
