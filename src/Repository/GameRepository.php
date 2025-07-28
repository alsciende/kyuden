<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use App\Exception\Data\GameNotFoundException;
use App\Exception\Data\WorkflowException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function get(string $gameId, ?string $expectedPlace = null): Game
    {
        $game = $this->find($gameId);

        if ($game === null) {
            throw new GameNotFoundException($gameId);
        }

        if (is_string($expectedPlace) && $game->getCurrentPlace() !== $expectedPlace) {
            throw new WorkflowException($expectedPlace, $game->getCurrentPlace() ?? '<undefined>');
        }

        return $game;
    }

    //    /**
    //     * @return Game[] Returns an array of Game objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('g.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Game
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
