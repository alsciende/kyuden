<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Exception\Data\CardNotFoundException;
use App\Exception\Data\GameMismatchException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PhysicalCard>
 */
class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PhysicalCard::class);
    }

    public function get(string $cardId, ?Game $game = null): PhysicalCard
    {
        $card = $this->find($cardId);

        if ($card === null) {
            throw new CardNotFoundException($cardId);
        }

        if ($game instanceof Game && $card->getGame()->getId() !== $game->getId()) {
            throw new GameMismatchException();
        }

        return $card;
    }

    //    /**
    //     * @return Card[] Returns an array of Card objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Card
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
