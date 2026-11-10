<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * The events of the versions of the game after the given one, in order.
     *
     * @return list<Event>
     */
    public function findSince(Game $game, int $version = 0): array
    {
        /** @var list<Event> */
        return $this->createQueryBuilder('e')
            ->andWhere('e.game = :game')
            ->andWhere('e.version > :version')
            ->setParameter('game', $game)
            ->setParameter('version', $version)
            ->orderBy('e.version', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * The last events of a message across the games (not the replays), the latest first, with their game.
     *
     * @param class-string $name
     *
     * @return list<Event>
     */
    public function findLatestOf(string $name, int $limit): array
    {
        /** @var list<Event> */
        return $this->createQueryBuilder('e')
            ->addSelect('g')
            ->join('e.game', 'g')
            ->andWhere('e.name = :name')
            ->andWhere('g.replayOf IS NULL')
            ->setParameter('name', $name)
            ->orderBy('e.id', \SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * The last events of the game, the latest first.
     *
     * @return list<Event>
     */
    public function findLatest(Game $game, int $limit): array
    {
        /** @var list<Event> */
        return $this->createQueryBuilder('e')
            ->andWhere('e.game = :game')
            ->setParameter('game', $game)
            ->orderBy('e.version', \SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
