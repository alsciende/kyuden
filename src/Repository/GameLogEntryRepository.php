<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GameLogEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameLogEntry>
 */
class GameLogEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameLogEntry::class);
    }

    /**
     * The entries of the versions of the game after the given one, in order.
     *
     * @return list<GameLogEntry>
     */
    public function findSince(Game $game, int $version = 0): array
    {
        /** @var list<GameLogEntry> */
        return $this->createQueryBuilder('e')
            ->andWhere('e.game = :game')
            ->andWhere('e.version > :version')
            ->setParameter('game', $game)
            ->setParameter('version', $version)
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
