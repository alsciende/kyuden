<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EarnedTrophy;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EarnedTrophy>
 */
class EarnedTrophyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EarnedTrophy::class);
    }

    /**
     * @return list<EarnedTrophy> what the user has earned, in no order
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    public function deleteByUser(User $user): int
    {
        $count = $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
        \assert(\is_int($count));

        return $count;
    }
}
