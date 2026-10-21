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
            ->addSelect('p', 'u', 's', 'c', 'l')
            ->leftJoin('g.players', 'p')
            // the view names the players
            ->leftJoin('p.user', 'u')
            ->leftJoin('p.stacks', 's')
            ->leftJoin('s.cards', 'c')
            // the available actions read the abilities of the cards in hand
            ->leftJoin('c.logicalCard', 'l')
            ->where('g.id = :id')
            ->setParameter('id', $gameId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The version of a game read from the database, not from the identity map: a message handled by another request
     * since the game was loaded is seen.
     */
    public function findVersion(string $gameId): ?int
    {
        $version = $this->getEntityManager()->getConnection()
            ->fetchOne('SELECT version FROM games WHERE id = :id', ['id' => $gameId]);

        return is_numeric($version) ? (int) $version : null;
    }

    /**
     * The games a user plays or has played, with their players, the last joined first (a single query).
     *
     * @return list<Game>
     */
    public function findByUser(string $userId): array
    {
        /** @var list<Game> */
        return $this->createQueryBuilder('g')
            ->addSelect('p')
            ->join('g.players', 'mine', 'WITH', 'IDENTITY(mine.user) = :user')
            ->join('g.players', 'p')
            ->setParameter('user', $userId)
            ->orderBy('mine.joinDate', 'DESC')
            ->addOrderBy('g.id', 'ASC')
            ->addOrderBy('p.joinRank', 'ASC')
            ->getQuery()
            ->getResult();
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
