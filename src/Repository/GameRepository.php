<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use App\Exception\Data\GameNotFoundException;
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

    /**
     * @param string|list<string>|null $expectedPlace the place, or one of the places, the game must be in
     */
    public function get(string $gameId, string|array|null $expectedPlace = null): Game
    {
        $game = $this->find($gameId);

        if ($game === null) {
            throw new GameNotFoundException($gameId);
        }

        if ($expectedPlace !== null) {
            $game->assertPlace(...(array) $expectedPlace);
        }

        return $game;
    }

    /**
     * The game with its players, their stacks and the cards of the stacks with their logical cards, in a single query
     * (no lazy loading while building a view of the whole game).
     */
    public function findWithCards(string $gameId): ?Game
    {
        /** @var Game|null */
        return $this->createQueryBuilder('g')
            ->addSelect('p', 's', 'c', 'l')
            ->leftJoin('g.players', 'p')
            ->leftJoin('p.stacks', 's')
            ->leftJoin('s.cards', 'c')
            // the available actions read the abilities of the cards in hand
            ->leftJoin('c.logicalCard', 'l')
            ->where('g.id = :id')
            ->setParameter('id', $gameId)
            ->getQuery()
            ->getOneOrNullResult();
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
