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
     * Whether a game has ended, read from the database without loading the game (GameLockMiddleware loads it when a
     * message is dispatched); null for an unknown game.
     */
    public function hasEnded(string $gameId): ?bool
    {
        $place = $this->getEntityManager()->getConnection()
            ->fetchOne('SELECT current_place FROM games WHERE id = :id', ['id' => $gameId]);

        return $place === false ? null : $place === Game::STATE_ENDED;
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
            ->orderBy('mine.joinDate', \SortDirection::Descending)
            ->addOrderBy('g.id', \SortDirection::Ascending)
            ->addOrderBy('p.joinRank', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * The games a user plays that have started and not ended, as the ids of the game and of their player: no game is
     * loaded, so that a message can then be dispatched on it (GameLockMiddleware).
     *
     * @return list<array{gameId: string, playerId: string}>
     */
    public function findInProgressPlayersOf(string $userId): array
    {
        /** @var list<array{gameId: string, playerId: string}> */
        return $this->createQueryBuilder('g')
            ->select('g.id AS gameId', 'p.id AS playerId')
            ->join('g.players', 'p', 'WITH', 'IDENTITY(p.user) = :user')
            ->where('g.currentPlace NOT IN (:places)')
            ->setParameter('user', $userId)
            ->setParameter('places', ['join_game', Game::STATE_ENDED])
            ->orderBy('g.id', \SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();
    }
}
