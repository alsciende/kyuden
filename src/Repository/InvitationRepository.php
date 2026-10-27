<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invitation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    /**
     * The invitation of a host that has not led to a game yet (a host has one at most), expired or not.
     */
    public function findUnusedOfHost(User $host): ?Invitation
    {
        return $this->findOneBy(['host' => $host, 'gameId' => null]);
    }

    public function findOneByCode(string $code): ?Invitation
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Deletes the invitations of a host that have not led to a game yet: their tokens and the candidates waiting with
     * them go with them (foreign keys).
     */
    public function deleteUnusedOfHost(User $host): void
    {
        $this->createQueryBuilder('i')
            ->delete()
            ->where('i.host = :host')
            ->andWhere('i.gameId IS NULL')
            ->setParameter('host', $host)
            ->getQuery()
            ->execute();
    }

    /**
     * Deletes every invitation of a host.
     */
    public function deleteByHost(User $host): void
    {
        $this->createQueryBuilder('i')
            ->delete()
            ->where('i.host = :host')
            ->setParameter('host', $host)
            ->getQuery()
            ->execute();
    }
}
