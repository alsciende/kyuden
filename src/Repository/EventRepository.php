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
}
