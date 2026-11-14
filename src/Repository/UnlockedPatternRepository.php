<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UnlockedPattern;
use App\Entity\User;
use App\Enum\BannerPattern;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UnlockedPattern>
 */
class UnlockedPatternRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UnlockedPattern::class);
    }

    /**
     * @return list<BannerPattern> what the user has earned, in no order
     */
    public function findValuesByUser(User $user): array
    {
        return array_map(
            static fn (UnlockedPattern $unlocked): BannerPattern => $unlocked->getPattern(),
            $this->findBy(['user' => $user]),
        );
    }

    public function deleteByUser(User $user): int
    {
        $count = $this->createQueryBuilder('u')
            ->delete()
            ->where('u.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
        \assert(\is_int($count));

        return $count;
    }
}
