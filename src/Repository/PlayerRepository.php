<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Data\GameMismatchException;
use App\Exception\Data\PlayerNotFoundException;
use Doctrine\ORM\Query;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Player>
 */
class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    public function get(string $playerId, ?Game $game = null): Player
    {
        $player = $this->find($playerId);

        if ($player === null) {
            throw new PlayerNotFoundException($playerId);
        }

        if ($game instanceof Game && $player->getGame()->getId() !== $game->getId()) {
            throw new GameMismatchException();
        }

        return $player;
    }

    /**
     * Id of the player of a user in a game, without loading any entity.
     */
    public function findIdByGameAndUser(string $gameId, string $userId): ?string
    {
        $playerId = $this->createQueryBuilder('p')
            ->select('p.id')
            ->andWhere('IDENTITY(p.game) = :gameId')
            ->andWhere('p.userId = :userId')
            ->setParameter('gameId', $gameId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult(Query::HYDRATE_SINGLE_SCALAR);

        return is_string($playerId) ? $playerId : null;
    }

    //    /**
    //     * @return Player[] Returns an array of Player objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Player
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
